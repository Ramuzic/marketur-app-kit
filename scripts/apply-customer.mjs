#!/usr/bin/env node
// Marketur App Kit: turns the generic app into one customer's app.
//   node scripts/apply-customer.mjs customers/<slug>
// Reads customers/<slug>/customer.json (and icon.png) and rewrites every file that
// carries the app name, bundle id, site, colors, tabs, quick actions and store text.
// Safe to run more than once: it always writes from the customer file, not from the last run.
import fs from 'node:fs';
import path from 'node:path';

const dir = process.argv[2];
if (!dir) { console.error('Usage: node scripts/apply-customer.mjs customers/<slug>'); process.exit(1); }
const c = JSON.parse(fs.readFileSync(path.join(dir, 'customer.json'), 'utf8'));
const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const P = (...a) => path.join(root, ...a);
const read = (f) => fs.readFileSync(P(f), 'utf8');
const write = (f, s) => { fs.mkdirSync(path.dirname(P(f)), { recursive: true }); fs.writeFileSync(P(f), s); console.log('wrote ' + f); };
const xml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, "\\'");
const plist = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const html = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// ---- check the customer file -------------------------------------------------------------
const errors = [];
const need = (k, v) => { if (v === undefined || v === null || v === '') errors.push('missing ' + k); };
need('name', c.name); need('bundle_id', c.bundle_id); need('site', c.site);
if (c.bundle_id && !/^[a-zA-Z][a-zA-Z0-9-]*(\.[a-zA-Z0-9-]+)+$/.test(c.bundle_id)) errors.push('bundle_id looks wrong: ' + c.bundle_id);
const pkg = c.android_package || c.bundle_id;
if (pkg && !/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/.test(pkg)) errors.push('android_package must be lower case letters, digits, _ and dots: ' + pkg);
if (c.name && c.name.length > 30) errors.push('name is longer than 30 characters (App Store limit)');
const site = new URL(c.site);
const host = site.hostname.replace(/^www\./, '');
const start = c.start || c.site.replace(/\/?$/, '/');
const version = c.version || '1.0.0';
if (!/^\d+\.\d+\.\d+$/.test(version)) errors.push('version must look like 1.0.0');
const ownHost = (u) => { try { const h = new URL(u).hostname.replace(/^www\./, ''); return h === host || h.endsWith('.' + host); } catch (e) { return false; } };
for (const t of c.tabs || []) if (!ownHost(t.url)) errors.push('tab ' + t.label + ' points off the site: ' + t.url);
if ((c.tabs || []).length > 5) errors.push('5 tabs at most');
if (errors.length) { console.error('customer.json problems:\n - ' + errors.join('\n - ')); process.exit(1); }

const colors = Object.assign({ top: '#FFFFFF', bar: '#FFFFFF', active: '#2563EB', inactive: '#9CA3AF', paper: '#FFFFFF', ink: '#111111', muted: '#6B7280' }, c.colors || {});
const short = c.short_name || c.name;
const uaTag = (c.slug || 'kit').replace(/[^a-z0-9]/gi, '') + 'App';
const dark = (hex) => { const v = parseInt(hex.replace('#', ''), 16); const r = (v >> 16) & 255, g = (v >> 8) & 255, b = v & 255; return (0.299 * r + 0.587 * g + 0.114 * b) < 140; };

// ---- web settings (copied into both apps by cap sync) ------------------------------------------
write('capacitor.config.json', JSON.stringify({
  appId: c.bundle_id,
  appName: short,
  webDir: 'www',
  server: { url: start, cleartext: false, allowNavigation: [host, '*.' + host].concat(c.allow_navigation || []), errorPath: 'offline.html' },
  // Every app also says MarketurApp, so one site plugin can spot any Kit app.
  appendUserAgent: uaTag + '/' + version + ' MarketurApp/1.0',
  backgroundColor: colors.paper,
  ios: { contentInset: 'never', scrollEnabled: true },
  android: { allowMixedContent: false },
  plugins: {
    SystemBars: { insetsHandling: 'disable', style: dark(colors.top) ? 'DARK' : 'LIGHT' },
    SplashScreen: { launchShowDuration: 1200, launchAutoHide: true, backgroundColor: colors.paper, showSpinner: false, androidScaleType: 'CENTER_CROP' }
  }
}, null, 2) + '\n');

const tabs = (c.tabs && c.tabs.length ? c.tabs : [{ label: 'Home', url: start, icon: 'home', match: ['/'] }]);
write('www/app.json', JSON.stringify({
  version, start, host,
  // Live settings from the App Kit plugin on the site (tabs, colors, bar style), read each time the app opens.
  config_url: c.config_url || 'https://' + host + '/wp-json/mak/v1/app-config',
  nav: ['floating', 'fixed', 'none'].includes(c.nav) ? c.nav : 'floating',
  onesignal_app_id: c.onesignal_app_id || '',
  colors, tabs,
  onboarding: c.onboarding || [],
  offline: Object.assign({ title: "You're offline", text: 'Check your connection and try again.', button: 'Try again' }, c.offline || {})
}, null, 2) + '\n');

write('www/index.html', `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${html(short)}</title>
<style>
  html, body { margin: 0; height: 100%; background: ${colors.paper}; color: ${colors.ink}; font-family: -apple-system, system-ui, Roboto, sans-serif; }
  .wrap { height: 100%; display: flex; align-items: center; justify-content: center; }
  .mark { font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
</style>
</head>
<body>
<div class="wrap"><div class="mark">${html(short)}</div></div>
<script>location.replace(${JSON.stringify(start)});</script>
</body>
</html>
`);

write('www/offline.html', `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${html(short)}</title>
<style>
  :root { --paper: ${colors.paper}; --ink: ${colors.ink}; --muted: ${colors.muted}; --sign: ${colors.active}; }
  html, body { margin: 0; height: 100%; background: var(--paper); color: var(--ink); font-family: -apple-system, system-ui, Roboto, sans-serif; }
  .wrap { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; text-align: center; }
  h1 { font-size: 22px; margin: 0 0 8px; }
  p { color: var(--muted); margin: 0 0 22px; }
  button { background: var(--sign); color: #fff; border: 0; border-radius: 999px; padding: 12px 26px; font-size: 16px; font-weight: 700; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Can't reach ${html(short)}</h1>
  <p>Check your connection and try again.</p>
  <button onclick="retry()">Try again</button>
</div>
<script>
  function retry() { location.href = ${JSON.stringify(start)}; }
</script>
</body>
</html>
`);

// ---- iPhone ------------------------------------------------------------------------------
let pbx = read('ios/App/App.xcodeproj/project.pbxproj');
pbx = pbx.replace(/PRODUCT_BUNDLE_IDENTIFIER = [^;]+;/g, 'PRODUCT_BUNDLE_IDENTIFIER = ' + c.bundle_id + ';');
pbx = pbx.replace(/MARKETING_VERSION = [^;]+;/g, 'MARKETING_VERSION = ' + version + ';');
pbx = pbx.replace(/\n\t+DEVELOPMENT_TEAM = [^;]+;/g, '');
if (c.apple_team_id) pbx = pbx.replace(/(\n(\t+)CODE_SIGN_STYLE = [^;]+;)/g, '$1\n$2DEVELOPMENT_TEAM = ' + c.apple_team_id + ';');
write('ios/App/App.xcodeproj/project.pbxproj', pbx);

const f = c.features || {};
let ent = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>aps-environment</key>
	<string>development</string>
	<key>com.apple.developer.associated-domains</key>
	<array>
		<string>applinks:${host}</string>
		<string>webcredentials:${host}</string>
	</array>
`;
if (f.apple_sign_in) ent += `	<key>com.apple.developer.applesignin</key>
	<array>
		<string>Default</string>
	</array>
`;
ent += `</dict>
</plist>
`;
write('ios/App/App/App.entitlements', ent);

let info = read('ios/App/App/Info.plist');
info = info.replace(/(<key>CFBundleDisplayName<\/key>\s*<string>)[^<]*(<\/string>)/, '$1' + plist(short) + '$2');
const symbolFor = { home: 'house', shop: 'bag', cart: 'cart', account: 'person.crop.circle', search: 'magnifyingglass', heart: 'heart', star: 'star', map: 'mappin', phone: 'phone', calendar: 'calendar', news: 'newspaper', play: 'play.circle', grid: 'square.grid.2x2', info: 'info.circle', bell: 'bell', chat: 'bubble.left', bag: 'bag' };
const sc = (c.shortcuts || []).slice(0, 4);
const scXml = sc.length ? `	<key>UIApplicationShortcutItems</key>
	<array>
${sc.map((s, i) => `		<dict>
			<key>UIApplicationShortcutItemType</key>
			<string>${plist(c.bundle_id)}.shortcut${i + 1}</string>
			<key>UIApplicationShortcutItemTitle</key>
			<string>${plist(s.label)}</string>
			<key>UIApplicationShortcutItemIconSymbolName</key>
			<string>${symbolFor[s.icon] || 'star'}</string>
			<key>UIApplicationShortcutItemUserInfo</key>
			<dict>
				<key>url</key>
				<string>${plist(s.url)}</string>
			</dict>
		</dict>`).join('\n')}
	</array>
` : '';
info = info.replace(/\t<key>UIApplicationShortcutItems<\/key>\s*<array>[\s\S]*?\n\t<\/array>\n/, '');
info = info.replace(/(\t<key>OneSignal_disable_badge_clearing<\/key>)/, scXml + '$1');
const cam = f.camera_text || short + ' uses the camera so you can take photos and videos to add to your posts, messages and profile.';
const mic = f.mic_text || short + ' uses the microphone to record sound for the videos you add.';
info = info.replace(/(<key>NSCameraUsageDescription<\/key>\s*<string>)[^<]*(<\/string>)/, '$1' + plist(cam) + '$2');
info = info.replace(/(<key>NSMicrophoneUsageDescription<\/key>\s*<string>)[^<]*(<\/string>)/, '$1' + plist(mic) + '$2');
if (!/NSPhotoLibraryAddUsageDescription/.test(info)) info = info.replace(/(\t<key>NSMicrophoneUsageDescription<\/key>)/, '\t<key>NSPhotoLibraryAddUsageDescription</key>\n\t<string>' + plist(short + ' saves the photos and videos you choose to download to your library.') + '</string>\n$1');
info = info.replace(/(<key>UIStatusBarStyle<\/key>\s*<string>)[^<]*(<\/string>)/, '$1' + (dark(colors.top) ? 'UIStatusBarStyleLightContent' : 'UIStatusBarStyleDarkContent') + '$2');
write('ios/App/App/Info.plist', info);

// ---- Android -----------------------------------------------------------------------------
let gradle = read('android/app/build.gradle');
gradle = gradle.replace(/applicationId "[^"]+"/, 'applicationId "' + pkg + '"');
gradle = gradle.replace(/versionName "[^"]+"/, 'versionName "' + version + '"');
write('android/app/build.gradle', gradle);

write('android/app/src/main/res/values/strings.xml', `<?xml version='1.0' encoding='utf-8'?>
<resources>
    <string name="app_name">${xml(short)}</string>
    <string name="title_activity_main">${xml(short)}</string>
    <string name="package_name">${pkg}</string>
    <string name="custom_url_scheme">${pkg}</string>
${sc.map((s, i) => `    <string name="sc_${i + 1}">${xml(s.label)}</string>`).join('\n')}
</resources>
`);

const tabIcons = new Set(['home', 'next', 'reels', 'community', 'chat', 'share', 'bell', 'shop', 'cart', 'account', 'search', 'heart', 'star', 'map', 'phone', 'menu', 'calendar', 'news', 'play', 'grid', 'info']);
write('android/app/src/main/res/xml/shortcuts.xml', `<?xml version="1.0" encoding="utf-8"?>
<shortcuts xmlns:android="http://schemas.android.com/apk/res/android">
${sc.map((s, i) => `    <shortcut
        android:shortcutId="s${i + 1}"
        android:enabled="true"
        android:icon="@drawable/ic_tab_${tabIcons.has(s.icon) ? s.icon : 'star'}"
        android:shortcutShortLabel="@string/sc_${i + 1}"
        android:shortcutLongLabel="@string/sc_${i + 1}">
        <intent
            android:action="android.intent.action.VIEW"
            android:targetPackage="${pkg}"
            android:targetClass="app.marketur.shell.MainActivity"
            android:data="${html(s.url)}" />
    </shortcut>`).join('\n')}
</shortcuts>
`);

let man = read('android/app/src/main/AndroidManifest.xml');
man = man.replace(/<!-- [^>]*links open in the app[^>]*-->/, '<!-- Links to the site open in the app (verified with /.well-known/assetlinks.json on the site). -->');
man = man.replace(/(<category android:name="android.intent.category.BROWSABLE" \/>\s*)(<data android:scheme="https" android:host="[^"]+" \/>\s*)+/, '$1<data android:scheme="https" android:host="' + host + '" />\n                <data android:scheme="https" android:host="www.' + host + '" />\n            ');
man = man.replace(/<!-- Press and hold the app icon[^>]*-->/, '<!-- Press and hold the app icon: the quick actions from customer.json. -->');
write('android/app/src/main/AndroidManifest.xml', man);

// ---- icons ---------------------------------------------------------------------------------
let pj = JSON.parse(read('package.json'));
const bg = c.icon_background || colors.paper;
pj.name = 'app-' + (c.slug || 'kit');
pj.version = version;
pj.description = short + ' app (Marketur App Kit)';
pj.scripts.icons = `npx capacitor-assets generate --iconBackgroundColor '${bg}' --iconBackgroundColorDark '${bg}' --splashBackgroundColor '${colors.paper}' --splashBackgroundColorDark '${colors.paper}'`;
write('package.json', JSON.stringify(pj, null, 2) + '\n');
const icon = path.join(dir, 'icon.png');
if (fs.existsSync(icon)) {
  fs.mkdirSync(P('resources'), { recursive: true });
  // Android adaptive icon layers and the launch screens, so nothing of the old app is left.
  let sharp = null;
  try { sharp = (await import('sharp')).default; } catch (e) { console.warn('sharp is not installed yet (npm ci first): icon and splash left as they were'); }
  if (sharp) {
    for (const n of fs.readdirSync(P('resources'))) fs.rmSync(P('resources', n));
    // Re-encoded as PNG with no transparency (the site icon may be a JPEG; Apple rejects icons with alpha).
    await sharp(icon).resize(1024, 1024, { fit: 'cover' }).flatten({ background: c.icon_background || colors.paper }).png().toFile(P('resources', 'icon-only.png'));
    console.log('copied icon.png to resources/icon-only.png (run npm run icons next)');
    const src = sharp(icon).resize(1024, 1024, { fit: 'cover' });
    const buf = await src.png().toBuffer();
    const { data } = await sharp(buf).extract({ left: 4, top: 4, width: 1, height: 1 }).raw().toBuffer({ resolveWithObject: true });
    const corner = c.icon_background || ('#' + [data[0], data[1], data[2]].map((v) => v.toString(16).padStart(2, '0')).join(''));
    const inner = await sharp(buf).resize(700, 700).png().toBuffer();
    await sharp({ create: { width: 1024, height: 1024, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } }).composite([{ input: inner, gravity: 'center' }]).png().toFile(P('resources', 'icon-foreground.png'));
    await sharp({ create: { width: 1024, height: 1024, channels: 4, background: corner } }).png().toFile(P('resources', 'icon-background.png'));
    const mark = await sharp(buf).resize(560, 560).composite([{ input: Buffer.from('<svg width="560" height="560"><rect width="560" height="560" rx="124" ry="124"/></svg>'), blend: 'dest-in' }]).png().toBuffer();
    for (const [n, bgc] of [['splash.png', colors.paper], ['splash-dark.png', c.splash_dark || colors.paper]]) {
      await sharp({ create: { width: 2732, height: 2732, channels: 4, background: bgc } }).composite([{ input: mark, gravity: 'center' }]).png().toFile(P('resources', n));
    }
    console.log('made the adaptive icon layers and launch screens (background ' + corner + ')');
  }
} else {
  console.warn('No icon.png in ' + dir + ': the app keeps the old icon. Add a 1024 x 1024 PNG.');
}

// Handy for the build scripts.
write('kit-out/env.sh', [
  'export KIT_BUNDLE_ID=' + JSON.stringify(c.bundle_id),
  'export KIT_PACKAGE=' + JSON.stringify(pkg),
  'export KIT_NAME=' + JSON.stringify(c.name),
  'export KIT_HOST=' + JSON.stringify(host),
  'export KIT_VERSION=' + JSON.stringify(version),
  'export KIT_APPLE_SIGN_IN=' + (f.apple_sign_in ? '1' : '0'),
  ''
].join('\n'));
console.log('\nDone: ' + c.name + ' (' + c.bundle_id + ', ' + host + ', ' + version + ')');
