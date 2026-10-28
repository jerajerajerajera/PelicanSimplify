# Translating

Simplify follows the language each person picks in their Pelican profile. It ships all 32 languages Pelican offers. A text missing from a language falls back to English.

## Fixing a text

Every text lives in `lang/<language>/<group>.php`, with the English original at the same key in `lang/en/<group>.php`. Change the text, keep the key. Open a pull request against the `snapshot` branch, or open an issue with the key, the current text and your suggestion.

Keep these as they are:

- Placeholders such as `:name` or `:count`. Their order may change.
- Pelican's own menu paths, such as `Admin → Eggs`. Arabic paths inside Simplify use `←`.
- Product names, commands and file names (Pelican, Wings, Minecraft, `server.properties`).

## Plurals

A text with `|` has one form per plural rule of the language, in Laravel's order. English has two (`:count server|:count servers`), Polish and Russian three, Arabic six (zero, one, two, few, many, other). Chinese, Japanese, Korean, Vietnamese, Indonesian and Turkish use one form, so no `|`. Ranges like `{0}` or `[2,*]` stay only where the English text has them.

## Changing a text on your own panel

You can override texts without changing the plugin: put the same file under `lang/vendor/pelican-simplify/<language>/<group>.php` in your Pelican directory, with only the keys you want to change. A Pelican language plugin can ship these files too.

## Checks

`php tests/translations.php` compares every language with English: missing or extra keys, placeholders and the number of plural forms. `python3 scripts/untranslated.py` finds English text written straight into the code instead of `lang/en`. Both run in `bash tests/run.sh`.

Right-to-left languages use logical CSS (`margin-inline-start`, `inset-inline-end`) instead of left and right. `SIMPLIFY_LOCALE=ar node tests/rtl-browser.js` checks the Arabic layout.

## Adding a language

Copy `lang/en` to `lang/<code>`, using Pelican's language code (`pt_BR`, `zh_TW`), translate the texts and run the checks. If the language has more than two plural forms, add it to `Translations::PLURAL_FORMS`.
