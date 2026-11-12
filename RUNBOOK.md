# Marketur App Kit runbook

One repository makes every customer's iPhone and Android app. Each customer is one folder,
`customers/<slug>/`, with `customer.json` and a 1024 x 1024 `icon.png`. Nothing secret is ever
kept in the repository: each customer's keys live in their own Codemagic group, `cust_<slug>`.

The customer's app is published under the customer's own Apple and Google accounts. Apple
rejects apps made from a template when a third party submits them under its own account
(guideline 4.2.6), so this is not optional.

## What the customer does (about 30 minutes, once)

Apple (needs the $99 a year Apple Developer Program, as an organization if it is a business):
1. App Store Connect > Users and Access > Integrations > App Store Connect API > Generate key,
   role **App Manager**. Send us the Issuer ID, the Key ID and the .p8 file through the intake form.
2. App Store Connect > Apps > + > New App: iOS, the app name, bundle id from the intake form
   (it shows up in the list once we have run kit-check), SKU anything.
3. At the end: answer App Privacy (the data questions) and press **Add for Review**.
   We give them the answers to copy in.

Google (needs a $25 Play Console developer account):
1. Play Console > Create app (name, free, app).
2. Google Cloud: make a service account and a JSON key, then Play Console > Users and permissions >
   invite its email with Admin rights for this app. Send the JSON through the intake form.
3. Upload the very first .aab by hand (Test and release > Internal testing > Create release).
   Google does not allow the first upload through the API. After that we upload.
4. Fill in Content rating, Data safety, Target audience, App access, Ads, then send for review.

## What we do

1. Make `customers/<slug>/customer.json` from the intake answers (copy `customers/example`).
   Add `icon.png` (1024 x 1024, no transparency, no rounded corners).
2. Codemagic > Teams > Global variables and secrets > make the group `cust_<slug>` with:
   `APP_STORE_CONNECT_ISSUER_ID`, `APP_STORE_CONNECT_KEY_IDENTIFIER`, `APP_STORE_CONNECT_PRIVATE_KEY`
   (the whole .p8 text), `CERTIFICATE_PRIVATE_KEY` (make one:
   `ssh-keygen -t rsa -b 2048 -m PEM -f cert_key -q -N ""`, paste cert_key, then delete both files),
   and if they have them: `REVIEW_DEMO_PASSWORD`, `SHOT_USER`, `SHOT_PASS`,
   `GCLOUD_SERVICE_ACCOUNT_CREDENTIALS`. Mark every one Secure.
3. Install the **Marketur App Kit** plugin on the customer's WordPress site and run its readiness check
   (Settings > App Kit). It hides the web menus and footer inside the app, adds the delete account
   page Apple asks for, and serves the files that let links open in the app.
4. Run the builds (Codemagic by hand, or `node scripts/start-build.mjs <slug> <workflow>`):
   - `kit-check` first: registers the bundle id in their Apple account. Then the customer does New App.
   - `kit-ios`: registers the bundle id with push and associated domains, makes the signing files,
     builds and uploads to TestFlight. About 15 minutes.
   - wait for Apple to finish processing (an email, 10 to 30 minutes), then
   - `kit-store`: simulator screenshots, Google Play pictures, the whole App Store page (description,
     keywords, subtitle, links, categories, age rating, price Free, review contact and demo account,
     screenshots) and attaches the build. Also fills the Google Play page when its key is there.
   - `kit-android-key` once, put the four values in the group, then `kit-android`.
   - `kit-check` again any time, to see what is left.
5. Look at the screenshots in the kit-store Artifacts. If one is wrong, change `screenshots` in
   customer.json (url, wait, js) and run kit-store again; it replaces the old ones.
6. Tell the customer to do App Privacy and press Add for Review. We never press it for them.

## Cost per customer (Codemagic pay as you go, Mac M2 about $0.095 a minute, Linux cheaper)

- kit-ios about 15 min, kit-store about 20 min, kit-android about 8 min on Linux.
- Roughly $4 to $5 for a first launch, about $1.50 per later iPhone update.

## Builds started from the customer's site (or their AI)

1. On marketur.net: App Kit orders > Build settings. Paste the Codemagic API token and the
   Codemagic app id once. They never leave marketur.net.
2. On the customer's order page: tick "Make a new key", press Update, copy the key and send it
   to the customer privately. They paste it under Settings > App Kit on their site.
3. From then on the site (Settings > App Kit) and any AI connected to it through the Marketur MCP
   plugin can change the app (app_update_settings) and start builds (app_build). Marketur checks
   the plan, the monthly limit (Build settings) and that the key belongs to that website, saves the
   site's latest settings into the order's customer.json, and starts the build. The build machine
   downloads that customer.json and the icon with a signed link that lasts 12 hours.
4. You can also start a build from the order page (admin builds do not count against the limit),
   and edit the customer.json there before a build.

## Updating an app later

Change customer.json (bump `version`), run kit-ios and kit-store (and kit-android). Most changes
need no new build at all: the app shows the live site, so site changes show up straight away.

## Apple review notes that matter

- Guideline 4.2 (minimum functionality): a plain website in a frame gets rejected. The native tab
  bar, onboarding, push, quick actions and offline screen help, but the site itself has to feel like
  an app. The readiness check in the plugin flags the usual problems.
- 5.1.1(v): if people can make an account, they must be able to delete it in the app. The plugin
  adds that page.
- If the site sells digital things (memberships, credits) inside the app, Apple wants in-app
  purchase. Physical goods and services are fine with the site's own checkout.
- If the site offers Google or Facebook sign-in, Sign in with Apple is needed too
  (`features.apple_sign_in: true`).
- 1.2: sites where members post content need reporting and blocking.

## Files

- `scripts/apply-customer.mjs` turns the generic app into the customer's app.
- `scripts/asc.mjs` App Store Connect API (bundle id, build number, store page, check).
- `scripts/play.mjs` Google Play API (store page, upload to Internal testing).
- `scripts/ios-screens.sh` simulator screenshots. `scripts/store_art.py` Play pictures.
- `scripts/start-build.mjs` starts a Codemagic build for a customer.
- `codemagic.yaml` the five workflows.
