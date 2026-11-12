#!/usr/bin/env node
// Marketur App Kit: the App Store Connect side, with the customer's own API key.
// Needs APP_STORE_CONNECT_ISSUER_ID, APP_STORE_CONNECT_KEY_IDENTIFIER, APP_STORE_CONNECT_PRIVATE_KEY.
//
//   node scripts/asc.mjs bundle    customers/<slug>          register the bundle id and its capabilities
//   node scripts/asc.mjs next-build customers/<slug>         prints the next free build number
//   node scripts/asc.mjs listing   customers/<slug> [shots]  fills in the whole store page, uploads screenshots
//   node scripts/asc.mjs check     customers/<slug>          says what is still missing before Submit
//
// It never presses Submit for Review. The customer (or the operator, with the customer's OK) does that.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const [cmd, dir, shotsDir] = process.argv.slice(2);
if (!cmd || !dir) { console.error('Usage: node scripts/asc.mjs <bundle|next-build|listing|check> customers/<slug> [screenshots dir]'); process.exit(1); }
const c = JSON.parse(fs.readFileSync(path.join(dir, 'customer.json'), 'utf8'));
const S = c.store || {};
const API = 'https://api.appstoreconnect.apple.com';

function token() {
  const iss = process.env.APP_STORE_CONNECT_ISSUER_ID, kid = process.env.APP_STORE_CONNECT_KEY_IDENTIFIER;
  let key = process.env.APP_STORE_CONNECT_PRIVATE_KEY || '';
  if (!iss || !kid || !key) throw new Error('Missing APP_STORE_CONNECT_ISSUER_ID / _KEY_IDENTIFIER / _PRIVATE_KEY');
  if (!key.includes('BEGIN')) key = '-----BEGIN PRIVATE KEY-----\n' + key.trim() + '\n-----END PRIVATE KEY-----\n';
  key = key.replace(/\\n/g, '\n');
  const b64 = (o) => Buffer.from(JSON.stringify(o)).toString('base64url');
  const now = Math.floor(Date.now() / 1000);
  const data = b64({ alg: 'ES256', kid, typ: 'JWT' }) + '.' + b64({ iss, iat: now, exp: now + 1100, aud: 'appstoreconnect-v1' });
  const sig = crypto.sign('sha256', Buffer.from(data), { key, dsaEncoding: 'ieee-p1363' }).toString('base64url');
  return data + '.' + sig;
}
let tok = null, tokAt = 0;
async function api(method, url, body) {
  if (!tok || Date.now() - tokAt > 15 * 60 * 1000) { tok = token(); tokAt = Date.now(); }
  const r = await fetch(url.startsWith('http') ? url : API + url, {
    method, headers: { Authorization: 'Bearer ' + tok, 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined
  });
  const text = await r.text();
  let j = null; try { j = text ? JSON.parse(text) : null; } catch (e) { j = { raw: text }; }
  if (!r.ok) {
    const msg = (j && j.errors || []).map((e) => e.detail || e.title).join('; ') || text.slice(0, 300);
    const err = new Error(method + ' ' + url + ' -> ' + r.status + ': ' + msg); err.status = r.status; throw err;
  }
  return j;
}
const done = [], todo = [];
const ok = (s) => { done.push(s); console.log('OK   ' + s); };
const miss = (s) => { todo.push(s); console.log('TODO ' + s); };
async function soft(label, fn) { try { await fn(); ok(label); } catch (e) { miss(label + ' (' + e.message + ')'); } }

async function findApp() {
  const j = await api('GET', '/v1/apps?filter[bundleId]=' + encodeURIComponent(c.bundle_id) + '&limit=1');
  return j.data && j.data[0];
}

async function bundle() {
  let b = (await api('GET', '/v1/bundleIds?filter[identifier]=' + encodeURIComponent(c.bundle_id) + '&limit=5')).data.find((x) => x.attributes.identifier === c.bundle_id);
  if (!b) {
    b = (await api('POST', '/v1/bundleIds', { data: { type: 'bundleIds', attributes: { identifier: c.bundle_id, name: (c.name || c.bundle_id).replace(/[^A-Za-z0-9 ]/g, ' ').slice(0, 60), platform: 'IOS' } } })).data;
    ok('registered the bundle id ' + c.bundle_id);
  } else ok('bundle id ' + c.bundle_id + ' is registered');
  const have = new Set(((await api('GET', '/v1/bundleIds/' + b.id + '/bundleIdCapabilities')).data || []).map((x) => x.attributes.capabilityType));
  const want = [['PUSH_NOTIFICATIONS'], ['ASSOCIATED_DOMAINS']];
  if ((c.features || {}).apple_sign_in) want.push(['APPLE_ID_AUTH', [{ key: 'APPLE_ID_AUTH_APP_CONSENT', options: [{ key: 'PRIMARY_APP_CONSENT' }] }]]);
  for (const [type, settings] of want) {
    if (have.has(type)) { ok(type + ' is on'); continue; }
    await soft('turned on ' + type, () => api('POST', '/v1/bundleIdCapabilities', { data: { type: 'bundleIdCapabilities', attributes: Object.assign({ capabilityType: type }, settings ? { settings } : {}), relationships: { bundleId: { data: { type: 'bundleIds', id: b.id } } } } }));
  }
  const app = await findApp();
  if (!app) miss('App Store Connect has no app for ' + c.bundle_id + ' yet. Apps > + > New App: iOS, name "' + c.name + '", bundle id ' + c.bundle_id + ', SKU ' + (c.slug || 'app') + '-ios');
  else ok('app record found: ' + app.attributes.name + ' (Apple ID ' + app.id + ')');
}

async function nextBuild() {
  const app = await findApp();
  let n = 0;
  if (app) {
    const j = await api('GET', '/v1/builds?filter[app]=' + app.id + '&sort=-uploadedDate&limit=20&fields[builds]=version');
    for (const b of j.data || []) n = Math.max(n, parseInt(b.attributes.version, 10) || 0);
  }
  const fromEnv = parseInt(process.env.PROJECT_BUILD_NUMBER || '0', 10) || 0;
  process.stdout.write(String(Math.max(n + 1, fromEnv, 1)) + '\n');
}

const EDITABLE = ['PREPARE_FOR_SUBMISSION', 'DEVELOPER_REJECTED', 'REJECTED', 'METADATA_REJECTED', 'INVALID_BINARY'];
async function editableVersion(app) {
  const j = await api('GET', '/v1/apps/' + app.id + '/appStoreVersions?filter[platform]=IOS&limit=10');
  let v = (j.data || []).find((x) => EDITABLE.includes(x.attributes.appStoreState || x.attributes.appVersionState));
  const want = c.version || '1.0.0';
  if (!v) {
    v = (await api('POST', '/v1/appStoreVersions', { data: { type: 'appStoreVersions', attributes: { platform: 'IOS', versionString: want }, relationships: { app: { data: { type: 'apps', id: app.id } } } } })).data;
    ok('made version ' + want);
  } else if (v.attributes.versionString !== want) {
    await soft('version number set to ' + want, () => api('PATCH', '/v1/appStoreVersions/' + v.id, { data: { type: 'appStoreVersions', id: v.id, attributes: { versionString: want } } }));
  }
  return v;
}

async function listing() {
  const app = await findApp();
  if (!app) { miss('App Store Connect has no app for ' + c.bundle_id + ' yet (New App is a 2 minute step the account owner does once).'); return; }
  const v = await editableVersion(app);

  // Version page: description, keywords, promo text, links, copyright.
  const locs = (await api('GET', '/v1/appStoreVersions/' + v.id + '/appStoreVersionLocalizations')).data || [];
  let loc = locs.find((l) => l.attributes.locale === 'en-US') || locs[0];
  const vAttrs = {};
  if (S.description) vAttrs.description = S.description.slice(0, 4000);
  if (S.keywords) vAttrs.keywords = S.keywords.slice(0, 100);
  if (S.promo) vAttrs.promotionalText = S.promo.slice(0, 170);
  if (S.support_url) vAttrs.supportUrl = S.support_url;
  if (S.marketing_url) vAttrs.marketingUrl = S.marketing_url;
  if (loc) await soft('description, keywords, promo text and links', () => api('PATCH', '/v1/appStoreVersionLocalizations/' + loc.id, { data: { type: 'appStoreVersionLocalizations', id: loc.id, attributes: vAttrs } }));
  else loc = (await api('POST', '/v1/appStoreVersionLocalizations', { data: { type: 'appStoreVersionLocalizations', attributes: Object.assign({ locale: 'en-US' }, vAttrs), relationships: { appStoreVersion: { data: { type: 'appStoreVersions', id: v.id } } } } })).data;
  if (S.copyright) await soft('copyright', () => api('PATCH', '/v1/appStoreVersions/' + v.id, { data: { type: 'appStoreVersions', id: v.id, attributes: { copyright: S.copyright } } }));

  // App info: categories, subtitle, privacy policy, age rating.
  const infos = (await api('GET', '/v1/apps/' + app.id + '/appInfos')).data || [];
  const info = infos.find((i) => EDITABLE.includes(i.attributes.state || i.attributes.appStoreState)) || infos[0];
  if (info) {
    if (S.category) {
      const rel = { primaryCategory: { data: { type: 'appCategories', id: S.category } } };
      if (S.secondary_category) rel.secondaryCategory = { data: { type: 'appCategories', id: S.secondary_category } };
      await soft('categories ' + S.category + (S.secondary_category ? ' + ' + S.secondary_category : ''), () => api('PATCH', '/v1/appInfos/' + info.id, { data: { type: 'appInfos', id: info.id, relationships: rel } }));
    }
    const il = ((await api('GET', '/v1/appInfos/' + info.id + '/appInfoLocalizations')).data || []).find((l) => l.attributes.locale === 'en-US');
    if (il) {
      const a = {};
      if (S.subtitle) a.subtitle = S.subtitle.slice(0, 30);
      if (S.privacy_url) a.privacyPolicyUrl = S.privacy_url;
      await soft('subtitle and privacy policy link', () => api('PATCH', '/v1/appInfoLocalizations/' + il.id, { data: { type: 'appInfoLocalizations', id: il.id, attributes: a } }));
    }
    await soft('age rating questions', async () => {
      const ar = (await api('GET', '/v1/appInfos/' + info.id + '/ageRatingDeclaration')).data;
      const base = {
        alcoholTobaccoOrDrugUseOrReferences: 'NONE', contests: 'NONE', gamblingSimulated: 'NONE', horrorOrFearThemes: 'NONE',
        matureOrSuggestiveThemes: 'NONE', medicalOrTreatmentInformation: 'NONE', profanityOrCrudeHumor: 'NONE',
        sexualContentGraphicAndNudity: 'NONE', sexualContentOrNudity: 'NONE', violenceCartoonOrFantasy: 'NONE',
        violenceRealistic: 'NONE', violenceRealisticProlongedGraphicOrSadistic: 'NONE', gambling: false, unrestrictedWebAccess: false
      };
      const r = S.age_rating || {};
      for (const k of Object.keys(base)) if (k in r) base[k] = r[k];
      await api('PATCH', '/v1/ageRatingDeclarations/' + ar.id, { data: { type: 'ageRatingDeclarations', id: ar.id, attributes: base } });
      // Questions Apple added in 2025. Sent one by one so an older API version does not block the rest.
      const extra = Object.assign({ messagingAndChat: false, userGeneratedContent: false, advertising: false, parentalControls: false, ageAssurance: false, healthOrWellnessTopics: false, lootBox: false, gunsOrOtherWeapons: 'NONE' }, r);
      for (const k of Object.keys(extra)) {
        if (k in base) continue;
        try { await api('PATCH', '/v1/ageRatingDeclarations/' + ar.id, { data: { type: 'ageRatingDeclarations', id: ar.id, attributes: { [k]: extra[k] } } }); }
        catch (e) { console.log('     (age rating ' + k + ' skipped: ' + e.message.slice(0, 120) + ')'); }
      }
    });
  } else miss('could not find the app info record');

  await soft('content rights (no third party content)', () => api('PATCH', '/v1/apps/' + app.id, { data: { type: 'apps', id: app.id, attributes: { contentRightsDeclaration: S.third_party_content ? 'USES_THIRD_PARTY_CONTENT' : 'DOES_NOT_USE_THIRD_PARTY_CONTENT' } } }));

  // Price: free.
  if (!S.paid) await soft('price set to Free', async () => {
    const pts = await api('GET', '/v1/apps/' + app.id + '/appPricePoints?filter[territory]=USA&limit=200');
    const free = (pts.data || []).find((p) => parseFloat(p.attributes.customerPrice) === 0);
    if (!free) throw new Error('no free price point found');
    await api('POST', '/v1/appPriceSchedules', {
      data: { type: 'appPriceSchedules', relationships: { app: { data: { type: 'apps', id: app.id } }, baseTerritory: { data: { type: 'territories', id: 'USA' } }, manualPrices: { data: [{ type: 'appPrices', id: '${price0}' }] } } },
      included: [{ type: 'appPrices', id: '${price0}', attributes: { startDate: null }, relationships: { appPricePoint: { data: { type: 'appPricePoints', id: free.id } } } }]
    });
  });

  // App Review contact and demo account. The demo password comes from REVIEW_DEMO_PASSWORD, never the file.
  const R = S.review || {};
  if (R.email) await soft('App Review contact' + (R.demo_user ? ' and demo account' : ''), async () => {
    const attrs = { contactFirstName: R.first_name || '', contactLastName: R.last_name || '', contactPhone: R.phone || '', contactEmail: R.email, notes: (R.notes || '').slice(0, 4000), demoAccountRequired: !!R.demo_user };
    if (R.demo_user) { attrs.demoAccountName = R.demo_user; attrs.demoAccountPassword = process.env.REVIEW_DEMO_PASSWORD || ''; }
    let rd = null;
    try { rd = (await api('GET', '/v1/appStoreVersions/' + v.id + '/appStoreReviewDetail')).data; } catch (e) { rd = null; }
    if (rd) await api('PATCH', '/v1/appStoreReviewDetails/' + rd.id, { data: { type: 'appStoreReviewDetails', id: rd.id, attributes: attrs } });
    else await api('POST', '/v1/appStoreReviewDetails', { data: { type: 'appStoreReviewDetails', attributes: attrs, relationships: { appStoreVersion: { data: { type: 'appStoreVersions', id: v.id } } } } });
  });

  // Screenshots (6.9 inch slot; Apple scales them down for smaller iPhones).
  if (shotsDir && fs.existsSync(shotsDir)) await soft('screenshots uploaded', () => uploadShots(loc.id, shotsDir));
  else miss('screenshots (no folder given)');

  // Attach the newest processed build, if there is one.
  await soft('newest build attached to the version', async () => {
    const b = (await api('GET', '/v1/builds?filter[app]=' + app.id + '&filter[processingState]=VALID&sort=-uploadedDate&limit=1')).data[0];
    if (!b) throw new Error('no processed build yet, run the iPhone build first');
    await api('PATCH', '/v1/appStoreVersions/' + v.id + '/relationships/build', { data: { type: 'builds', id: b.id } });
  });
}

async function uploadShots(locId, folder) {
  const files = fs.readdirSync(folder).filter((f) => /\.png$/i.test(f)).sort().slice(0, 10);
  if (!files.length) throw new Error('no PNG files in ' + folder);
  const sets = (await api('GET', '/v1/appStoreVersionLocalizations/' + locId + '/appScreenshotSets')).data || [];
  let set = sets.find((s) => s.attributes.screenshotDisplayType === 'APP_IPHONE_67');
  if (!set) set = (await api('POST', '/v1/appScreenshotSets', { data: { type: 'appScreenshotSets', attributes: { screenshotDisplayType: 'APP_IPHONE_67' }, relationships: { appStoreVersionLocalization: { data: { type: 'appStoreVersionLocalizations', id: locId } } } } })).data;
  // Start clean so a second run does not pile up copies.
  for (const s of (await api('GET', '/v1/appScreenshotSets/' + set.id + '/appScreenshots')).data || []) await api('DELETE', '/v1/appScreenshots/' + s.id);
  for (const f of files) {
    const buf = fs.readFileSync(path.join(folder, f));
    const shot = (await api('POST', '/v1/appScreenshots', { data: { type: 'appScreenshots', attributes: { fileName: f, fileSize: buf.length }, relationships: { appScreenshotSet: { data: { type: 'appScreenshotSets', id: set.id } } } } })).data;
    for (const op of shot.attributes.uploadOperations || []) {
      const headers = {}; for (const h of op.requestHeaders || []) headers[h.name] = h.value;
      const r = await fetch(op.url, { method: op.method, headers, body: buf.subarray(op.offset, op.offset + op.length) });
      if (!r.ok) throw new Error('upload of ' + f + ' failed: ' + r.status);
    }
    await api('PATCH', '/v1/appScreenshots/' + shot.id, { data: { type: 'appScreenshots', id: shot.id, attributes: { uploaded: true, sourceFileChecksum: crypto.createHash('md5').update(buf).digest('hex') } } });
    console.log('     uploaded ' + f);
  }
}

async function check() {
  const app = await findApp();
  if (!app) { miss('New App in App Store Connect'); return; }
  ok('app record ' + app.attributes.name);
  const v = (await api('GET', '/v1/apps/' + app.id + '/appStoreVersions?filter[platform]=IOS&limit=5')).data[0];
  if (v) ok('version ' + v.attributes.versionString + ' is ' + (v.attributes.appStoreState || v.attributes.appVersionState));
  const b = (await api('GET', '/v1/builds?filter[app]=' + app.id + '&sort=-uploadedDate&limit=1')).data[0];
  if (b) ok('newest build ' + b.attributes.version + ' (' + b.attributes.processingState + ')'); else miss('no build uploaded yet');
}

const run = { bundle, 'next-build': nextBuild, listing, check }[cmd];
if (!run) { console.error('Unknown command ' + cmd); process.exit(1); }
try {
  await run();
} catch (e) { console.error('FAILED ' + e.message); process.exit(1); }
if (cmd !== 'next-build') {
  console.log('\nStill for the account owner to do by hand in App Store Connect:');
  console.log(' - App Privacy (the data questions; Apple has no API for it). Use the answers in kit-out/app-privacy.txt');
  console.log(' - Pricing and Availability: check the countries');
  console.log(' - Press Add for Review / Submit');
  if (todo.length) { console.log('\nNot done by this run:'); for (const t of todo) console.log(' - ' + t); }
  fs.mkdirSync('kit-out', { recursive: true });
  fs.writeFileSync('kit-out/asc-' + cmd + '.json', JSON.stringify({ done, todo }, null, 2));
}
