/* Minecraft MOTD editor, shared by the server dashboard and the server creator.
   The MOTD is edited as two plain lines with a style per character; the § codes are generated from that
   model, so they are always minimal: no duplicate colours, and a reset only where formatting has to end.
   Minecraft semantics: a colour code clears bold/italic/…, so formats are written after the colour. */
(() => {
  const COLORS = [
    ['0', 'Black', '#000000'], ['1', 'Dark blue', '#0000aa'], ['2', 'Dark green', '#00aa00'], ['3', 'Dark aqua', '#00aaaa'],
    ['4', 'Dark red', '#aa0000'], ['5', 'Dark purple', '#aa00aa'], ['6', 'Gold', '#ffaa00'], ['7', 'Gray', '#aaaaaa'],
    ['8', 'Dark gray', '#555555'], ['9', 'Blue', '#5555ff'], ['a', 'Green', '#55ff55'], ['b', 'Aqua', '#55ffff'],
    ['c', 'Red', '#ff5555'], ['d', 'Light purple', '#ff55ff'], ['e', 'Yellow', '#ffff55'], ['f', 'White', '#ffffff'],
  ];
  const FORMATS = [['l', 'Bold'], ['o', 'Italic'], ['n', 'Underline'], ['m', 'Strikethrough'], ['k', 'Obfuscated']];
  const FLAGS = FORMATS.map(([code]) => code);
  const HEX = Object.fromEntries(COLORS.map(([code, , hex]) => [code, hex]));
  const DEFAULT_COLOR = '#aaaaaa';
  const MAX = 512;
  const LINES = 2;
  const plain = () => ({ c: '', l: false, o: false, n: false, m: false, k: false });
  const same = (a, b) => a.c === b.c && FLAGS.every((f) => a[f] === b[f]);
  const isDefault = (s) => same(s, plain());
  const chars = (text) => Array.from(String(text ?? ''));

  /** Parse legacy text into lines of { chars, styles }. Formatting carries over the line break, as in Minecraft. */
  function parse(value) {
    const text = chars(String(value ?? '').replace(/\r\n?/g, '\n'));
    const lines = [{ chars: [], styles: [] }];
    let style = plain();
    for (let i = 0; i < text.length; i++) {
      const ch = text[i], next = (text[i + 1] || '').toLowerCase();
      if (ch === '§' && next === 'x' && /^(?:§[0-9a-f]){6}$/i.test(text.slice(i + 2, i + 14).join(''))) {
        style = { ...plain(), c: '#' + text.slice(i + 2, i + 14).filter((_, n) => n % 2).join('').toLowerCase() };
        i += 13;
      } else if (ch === '§' && /^[0-9a-fk-or]$/.test(next)) {
        if (HEX[next]) style = { ...plain(), c: next };
        else if (next === 'r') style = plain();
        else style = { ...style, [next]: true };
        i++;
      } else if (ch === '\n') lines.push({ chars: [], styles: [] });
      else {
        const line = lines[lines.length - 1];
        line.chars.push(ch);
        line.styles.push(style);
      }
    }
    return lines;
  }

  const code = (c) => (c.startsWith('#') ? '§x' + chars(c.slice(1)).map((d) => '§' + d).join('') : '§' + c);

  /** Write lines back as legacy text with the fewest codes that give exactly these styles. */
  function serialize(lines) {
    let out = '', prev = plain();
    lines.forEach((line, index) => {
      if (index) out += '\n';
      line.chars.forEach((ch, i) => {
        const s = line.styles[i];
        // A space only shows underline and strikethrough, so it never needs codes of its own otherwise.
        const blank = /\s/.test(ch) && !s.n && !s.m && !prev.n && !prev.m;
        if (!blank && !same(s, prev)) {
          let base = prev;
          if (s.c !== prev.c || FLAGS.some((f) => prev[f] && !s[f])) {
            out += s.c ? code(s.c) : '§r';
            base = { ...plain(), c: s.c };
          }
          for (const f of FLAGS) if (s[f] && !base[f]) out += '§' + f;
          prev = s;
        }
        out += ch;
      });
    });
    return out;
  }

  /** Consecutive characters with the same style, ready to render as spans (text only, never HTML). */
  function runs(line, scramble = false) {
    const parts = [];
    line.chars.forEach((ch, i) => {
      const s = line.styles[i], last = parts[parts.length - 1];
      const shown = scramble && s.k && ch.trim() ? String.fromCharCode(33 + Math.floor(Math.random() * 94)) : ch;
      if (last && same(last.s, s)) last.text += shown;
      else parts.push({ s, text: shown });
    });
    return parts.map(({ s, text }) => ({ text, obfuscated: s.k, style: css(s) }));
  }

  function css(s) {
    const decoration = [s.n && 'underline', s.m && 'line-through'].filter(Boolean).join(' ');
    return { color: s.c ? HEX[s.c] || s.c : DEFAULT_COLOR, fontWeight: s.l ? 'bold' : null, fontStyle: s.o ? 'italic' : null, textDecoration: decoration || null };
  }

  /** Text without codes, for search and short labels. */
  const strip = (value) => parse(value).map((line) => line.chars.join('')).join('\n');

  window.psMotd = { COLORS, FORMATS, MAX, parse, serialize, runs, strip, plain, same };

  /* Alpine component. Bind with x-modelable="value" + x-model, or pass { value: $wire.entangle(...) }. */
  window.psMotdEditor = function (options = {}) {
    return {
      value: options.value ?? '',
      name: () => String(options.name?.() || 'Minecraft Server'),
      icon: () => String(options.icon?.() || ''),
      colors: COLORS,
      formats: FORMATS,
      lines: [],
      written: null,
      selection: { line: 0, start: 0, end: 0 },
      pending: null,
      showCodes: false,
      notice: '',
      tick: 0,
      timer: null,

      init() {
        this.load(this.value);
        this.$watch('value', (value) => {
          if (value !== this.written) this.load(value);
        });
        this.track = () => this.readSelection();
        document.addEventListener('selectionchange', this.track);
        this.timer = setInterval(() => {
          if (this.lines.some((line) => line.styles.some((s) => s.k)) && this.$root?.offsetParent !== null) this.tick++;
        }, 120);
      },
      destroy() {
        document.removeEventListener('selectionchange', this.track);
        clearInterval(this.timer);
      },

      load(value) {
        const lines = parse(value);
        this.notice = lines.length > LINES ? 'Minecraft shows two lines. The extra lines are joined to line 2 when you edit.' : '';
        while (lines.length > LINES) {
          const extra = lines.pop(), last = lines[lines.length - 1];
          if (extra.chars.length) {
            last.chars.push(' ', ...extra.chars);
            last.styles.push(last.styles[last.styles.length - 1] || plain(), ...extra.styles);
          }
        }
        while (lines.length < LINES) lines.push({ chars: [], styles: [] });
        this.lines = lines;
        this.written = String(value ?? '');
        this.pending = null;
        this.$nextTick?.(() => this.lines.forEach((line, index) => {
          const input = this.input(index);
          if (input && input.value !== line.chars.join('')) input.value = line.chars.join('');
        }));
      },
      input(index) {
        return this.$refs?.['line' + index];
      },
      text(index) {
        return this.lines[index]?.chars.join('') ?? '';
      },

      /** Store the edit and publish the new value. Edits that would exceed 512 characters are refused. */
      commit(lines) {
        const value = serialize(lines[1]?.chars.length ? lines : lines.slice(0, 1));
        if (chars(value).length > MAX) {
          this.notice = 'The MOTD can contain at most ' + MAX + ' characters, including colour codes.';
          return false;
        }
        this.notice = '';
        this.lines = lines;
        this.written = value;
        this.value = value;
        return true;
      },
      length() {
        return chars(this.written ?? '').length;
      },

      /** Typing: keep the styles of untouched characters; new text takes the style before the caret. */
      edit(index, event) {
        const el = event.target, next = chars(el.value), line = this.lines[index];
        const old = line.chars;
        let start = 0;
        while (start < old.length && start < next.length && old[start] === next[start]) start++;
        let end = 0;
        while (end < old.length - start && end < next.length - start && old[old.length - 1 - end] === next[next.length - 1 - end]) end++;
        const inserted = next.slice(start, next.length - end).join('');
        const inherit = this.pending && this.pending.line === index ? this.pending.style : line.styles[start - 1] || line.styles[start] || plain();
        const piece = this.styledPiece(inherit, inserted);
        const lines = this.lines.map((l) => ({ chars: [...l.chars], styles: [...l.styles] }));
        const target = lines[index];
        target.chars.splice(start, old.length - start - end, ...piece[0].chars);
        target.styles.splice(start, old.length - start - end, ...piece[0].styles);
        // A pasted line break moves the rest to line 2; line 2 has no line below it.
        if (piece.length > 1) {
          const rest = piece.slice(1).reduce((all, p, n) => ({ chars: [...all.chars, ...(n ? [' '] : []), ...p.chars], styles: [...all.styles, ...(n ? [p.styles[0] || plain()] : []), ...p.styles] }), { chars: [], styles: [] });
          if (index === 0) {
            const tail = { chars: target.chars.splice(start + piece[0].chars.length), styles: target.styles.splice(start + piece[0].styles.length) };
            lines[1] = { chars: [...rest.chars, ...tail.chars, ...lines[1].chars], styles: [...rest.styles, ...tail.styles, ...lines[1].styles] };
          } else {
            target.chars.splice(start + piece[0].chars.length, 0, ' ', ...rest.chars);
            target.styles.splice(start + piece[0].styles.length, 0, inherit, ...rest.styles);
          }
        }
        if (!this.commit(lines)) {
          el.value = old.join('');
          return;
        }
        this.pending = null;
        if (el.value !== this.text(index)) {
          el.value = this.text(index);
          if (index === 0 && piece.length > 1) this.input(1)?.focus();
        }
      },
      /** Pasted text may contain § codes of its own: they are applied on top of the inherited style. */
      styledPiece(inherit, text) {
        if (!text.includes('§')) {
          return text.split('\n').map((part) => ({ chars: chars(part), styles: chars(part).map(() => inherit) }));
        }
        const parsed = parse(text);
        let reached = false;
        return parsed.map((line) => ({ chars: line.chars, styles: line.styles.map((s) => {
          if (!isDefault(s)) reached = true;
          return reached ? s : inherit;
        }) }));
      },
      keydown(index, event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          if (index === 0) this.input(1)?.focus();
        } else if (index === 1 && event.key === 'Backspace' && event.target.selectionStart === 0 && event.target.selectionEnd === 0) {
          event.preventDefault();
          const first = this.input(0);
          first?.focus();
          first?.setSelectionRange(first.value.length, first.value.length);
        } else if (index === 0 && event.key === 'ArrowDown') this.input(1)?.focus();
        else if (index === 1 && event.key === 'ArrowUp') this.input(0)?.focus();
      },

      readSelection() {
        const el = document.activeElement;
        const index = [0, 1].find((n) => this.input(n) === el);
        if (index === undefined) return;
        const value = el.value;
        const start = chars(value.slice(0, el.selectionStart ?? 0)).length, end = chars(value.slice(0, el.selectionEnd ?? 0)).length;
        const sel = this.selection;
        if (sel.line !== index || sel.start !== start || sel.end !== end) {
          this.selection = { line: index, start, end };
          if (this.pending && (this.pending.line !== index || this.pending.at !== start || start !== end)) this.pending = null;
        }
      },
      /** The style the toolbar shows: the selected text, the pending style, or the character before the caret. */
      selectedStyles() {
        const { line, start, end } = this.selection, styles = this.lines[line]?.styles || [];
        if (end > start) return styles.slice(start, end);
        if (this.pending && this.pending.line === line) return [this.pending.style];
        return [styles[start - 1] || styles[start] || plain()];
      },
      colorActive(colorCode) {
        const styles = this.selectedStyles();
        return styles.length > 0 && styles.every((s) => (s.c || '7') === colorCode);
      },
      formatActive(flag) {
        const styles = this.selectedStyles();
        return styles.length > 0 && styles.every((s) => s[flag]);
      },

      /** Apply a change to the selection, or remember it for the next typed text when nothing is selected. */
      restyle(change) {
        const { line, start, end } = this.selection;
        if (end <= start) {
          const base = this.selectedStyles()[0];
          this.pending = { line, at: start, style: change(base) };
          return;
        }
        const lines = this.lines.map((l) => ({ chars: [...l.chars], styles: [...l.styles] }));
        for (let i = start; i < end; i++) lines[line].styles[i] = change(lines[line].styles[i]);
        // Toolbar clicks are not input events, so tell forms around the editor (like the creator draft) about them.
        if (this.commit(lines)) this.$root?.dispatchEvent?.(new Event('change', { bubbles: true }));
      },
      setColor(colorCode) {
        this.restyle((s) => ({ ...s, c: colorCode === '7' ? '' : colorCode }));
      },
      toggleFormat(flag) {
        const on = !this.formatActive(flag);
        this.restyle((s) => ({ ...s, [flag]: on }));
      },
      clearFormatting() {
        this.restyle(() => plain());
      },

      /** Advanced: the exact text that is saved, with § codes. */
      editCodes(event) {
        const value = String(event.target.value).replace(/&(?=[0-9a-fk-or])/gi, '§');
        if (chars(value).length > MAX) {
          this.notice = 'The MOTD can contain at most ' + MAX + ' characters, including colour codes.';
          event.target.value = this.written;
          return;
        }
        this.value = value;
        this.load(value);
        this.written = value;
      },

      editorRuns(index) {
        return runs(this.lines[index] || { chars: [], styles: [] });
      },
      previewRuns(index) {
        this.tick;
        return runs(this.lines[index] || { chars: [], styles: [] }, true);
      },
      scroll(index, event) {
        const mirror = this.$refs?.['mirror' + index];
        if (mirror) mirror.scrollLeft = event.target.scrollLeft;
      },
    };
  };
})();
