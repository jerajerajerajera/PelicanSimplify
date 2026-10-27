#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
PHP_BIN=${PHP_BIN:-php}
python3 "$ROOT/tests/package-layout.py"
python3 "$ROOT/tests/hub-check.py"
while IFS= read -r -d '' FILE; do "$PHP_BIN" -l "$FILE" >/dev/null; done < <(find "$ROOT" -name '*.php' ! -name '*.blade.php' -print0)
for FILE in "$ROOT"/tests/*.php; do [[ "$FILE" == *.fixture.php ]] || "$PHP_BIN" "$FILE"; done
if [[ -d "$ROOT/server-directory/tests" ]]; then for FILE in "$ROOT"/server-directory/tests/*.php; do "$PHP_BIN" "$FILE"; done; fi
for FILE in "$ROOT"/tests/*.js; do [[ "$FILE" == */theme-browser.js || "$FILE" == */browser-harness.js || "$FILE" == */lang-en.js ]] || node -r "$ROOT/tests/lang-en.js" "$FILE"; done
if [[ ${RUN_BROWSER_TESTS:-0} == 1 ]]; then node "$ROOT/tests/theme-browser.js"; fi
echo 'All enabled checks passed.'
