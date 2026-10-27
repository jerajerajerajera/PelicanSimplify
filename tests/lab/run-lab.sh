#!/usr/bin/env bash
# The lab round for one Pelican release: a real panel with the plugin from this checkout, a fake Wings with twelve
# servers, the CI smoke test, the overview benchmark, the Playwright walk over every Simplify page (desktop, two
# phones portrait and landscape, light and dark), the walk over what 0.6.0 adds and the speed walk. Results land in
# $LAB_DIR/shots/<version>-<php>/, <version>-<php>-060/ and <version>-<php>-perf/.
#   tests/lab/run-lab.sh <pelican-version> [php] [panel-port] [wings-port]
#   e.g. tests/lab/run-lab.sh 1.0.0-rc.2 php8.4 8003 8092
# Needs: git, composer at /usr/local/bin/composer, the PHP build, node with playwright (PW_CHROMIUM for a system
# Chromium), sqlite. Set LAB_DIR to keep the panels somewhere else than ~/pelican-lab.
set -Eeuo pipefail
# LAB_PARTS picks the steps (default all): smoke bench walk 060 perf. Runs side by side skew the timings, so run perf alone.
PARTS=" ${LAB_PARTS:-smoke bench walk 060 perf} "; part() { [[ $PARTS == *" $1 "* ]]; }
VERSION=$1; PHP=${2:-php8.3}; PORT=${3:-8001}; WINGS=${4:-8090}
HERE=$(cd "$(dirname "$0")" && pwd); REPO=$(cd "$HERE/../.." && pwd)
export LAB_DIR=${LAB_DIR:-$HOME/pelican-lab}; DIR=$LAB_DIR/panels/$VERSION; TAG=$VERSION-${PHP//php/php}; OUT=$LAB_DIR/shots/$TAG
mkdir -p "$LAB_DIR/shots"
[[ -f $DIR/vendor/autoload.php && -f $DIR/.env ]] || "$HERE/setup-panel.sh" "$VERSION" "$PHP" "$PORT"
sed -i "s#^APP_URL=.*#APP_URL=http://127.0.0.1:$PORT#" "$DIR/.env"
"$HERE/install-plugin.sh" "$DIR" "$REPO" "$PHP"
cd "$DIR"
PHP_CLI_SERVER_WORKERS=4 $PHP artisan serve --host 127.0.0.1 --port "$PORT" > "$LAB_DIR/serve-$TAG.log" 2>&1 &
SERVE_PID=$!; WINGS_PID=
trap 'kill $WINGS_PID $SERVE_PID 2>/dev/null || true' EXIT
if ! $PHP -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(App\Models\Node::where("name","Lab node")->exists()?0:1);'; then
  $PHP "$HERE/seed.php" "$WINGS" > "$LAB_DIR/servers-$VERSION.json"
fi
# The node always points at this run's fake Wings, also when the panel was seeded with another port before.
$PHP -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Models\Node::where("name","Lab node")->update(["daemon_listen"=>(int)$argv[1],"daemon_connect"=>(int)$argv[1]]);' "$WINGS"
[[ -s $LAB_DIR/servers-$VERSION.json ]] || $PHP -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(App\Models\Server::query()->get(["uuid","name"])->map(fn($s)=>["uuid"=>$s->uuid,"name"=>$s->name])->values()), "\n";' > "$LAB_DIR/servers-$VERSION.json"
$PHP "$HERE/urls.php" > "$LAB_DIR/urls-$VERSION.json"
# 1. CI smoke test, then its leftovers removed (a second node would switch host mode on by itself). It runs before the
# fake Wings starts, like in CI: its in-game step expects every server to be unreachable.
if part smoke; then $PHP plugins/pelican-simplify/tests/panel/smoke.php | tail -1 || echo "SMOKE TEST FAILED"; fi
$PHP "$HERE/cleanup.php"
# Fake Wings (re-reads the server list on every request).
node "$HERE/fake-wings.js" --port "$WINGS" --servers "$LAB_DIR/servers-$VERSION.json" --delay 100 --log "$LAB_DIR/wings-$VERSION.log" > "$LAB_DIR/wings-$VERSION.out" 2>&1 &
WINGS_PID=$!
# 2. Overview benchmark: cold rounds, Wings requests per round, a warm call.
if part bench; then $PHP "$HERE/bench.php" "$LAB_DIR/wings-$VERSION.log" 3 | tee "$LAB_DIR/bench-$TAG.json"; fi
# 3. Browser walk.
for _ in $(seq 1 30); do curl -sf -o /dev/null "http://127.0.0.1:$PORT/login" && break; sleep 1; done
STATUS=0
part walk && { rm -rf "$OUT"; node "$HERE/lab.js" --base "http://127.0.0.1:$PORT" --urls "$LAB_DIR/urls-$VERSION.json" --out "$OUT" | tail -40 || STATUS=1; }
# 4. What 0.6.0 adds: settings, Server Maker, installation view, notifications, host mode and more (lab-060.js).
part 060 && { rm -rf "$OUT-060"; node "$HERE/lab-060.js" --base "http://127.0.0.1:$PORT" --urls "$LAB_DIR/urls-$VERSION.json" --servers "$LAB_DIR/servers-$VERSION.json" \
  --panel "$DIR" --php "$PHP" --out "$OUT-060" | tail -60 || STATUS=1; }
# 5. Speed per page, cold and warm, with the assets (perf.js).
part perf && { rm -rf "$OUT-perf"; node "$HERE/perf.js" --base "http://127.0.0.1:$PORT" --urls "$LAB_DIR/urls-$VERSION.json" --out "$OUT-perf" | tail -30 || STATUS=1; }
exit $STATUS
