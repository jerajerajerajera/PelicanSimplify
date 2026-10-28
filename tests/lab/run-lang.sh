#!/usr/bin/env bash
# The language walk on a lab panel that run-lab.sh already installed and seeded: the admin switches to each language
# in turn and tests/lab/lang.js walks every Simplify page. Results land in $LAB_DIR/shots/lang-<locale>/.
#   tests/lab/run-lang.sh <pelican-version> [php] [panel-port] [wings-port] [locales]
#   e.g. tests/lab/run-lang.sh 1.0.0-beta38 php8.3 8001 8090 "nl de ar"
set -Eeuo pipefail
VERSION=$1; PHP=${2:-php8.3}; PORT=${3:-8001}; WINGS=${4:-8090}; LOCALES=${5:-nl de ar}
HERE=$(cd "$(dirname "$0")" && pwd); REPO=$(cd "$HERE/../.." && pwd)
export LAB_DIR=${LAB_DIR:-$HOME/pelican-lab}; DIR=$LAB_DIR/panels/$VERSION
"$HERE/install-plugin.sh" "$DIR" "$REPO" "$PHP"
cd "$DIR"
node "$HERE/fake-wings.js" --port "$WINGS" --servers "$LAB_DIR/servers-$VERSION.json" --delay 100 --log "$LAB_DIR/wings-lang.log" > "$LAB_DIR/wings-lang.out" 2>&1 &
WINGS_PID=$!
PHP_CLI_SERVER_WORKERS=4 $PHP artisan serve --host 127.0.0.1 --port "$PORT" > "$LAB_DIR/serve-lang.log" 2>&1 &
SERVE_PID=$!
setlang() { $PHP -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Models\User::where("email","lab@example.com")->update(["language"=>$argv[1]]);' "$1"; }
trap 'setlang en; kill $WINGS_PID $SERVE_PID 2>/dev/null || true' EXIT
$PHP "$HERE/urls.php" > "$LAB_DIR/urls-$VERSION.json"
for _ in $(seq 1 30); do curl -sf -o /dev/null "http://127.0.0.1:$PORT/login" && break; sleep 1; done
STATUS=0
for LOCALE in $LOCALES; do
  setlang "$LOCALE"
  rm -rf "$LAB_DIR/shots/lang-$LOCALE"
  node "$HERE/lang.js" --base "http://127.0.0.1:$PORT" --urls "$LAB_DIR/urls-$VERSION.json" --out "$LAB_DIR/shots/lang-$LOCALE" --locale "$LOCALE" | grep -v '^PASS' || STATUS=1
done
exit $STATUS
