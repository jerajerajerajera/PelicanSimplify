#!/usr/bin/env python3
"""Finds English texts that are written straight into the code instead of lang/en/*.php.

Usage: python3 scripts/untranslated.py [--all] [paths...]
Prints file:line and the text. A text that must stay as it is (a product name, a Minecraft command, a log
line) goes in scripts/untranslated-allow.txt, one per line. tests/run.sh runs this and fails on new texts.
English labels that double as ids (tab names, option constants) are fine when lang/en has the same text under
one of ID_PATHS: the code shows them through Translations::text(), labels() or rows().
"""
import json, pathlib, re, subprocess, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
ALLOW_FILE = ROOT / 'scripts' / 'untranslated-allow.txt'
SOURCES = [('resources/views', '*.blade.php'), ('resources/views/js', '*.js'), ('src', '*.php')]
SKIP = {'resources/views/js/embed.js'}  # the public website embed stays English for now

STRING = re.compile(r"'((?:[^'\\\n]|\\.)*)'|\"((?:[^\"\\\n]|\\.)*)\"")
SINGLE = re.compile(r"'((?:[^'\\\n]|\\.)*)'()")
TEXT_ATTR = re.compile(r'(?<![:\w-])(?:title|aria-label|placeholder|alt|data-tip|label|wire:confirm)="([^"]*)"()')
TEXT_NODE = re.compile(r'>([^<>]*)<')
TRANSLATED = re.compile(r"(?:__|trans|trans_choice|t|tc|sscText|Simplify\.t|Simplify\.tc|@lang)\(\s*'[^']*'")
ID_PATHS = ['settings.tabs', 'settings.steps', 'settings.step_hints', 'settings.check_groups', 'settings.experiences', 'settings.channels',
            'settings.features', 'settings.abilities', 'settings.performance', 'settings.egg_modes', 'settings.explain_audiences', 'maker.minecraft', 'maker.steps']
THROWN = re.compile(r'new \\?(RuntimeException|LogicException|InvalidArgumentException|UnexpectedValueException|JsonException)\(')  # internal errors stay English for the log
CODEISH = re.compile(r'^[\w.:/#@$%*+=<>()\[\]{},;|&!?\\^~`-]*$')

def looks_like_text(s):
    padded = s != s.strip() and len(s.strip()) > 2 and re.fullmatch(r"[\s·—,:]*[A-Za-z][A-Za-z ']*[A-Za-z][\s·—,:.]*", s)
    s = s.strip()
    if padded and not re.fullmatch(r'[a-z0-9_-]+', s.split()[0] if s.split() else ''): return True
    if padded and len(s.split()) == 1 and s.isalpha() and s.islower(): return True
    if len(s) < 3 or not re.search(r'[A-Za-z]{2,}', s): return False
    if '::' in s or s.startswith(('http', '/', '#', '.', '$', '--', 'ps-', 'ssc-', 'fi-', 'is-', 'x-', 'wire:', 'data-', 'aria-', 'tabler-', 'heroicon')): return False
    if CODEISH.match(s) or re.fullmatch(r'\(.*\)|[\d.]+ ?[KMGT]?B|(cubic-bezier|translate[XY]?|scale|var|hsla?|rgba?)\(.*|[a-z0-9 ,:()-]+(, ?[a-z0-9 :()-]+)+', s) or len(re.findall(r'[A-Za-z]', s)) < len(s) / 3: return False  # identifiers, keys, paths, CSS selectors without spaces
    words = s.split()
    if re.search(r'[{}<>=;$]|=>|\bfunction\b|\breturn\b|\(\)|&&|\|\|', s): return False  # code or markup
    if all(re.fullmatch(r'[a-z0-9:_./-]+', w) for w in words): return False  # class lists, commands
    if len(words) == 1: return s[0].isupper() and s.isalpha() and len(s) > 2
    return True

def allowed():
    allow = set()
    if ALLOW_FILE.is_file(): allow = {line.strip() for line in ALLOW_FILE.read_text().splitlines() if line.strip() and not line.startswith('#')}
    return allow | id_texts()

def id_texts():
    """English texts under ID_PATHS in lang/en, read through PHP."""
    code = 'echo json_encode(array_map(fn ($f) => require $f, array_combine(array_map(fn ($f) => basename($f, ".php"), glob($argv[1] . "/*.php")), glob($argv[1] . "/*.php"))));'
    try: data = json.loads(subprocess.run(['php', '-r', code, str(ROOT / 'lang' / 'en')], capture_output=True, text=True, check=True).stdout)
    except (OSError, subprocess.CalledProcessError, ValueError): return set()
    found = set()
    def walk(node):
        if isinstance(node, dict): [walk(v) for v in node.values()]
        elif isinstance(node, str): found.add(node.strip()); found.add(node.replace("'", "\\'").strip())
    for path in ID_PATHS:
        node = data
        for part in path.split('.'): node = node.get(part, {}) if isinstance(node, dict) else {}
        walk(node)
    return found

def scan(path):
    text = path.read_text()
    rel = path.relative_to(ROOT).as_posix()
    blade = rel.endswith('.blade.php')
    if blade: text = re.sub(r'\{\{--.*?--\}\}', lambda m: '\n' * m.group(0).count('\n'), text, flags=re.S)
    text = re.sub(r'/\*.*?\*/', lambda m: '\n' * m.group(0).count('\n'), text, flags=re.S)
    found = []
    script = False
    for number, line in enumerate(text.split('\n'), 1):
        opens, closes = line.count('<script'), line.count('</script>')
        in_script = script or opens > closes
        script = (script or opens > 0) and not (closes >= opens and closes > 0)
        code = re.sub(r'^\s*(//|#|\*).*', '', line)
        code = re.sub(r'(?<![:"\'])//(?![/\w.-]*\.\w).*$', '', code) if not blade else code
        code = TRANSLATED.sub('', code)
        if THROWN.search(code): continue
        if re.search(r'(Log::|logger\(|report\(|->info\(|->warn\(|->error\(|->line\(|->comment\(|console\.(log|warn|error))', code): continue
        for m in (list(SINGLE.finditer(code)) if blade else STRING.finditer(code)):
            s = m.group(1) if m.group(1) is not None else m.group(2)
            if looks_like_text(s): found.append((number, s))
        if blade:
            for m in TEXT_ATTR.finditer(code):
                s = ' '.join(re.sub(r'\{\{.*?\}\}|&\w+;', ' ', m.group(1)).split())
                if looks_like_text(s): found.append((number, s))
        if blade:
            stripped = re.sub(r'\{\{.*?\}\}|\{!!.*?!!\}|&\w+;|@\w+(\((?:[^()]|\([^()]*\))*\))?', ' ', code)
            for m in TEXT_NODE.finditer(stripped):
                s = ' '.join(m.group(1).split())
                if looks_like_text(s) and not re.search(r'[\'"]', s): found.append((number, s))
            if '<' not in stripped and '>' not in stripped.replace('->', '').replace('=>', '') and not in_script and not re.match(r'\s*@(php|endphp|foreach|for|while|switch|case|include|livewire|props|aware|js|class|style)\b', code):
                s = ' '.join(stripped.split())
                if looks_like_text(s) and not re.search(r'[\'"()=]|^\w+:\s|,$', s) and len(s.split()) > 1: found.append((number, s))
    return rel, found

def main(args):
    show_all = '--all' in args
    paths = [a for a in args if not a.startswith('--')]
    files = [ROOT / p for p in paths] if paths else [f for base, pattern in SOURCES for f in sorted((ROOT / base).rglob(pattern))]
    allow = set() if show_all else allowed()
    total = 0
    for f in files:
        rel, found = scan(f)
        if rel in SKIP and not paths: continue
        for number, s in found:
            if s.strip() in allow: continue
            total += 1
            print(f'{rel}:{number}: {s}')
    print(f'{total} untranslated text(s)', file=sys.stderr)
    return total

if __name__ == '__main__':
    sys.exit(1 if main(sys.argv[1:]) else 0)
