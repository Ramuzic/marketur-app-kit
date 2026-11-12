#!/bin/bash
# Marketur App Kit: App Store screenshots from the iPhone simulator (6.9 inch, 1320 x 2868).
#   bash scripts/ios-screens.sh customers/<slug> <out dir>
# Run on a Mac after apply-customer.mjs and `npx cap sync ios`.
# Each entry in customer.json "screenshots" is one picture:
#   { "name": "02-home", "url": "https://site/", "wait": 14, "js": "optional code to run on the page",
#     "onboarding": true (shows the first launch screens), "signed_in": true (needs SHOT_USER / SHOT_PASS) }
# Signed-in pictures use a WordPress member made for this (never an admin), from SHOT_USER and SHOT_PASS.
set -e
CDIR="$1"; OUT="${2:-kit-out/screens/ios}"
source kit-out/env.sh
mkdir -p "$OUT"

UDID=$(xcrun simctl list devices available | grep -E "iPhone 1[6-9] Pro Max" | tail -1 | sed -E 's/.*\(([0-9A-F-]{36})\).*/\1/')
if [ -z "$UDID" ]; then UDID=$(xcrun simctl list devices available | grep -E "iPhone.*Pro Max" | tail -1 | sed -E 's/.*\(([0-9A-F-]{36})\).*/\1/'); fi
echo "Simulator $UDID"

xcodebuild -project ios/App/App.xcodeproj -scheme App -configuration Debug \
  -sdk iphonesimulator -destination "id=$UDID" -derivedDataPath build/sim \
  CODE_SIGNING_ALLOWED=NO build | tail -5
APP=build/sim/Build/Products/Debug-iphonesimulator/App.app

xcrun simctl boot "$UDID" || true
xcrun simctl bootstatus "$UDID" -b
xcrun simctl status_bar "$UDID" override --time "9:41" --dataNetwork wifi --wifiMode active --wifiBars 3 --cellularMode active --cellularBars 4 --batteryState charged --batteryLevel 100

fresh() { xcrun simctl uninstall "$UDID" "$KIT_BUNDLE_ID" || true; xcrun simctl install "$UDID" "$APP"; }
shot() {
  name="$1"; wait="$2"; shift 2
  xcrun simctl terminate "$UDID" "$KIT_BUNDLE_ID" || true
  xcrun simctl launch "$UDID" "$KIT_BUNDLE_ID" -NexShot "$@"
  sleep "$wait"
  xcrun simctl io "$UDID" screenshot "$OUT/$name.png"
  echo "Saved $name"
}

# Cookies for signed-in pictures (plain WordPress sign-in).
CK=""
if [ -n "$SHOT_USER" ] && [ -n "$SHOT_PASS" ]; then
  JAR=$(mktemp)
  curl -s -o /dev/null -c "$JAR" -b "wordpress_test_cookie=WP%20Cookie%20check" \
    -A "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MarketurApp/1.0" \
    --data-urlencode "log=$SHOT_USER" --data-urlencode "pwd=$SHOT_PASS" \
    --data "rememberme=forever&testcookie=1" "https://$KIT_HOST/wp-login.php" || true
  CK=$(awk -F'\t' 'NF>=7 && $6 != "" {printf "%s=%s; ", $6, $7}' "$JAR"); rm -f "$JAR"
  echo "$CK" | grep -q "wordpress_logged_in_" && echo "Signed in for member pictures" || { echo "Sign-in did not work, member pictures skipped"; CK=""; }
fi

fresh
node -e '
const c = require(require("path").resolve(process.argv[1], "customer.json"));
for (const s of (c.screenshots || [])) console.log([s.name, s.wait || 14, s.onboarding ? 1 : 0, s.signed_in ? 1 : 0, s.url || "", Buffer.from(s.js || "").toString("base64")].join("\t"));
' "$CDIR" > kit-out/shots.tsv

while IFS=$'\t' read -r NAME WAIT ONB SIGNED URL JS64; do
  ARGS=()
  if [ "$ONB" = "1" ]; then fresh; ARGS+=(-NexShowOnboarding -NexShowGate); fi
  if [ "$SIGNED" = "1" ]; then [ -z "$CK" ] && { echo "Skip $NAME (signed in)"; continue; }; ARGS+=(-NexCookies "$CK"); fi
  [ -n "$URL" ] && ARGS+=(-NexURL "$URL")
  if [ -n "$JS64" ]; then ARGS+=(-NexJS "$(echo "$JS64" | base64 --decode)"); fi
  shot "$NAME" "$WAIT" "${ARGS[@]}"
done < kit-out/shots.tsv

ls -la "$OUT"
