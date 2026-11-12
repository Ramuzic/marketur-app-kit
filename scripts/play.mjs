#!/usr/bin/env node
// Marketur App Kit: the Google Play side, with the customer's own service account
// (Play Console > Users and permissions: invite the service account email as Admin for this app).
// Needs GCLOUD_SERVICE_ACCOUNT_CREDENTIALS (the JSON key, as text).
//
//   node scripts/play.mjs listing customers/<slug> <art dir>   title, descriptions, contact, icon, feature graphic, phone screenshots
//   node scripts/play.mjs upload  customers/<slug> <file.aab>  sends a build to the Internal testing track as a draft
//
// Google only accepts API uploads after the first .aab was uploaded by hand in Play Console.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const [cmd, dir, arg] = process.argv.slice(2);
if (!cmd || !dir) { console.error('Usage: node scripts/play.mjs <listing|upload> customers/<slug> <dir or .aab>'); process.exit(1); }
const c = JSON.parse(fs.readFileSync(path.join(dir, 'customer.json'), 'utf8'));
const S = c.store || {}, G = S.play || {};
const pkg = c.android_package || c.bundle_id;
const BASE = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/' + pkg;
const UP = 'https://androidpublisher.googleapis.com/upload/androidpublisher/v3/applications/' + pkg;

async function accessToken() {
  const raw = process.env.GCLOUD_SERVICE_ACCOUNT_CREDENTIALS;
  if (!raw) throw new Error('Missing GCLOUD_SERVICE_ACCOUNT_CREDENTIALS');
  const sa = JSON.parse(raw);
  const b64 = (o) => Buffer.from(JSON.stringify(o)).toString('base64url');
  const now = Math.floor(Date.now() / 1000);
  const data = b64({ alg: 'RS256', typ: 'JWT' }) + '.' + b64({ iss: sa.client_email, scope: 'https://www.googleapis.com/auth/androidpublisher', aud: 'https://oauth2.googleapis.com/token', iat: now, exp: now + 3500 });
  const sig = crypto.sign('sha256', Buffer.from(data), sa.private_key).toString('base64url');
  const r = await fetch('https://oauth2.googleapis.com/token', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer&assertion=' + data + '.' + sig });
  const j = await r.json();
  if (!j.access_token) throw new Error('Google sign-in for the service account failed: ' + JSON.stringify(j).slice(0, 300));
  return j.access_token;
}
let TOKEN;
async function call(method, url, body, type) {
  const r = await fetch(url, { method, headers: { Authorization: 'Bearer ' + TOKEN, 'Content-Type': type || 'application/json' }, body: body === undefined ? undefined : (type ? body : JSON.stringify(body)) });
  const text = await r.text();
  let j = null; try { j = text ? JSON.parse(text) : null; } catch (e) { j = { raw: text }; }
  if (!r.ok) { const e = new Error(method + ' ' + url.replace(BASE, '').replace(UP, '') + ' -> ' + r.status + ': ' + ((j && j.error && j.error.message) || text.slice(0, 300))); e.status = r.status; throw e; }
  return j;
}

async function withEdit(fn) {
  TOKEN = await accessToken();
  let edit;
  try { edit = await call('POST', BASE + '/edits', {}); }
  catch (e) {
    if (e.status === 404) throw new Error('Google Play has no app ' + pkg + ' yet, or the service account cannot see it. Create the app in Play Console and invite the service account first.');
    throw e;
  }
  await fn(edit.id);
  await call('POST', BASE + '/edits/' + edit.id + ':commit');
  console.log('OK   saved (committed) on Google Play');
}

async function listing() {
  const art = arg;
  await withEdit(async (id) => {
    const full = (G.full_description || S.description || '').slice(0, 4000);
    await call('PUT', BASE + '/edits/' + id + '/listings/en-US', { language: 'en-US', title: (G.title || c.name).slice(0, 30), shortDescription: (G.short_description || S.subtitle || '').slice(0, 80), fullDescription: full });
    console.log('OK   title, short and full description');
    const d = { defaultLanguage: 'en-US' };
    if (G.contact_email || (S.review || {}).email) d.contactEmail = G.contact_email || S.review.email;
    if (G.contact_website || S.marketing_url) d.contactWebsite = G.contact_website || S.marketing_url;
    if (G.contact_phone) d.contactPhone = G.contact_phone;
    await call('PATCH', BASE + '/edits/' + id + '/details', d);
    console.log('OK   contact details');
    if (!art || !fs.existsSync(art)) { console.log('TODO images (no folder given)'); return; }
    const one = async (type, file) => {
      if (!fs.existsSync(file)) { console.log('TODO ' + type + ' (' + file + ' missing)'); return; }
      await call('DELETE', BASE + '/edits/' + id + '/listings/en-US/' + type);
      await call('POST', UP + '/edits/' + id + '/listings/en-US/' + type + '?uploadType=media', fs.readFileSync(file), 'image/png');
      console.log('OK   ' + type);
    };
    await one('icon', path.join(art, 'play-icon-512.png'));
    await one('featureGraphic', path.join(art, 'play-feature-1024x500.png'));
    const phone = path.join(art, 'android');
    if (fs.existsSync(phone)) {
      await call('DELETE', BASE + '/edits/' + id + '/listings/en-US/phoneScreenshots');
      for (const f of fs.readdirSync(phone).filter((x) => /\.png$/i.test(x)).sort().slice(0, 8)) {
        await call('POST', UP + '/edits/' + id + '/listings/en-US/phoneScreenshots?uploadType=media', fs.readFileSync(path.join(phone, f)), 'image/png');
        console.log('     uploaded ' + f);
      }
      console.log('OK   phone screenshots');
    }
  });
  console.log('\nStill for the account owner in Play Console: Content rating questionnaire, Data safety form, Target audience,');
  console.log('App access (the demo account), Ads declaration, and the first production release review.');
}

async function upload() {
  if (!arg || !fs.existsSync(arg)) throw new Error('No .aab file at ' + arg);
  await withEdit(async (id) => {
    let b;
    try { b = await call('POST', UP + '/edits/' + id + '/bundles?uploadType=media', fs.readFileSync(arg), 'application/octet-stream'); }
    catch (e) { throw new Error('Upload refused. If this is the very first build, upload the .aab by hand once in Play Console (Test and release > Internal testing). ' + e.message); }
    console.log('OK   uploaded build ' + b.versionCode);
    await call('PUT', BASE + '/edits/' + id + '/tracks/internal', { track: 'internal', releases: [{ name: (c.version || '1.0.0') + ' (' + b.versionCode + ')', versionCodes: [String(b.versionCode)], status: 'draft' }] });
    console.log('OK   Internal testing draft release');
  });
}

try {
  if (cmd === 'listing') await listing();
  else if (cmd === 'upload') await upload();
  else throw new Error('Unknown command ' + cmd);
} catch (e) { console.error('FAILED ' + e.message); process.exit(1); }
