// MOTD editor (resources/views/js/motd.js): parsing, minimal § codes, selection styling, typing, pasting and limits.
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const document = { activeElement: null, addEventListener() {}, removeEventListener() {} };
const context = { window: {}, document, setInterval: () => 0, clearInterval() {} };
vm.createContext(context);
for (const file of ['motd.js', 'dashboard.js']) vm.runInContext(fs.readFileSync(__dirname + '/../resources/views/js/' + file, 'utf8'), context);
const { parse, serialize } = context.window.psMotd;
const canonical = (value) => serialize(parse(value));

function editor(value) {
  const input = () => ({ value: '', selectionStart: 0, selectionEnd: 0, focus() { document.activeElement = this; }, setSelectionRange(a, b) { this.selectionStart = a; this.selectionEnd = b; } });
  const e = context.window.psMotdEditor({ value });
  e.$refs = { line0: input(), line1: input() };
  e.$nextTick = (fn) => fn();
  e.$watch = () => {};
  e.init();
  return e;
}
const select = (e, line, start, end = start) => { e.selection = { line, start, end }; };
const type = (e, line, text) => { const input = e.$refs['line' + line]; input.value = text; e.edit(line, { target: input }); };

// Canonical text stays exactly as it is; broken text loses its duplicate and dangling codes.
for (const value of ['§6§lDeck §7· §aSurvival\n§c§oNieuw: §bmodpacks', 'Plain', '§x§1§2§a§b§3§4RGB §rtext', '§a😀 ok', '']) assert.equal(canonical(value), value);
assert.equal(canonical('§a§cHello§r§r'), '§cHello');
assert.equal(canonical('§l§aGreen'), '§aGreen', 'a colour code clears formatting set before it, as in Minecraft');
assert.equal(canonical('Hi§'), 'Hi§', 'a lone § is kept as text');

// Loading never rewrites a value the user did not touch, even when it is not canonical.
let e = editor('§a§aHello');
assert.equal(e.value, '§a§aHello');
assert.equal(e.$refs.line0.value, 'Hello');

// Colour on part of a line keeps the rest; picking the same colour again adds nothing.
e = editor('§6Gold text');
select(e, 0, 5, 9);
e.setColor('b');
assert.equal(e.value, '§6Gold §btext');
e.setColor('b');
e.setColor('b');
assert.equal(e.value, '§6Gold §btext', 'no duplicate colour codes');
assert(e.colorActive('b') && !e.colorActive('6'));
e.setColor('6');
assert.equal(e.value, '§6Gold text');

// Bold survives a later colour change, and toggles off for part of the selection.
e = editor('Hello');
select(e, 0, 0, 5);
e.toggleFormat('l');
e.setColor('c');
assert.equal(e.value, '§c§lHello');
assert(e.formatActive('l'));
select(e, 0, 2, 5);
e.toggleFormat('l');
assert.equal(e.value, '§c§lHe§cllo');
select(e, 0, 0, 5);
e.clearFormatting();
assert.equal(e.value, 'Hello');
select(e, 0, 0, 5);
e.setColor('7');
assert.equal(e.value, 'Hello', 'gray is the default MOTD colour and needs no code');

// Typing takes the style before the caret; a style picked without a selection applies to the next text.
e = editor('§cHi');
type(e, 0, 'Hi!');
assert.equal(e.value, '§cHi!');
select(e, 0, 3);
e.setColor('a');
assert.equal(e.value, '§cHi!', 'a pending style changes nothing until you type');
type(e, 0, 'Hi!X');
assert.equal(e.value, '§cHi!§aX');
type(e, 0, 'H!X');
assert.equal(e.value, '§cH!§aX', 'deleting keeps the styles of the other characters');

// Pasted § codes apply; a pasted line break moves the rest to line 2.
e = editor('A');
type(e, 0, 'A§eYo');
assert.equal(e.value, 'A§eYo');
e = editor('§bTop');
type(e, 0, 'Top\nBottom');
assert.equal(e.value, '§bTop\nBottom');
assert.equal(e.$refs.line0.value, 'Top');
type(e, 1, 'Bottom!');
assert.equal(e.value, '§bTop\nBottom!');

// Line 2 inherits formatting across the line break, so styling it does not repeat codes.
e = editor('§aOne\nTwo');
select(e, 1, 0, 3);
e.toggleFormat('n');
assert.equal(e.value, '§aOne\n§nTwo');

// The 512 character limit counts codes and refuses edits beyond it.
e = editor('x'.repeat(511));
type(e, 0, 'x'.repeat(513));
assert.equal(e.value, 'x'.repeat(511));
assert.equal(e.$refs.line0.value, 'x'.repeat(511));
assert(e.notice.includes('512'));
assert.equal(editor('§a😀').length(), 3, 'length counts characters, not UTF-16 units');

// Extra lines are flagged and joined to line 2 on the first edit.
e = editor('One\nTwo\nThree');
assert(e.notice);
type(e, 0, 'One!');
assert.equal(e.value, 'One!\nTwo Three');

// Raw code editing accepts & as §.
e = editor('');
e.editCodes({ target: { value: '&aGreen & more' } });
assert.equal(e.value, '§aGreen & more');
assert.equal(e.$refs.line0.value, 'Green & more');

// Read-only previews for cards: Minecraft resets, plain text only, line breaks and obfuscation.
const d = context.window.pelicanSimplify({}, {});
let preview = d.motdPreview('§lBold§cRed§nUnder§rNormal\n§kab c');
assert.deepEqual(preview.map((p) => p.text), ['Bold', 'Red', 'Under', 'Normal', '\n', '▒▒ ▒']);
assert.equal(preview[0].style.fontWeight, 'bold');
assert.equal(preview[1].style.fontWeight, null);
assert.equal(preview[1].style.color, '#ff5555');
assert(preview[2].style.textDecoration.includes('underline'));
assert.equal(preview[3].style.color, '#aaaaaa');
assert.equal(d.motdPreview('<script>bad</script>')[0].text, '<script>bad</script>');
console.log('PASS MOTD editor: exact round trips, minimal codes without duplicates, selection colours and formats, typing, pasting, two lines, 512 limit and safe previews');
