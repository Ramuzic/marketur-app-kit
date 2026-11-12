#!/usr/bin/env node
// Starts a Kit build on Codemagic for one customer.
//   CODEMAGIC_TOKEN=... CODEMAGIC_APP_ID=... node scripts/start-build.mjs <slug> <kit-ios|kit-store|kit-android|kit-android-key|kit-check> [branch]
// CODEMAGIC_TOKEN: Codemagic > Teams > Personal account > Integrations > Codemagic API > Show.
// CODEMAGIC_APP_ID: the id in the app's Codemagic page address.
const [slug, workflow, branch = 'main'] = process.argv.slice(2);
if (!slug || !workflow) { console.error('Usage: node scripts/start-build.mjs <slug> <workflow> [branch]'); process.exit(1); }
const token = process.env.CODEMAGIC_TOKEN, appId = process.env.CODEMAGIC_APP_ID;
if (!token || !appId) { console.error('Set CODEMAGIC_TOKEN and CODEMAGIC_APP_ID'); process.exit(1); }
const r = await fetch('https://api.codemagic.io/builds', {
  method: 'POST',
  headers: { 'x-auth-token': token, 'Content-Type': 'application/json' },
  body: JSON.stringify({ appId, workflowId: workflow, branch, environment: { variables: { CUSTOMER: slug }, groups: ['cust_' + slug] } })
});
const j = await r.json().catch(() => ({}));
if (!r.ok) { console.error('Codemagic said ' + r.status + ': ' + JSON.stringify(j)); process.exit(1); }
console.log('Started ' + workflow + ' for ' + slug + ': https://codemagic.io/app/' + appId + '/build/' + j.buildId);
