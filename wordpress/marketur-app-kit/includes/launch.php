<?php
/**
 * Launch page (App Kit 1.4.0).
 *
 * Every step from "I have a website" to "my app is in the App Store and Google Play", in order,
 * on one page in the customer's dashboard. Each step says exactly where to click on Apple's
 * or Google's site and what to type, shows whether it is done, and has its button or box right
 * there. Keys pasted here go to Marketur, which sends them straight on to the build service for
 * this app only and does not keep them. The assistant reads the same status and talks people
 * through it.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Launch {

    const FLAGS = 'mak_launch_flags';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
        add_action( 'wp_ajax_mak_launch', [ __CLASS__, 'ajax' ] );
    }

    public static function menu() {
        add_submenu_page( 'mak-assistant', 'Launch your app', 'Launch', 'manage_options', 'mak-launch', [ __CLASS__, 'page' ] );
    }

    private static function flags() { return (array) get_option( self::FLAGS, [] ); }

    public static function ajax() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Only site admins can do this.' ], 403 );
        check_ajax_referer( 'mak_launch', 'nonce' );
        $op = sanitize_key( $_POST['op'] ?? '' );
        $v  = function ( $k ) { return isset( $_POST[ $k ] ) ? trim( (string) wp_unslash( $_POST[ $k ] ) ) : ''; };

        if ( $op === 'tick' ) {
            $f = self::flags();
            $k = sanitize_key( $v( 'step' ) );
            if ( $v( 'on' ) === '1' ) $f[ $k ] = time(); else unset( $f[ $k ] );
            update_option( self::FLAGS, $f, false );
            wp_send_json_success( [ 'ok' => true ] );
        }
        if ( $op === 'apple' ) {
            $r = MAK_AI_Tools::hub( 'keys', [ 'type' => 'apple', 'issuer_id' => $v( 'issuer_id' ), 'key_id' => $v( 'key_id' ), 'p8' => $v( 'p8' ) ] );
        } elseif ( $op === 'google' ) {
            $r = MAK_AI_Tools::hub( 'keys', [ 'type' => 'google', 'json' => $v( 'json' ) ] );
        } elseif ( $op === 'android_key' ) {
            $r = MAK_AI_Tools::hub( 'keys', [ 'type' => 'android' ] );
            if ( ! is_wp_error( $r ) && ! empty( $r['status']['keys']['android_upload_sha256'] ) ) {
                // The app links file needs this fingerprint; fill it in so nobody has to copy it.
                $s = (array) get_option( MAK_OPT, [] );
                $have = trim( (string) ( $s['android_sha256'] ?? '' ) );
                $fp = $r['status']['keys']['android_upload_sha256'];
                if ( strpos( $have, $fp ) === false ) { $s['android_sha256'] = trim( $have . "\n" . $fp ); update_option( MAK_OPT, $s ); }
            }
        } elseif ( $op === 'home' ) {
            $id = class_exists( 'MAK_Home' ) ? MAK_Home::ensure_page() : 0;
            $r = $id ? [ 'note' => 'Made. The app opens on it.' ] : new WP_Error( 'home', 'Could not make the page.' );
        } elseif ( in_array( $op, [ 'check', 'ios', 'store', 'android', 'submit' ], true ) ) {
            $r = MAK_AI_Tools::build( [ 'kind' => $op, 'confirm' => true ] );
        } elseif ( $op === 'download' ) {
            $r = MAK_AI_Tools::hub( 'download' );
        } elseif ( $op === 'status' ) {
            $r = MAK_AI_Tools::hub( 'launch' );
        } else {
            wp_send_json_error( [ 'message' => 'Unknown action.' ] );
        }
        if ( is_wp_error( $r ) ) wp_send_json_error( [ 'message' => $r->get_error_message() ] );
        wp_send_json_success( $r );
    }

    /* ---------------------------------------------------------------- page */

    public static function page() {
        $connected = (bool) Marketur_App_Kit::get( 'hub_key' );
        $st = $connected ? MAK_AI_Tools::hub( 'launch' ) : null;
        $err = is_wp_error( $st ) ? $st->get_error_message() : '';
        $st = is_array( $st ) ? $st : [];
        $f = self::flags();
        $app = $st['app'] ?? [];
        $keys = $st['keys'] ?? [];
        $done = $st['done'] ?? [];
        $checks = Marketur_App_Kit::checks();
        $ready = ! in_array( false, array_column( $checks, 'ok' ), true );
        $name = esc_html( $app['name'] ?? Marketur_App_Kit::get( 'app_name' ) );
        $bundle = esc_html( $app['bundle_id'] ?? '' );
        $pkg = esc_html( $app['package'] ?? '' );
        $sku = esc_html( $app['sku'] ?? '' );
        $last = function ( $kind ) use ( $st ) {
            foreach ( (array) ( $st['builds'] ?? [] ) as $b ) if ( $b['kind'] === $kind ) return $b;
            return null;
        };
        $build_line = function ( $kind ) use ( $last ) {
            $b = $last( $kind );
            if ( ! $b ) return '';
            $map = [ 'queued' => 'waiting to start', 'preparing' => 'starting', 'fetching' => 'starting', 'building' => 'running', 'testing' => 'running', 'publishing' => 'uploading', 'finishing' => 'finishing', 'finished' => 'finished', 'failed' => 'did not work', 'canceled' => 'canceled', 'timeout' => 'took too long' ];
            $s = $map[ $b['status'] ] ?? $b['status'];
            return '<p class="mk-b mk-b-' . esc_attr( $b['status'] ) . '">Last run ' . esc_html( $b['started'] ) . ': <strong>' . esc_html( $s ) . '</strong>' . ( $b['message'] ? ' (' . esc_html( $b['message'] ) . ')' : '' ) . '</p>';
        };
        $n = 0;
        $step = function ( $id, $title, $is_done, $body, $tick = false ) use ( &$n, $f ) {
            $n++;
            $on = $is_done || ( $tick && ! empty( $f[ $id ] ) );
            echo '<div class="mk-s' . ( $on ? ' on' : '' ) . '" id="mk-' . esc_attr( $id ) . '"><div class="mk-h"><span class="mk-n">' . ( $on ? '&#10003;' : $n ) . '</span><h3>' . esc_html( $title ) . '</h3></div><div class="mk-c">' . $body;
            if ( $tick ) echo '<label class="mk-tick"><input type="checkbox" data-step="' . esc_attr( $id ) . '"' . checked( ! empty( $f[ $id ] ), true, false ) . '> Done</label>';
            echo '</div></div>';
        };
        $btn = function ( $op, $label, $confirm = '' ) { return '<button type="button" class="button button-primary mk-go" data-op="' . esc_attr( $op ) . '" data-confirm="' . esc_attr( $confirm ) . '">' . esc_html( $label ) . '</button> <span class="mk-msg" data-for="' . esc_attr( $op ) . '"></span>'; };
        ?>
<div class="wrap mk-l">
  <h1>Launch your app</h1>
  <p class="description">Every step to get <?php echo $name ?: 'your app'; ?> into the App Store and Google Play, in order. Stuck on one? Ask the <a href="<?php echo esc_url( admin_url( 'admin.php?page=mak-assistant' ) ); ?>">Assistant</a> and it will walk you through it.</p>
  <?php if ( $err ) echo '<div class="notice notice-error inline"><p>' . esc_html( $err ) . '</p></div>'; ?>
  <?php
  $dfy = $connected ? MAK_AI_Tools::hub( 'dfy' ) : null;
  if ( is_array( $dfy ) && ( $dfy['email'] || $dfy['order_url'] ) ) {
      echo '<div class="mk-dfy"><strong>' . ( $dfy['done_for_you'] ? 'Marketur is doing this for you.' : 'Rather have us do all of this?' ) . '</strong> ';
      if ( $dfy['done_for_you'] && $dfy['email'] ) {
          echo 'Invite <code>' . esc_html( $dfy['email'] ) . '</code> in <a href="https://appstoreconnect.apple.com/access/users" target="_blank" rel="noopener">App Store Connect &gt; Users and Access</a> (click +, role <strong>Admin</strong>) and in <a href="https://play.google.com/console" target="_blank" rel="noopener">Play Console &gt; Users and permissions</a> (Invite new users, <strong>Admin</strong> for your app). We take it from there and tell you when your app is live. You only pay Apple and Google and accept their terms yourself.';
      } elseif ( $dfy['order_url'] ) {
          echo 'We build it, publish it and keep it updated for you. <a class="button" href="' . esc_url( $dfy['order_url'] ) . '" target="_blank" rel="noopener">Have us do it</a>';
      }
      echo '</div>';
  }
  ?>

  <h2>iPhone</h2>
  <?php
  $step( 'connect', 'Connect this site to Marketur', $connected, '<p>Paste the App Kit key from your Marketur order under <a href="' . esc_url( admin_url( 'options-general.php?page=marketur-app-kit' ) ) . '">Settings &gt; App Kit</a> and save.</p>' );
  $list = '';
  foreach ( $checks as $c ) if ( ! $c['ok'] ) $list .= '<li>' . esc_html( $c['label'] ) . '<br><span class="description">' . esc_html( $c['fix'] ) . '</span></li>';
  $step( 'ready', 'Get the site ready for review', $ready, $ready ? '<p>All the checks pass.</p>' : '<p>Apple and Google reject apps for these, so fix them first:</p><ul class="mk-ul">' . $list . '</ul>' );
  $hp = (int) Marketur_App_Kit::get( 'home_page' );
  $has_home = $hp && get_post_status( $hp ) === 'publish';
  $step( 'home', 'Give the app its own home screen', $has_home, '<p>Required. Apple turns down apps that are just the website, so your app opens on a home screen built from your own posts, products and links. Pick the look under <a href="' . esc_url( admin_url( 'options-general.php?page=marketur-app-kit' ) ) . '">Settings &gt; App Kit</a> (or ask the Assistant), then:</p>' . ( $has_home ? '<p><a class="button" href="' . esc_url( get_permalink( $hp ) ) . '" target="_blank">See it</a></p>' : $btn( 'home', 'Make the home screen' ) ) );
  $step( 'apple_account', 'Apple Developer account', false, '<p>You need your own account; it costs $99 a year. Go to <a href="https://developer.apple.com/programs/enroll/" target="_blank" rel="noopener">developer.apple.com/programs/enroll</a> and enroll. For a business, enroll as an <strong>organization</strong> (it needs a free D-U-N-S number and can take a few days); otherwise as an individual. Tick Done once Apple says you are in.</p>', true );
  $step( 'apple_key', 'Give Marketur access to build your app', ! empty( $keys['apple'] ),
      '<ol class="mk-ol"><li>Open <a href="https://appstoreconnect.apple.com/access/integrations/api" target="_blank" rel="noopener">App Store Connect &gt; Users and Access &gt; Integrations &gt; App Store Connect API</a>. If it asks, click <strong>Request Access</strong> and accept.</li>'
      . '<li>Under <strong>Team Keys</strong>, click the <strong>+</strong>. Name it <code>Marketur App Kit</code>, set Access to <strong>Admin</strong>, click <strong>Generate</strong>.</li>'
      . '<li>Click <strong>Download</strong> on the new key. Apple lets you download it only once, so keep the .p8 file.</li>'
      . '<li>Copy the <strong>Issuer ID</strong> (above the list) and the new key\'s <strong>Key ID</strong> into the boxes below, choose the .p8 file, and press Send.</li></ol>'
      . ( ! empty( $keys['apple'] ) ? '<p class="mk-ok">Sent on ' . esc_html( $keys['apple'] ) . '. Send again only if you made a new key.</p>' : '' )
      . '<div class="mk-form" data-op="apple"><p><label>Issuer ID<br><input type="text" name="issuer_id" class="regular-text" autocomplete="off"></label></p><p><label>Key ID<br><input type="text" name="key_id" class="regular-text" autocomplete="off"></label></p><p><label>The .p8 file<br><input type="file" accept=".p8" class="mk-file" data-into="p8"></label><input type="hidden" name="p8"></p>' . $btn( 'apple', 'Send to Marketur' ) . '<p class="description">Goes straight to the build service for this app only. Marketur does not keep a copy.</p></div>' );
  $step( 'registered', 'Register the app with Apple', ! empty( $done['registered'] ), '<p>Adds <code>' . $bundle . '</code> to your Apple account, with notifications and app links switched on. Takes about a minute.</p>' . $btn( 'check', 'Register' ) . $build_line( 'check' ) );
  $step( 'new_app', 'Make the app in App Store Connect', false,
      '<ol class="mk-ol"><li>Open <a href="https://appstoreconnect.apple.com/apps" target="_blank" rel="noopener">App Store Connect &gt; Apps</a>, click the blue <strong>+</strong>, then <strong>New App</strong>.</li>'
      . '<li>Tick <strong>iOS</strong>. Name: <code>' . $name . '</code>. Primary language: <strong>English (U.S.)</strong>. Bundle ID: pick <code>' . $bundle . '</code> from the list. SKU: <code>' . $sku . '</code>. User access: <strong>Full Access</strong>.</li>'
      . '<li>Click <strong>Create</strong>. If the name is taken, try a small change (for example adding your city) and tell the Assistant, so the store page uses it too.</li></ol>', true );
  $step( 'ios', 'Build the iPhone app', ! empty( $done['ios'] ), '<p>Builds the app and sends it to TestFlight in your Apple account. About 15 minutes, then Apple takes 10 to 30 minutes to process it. You can install it on your own phone from the TestFlight app once Apple emails you.</p>' . $btn( 'ios', 'Build the iPhone app', 'Start an iPhone build? It counts as one of this month\'s builds.' ) . $build_line( 'ios' ) );
  $step( 'store', 'Screenshots and the store page', ! empty( $done['store'] ), '<p>Takes the screenshots and fills in the App Store page: description, keywords, subtitle, links, category, age rating, price (free), the contact for App Review and the screenshots, and attaches the newest build. Run it after the iPhone build has finished processing. It also fills in Google Play once that is set up below.</p>' . $btn( 'store', 'Make the store page', 'Make the screenshots and store pages? It counts as one of this month\'s builds.' ) . $build_line( 'store' ) );
  $step( 'submitted', 'Your last part, then submit', false,
      '<ol class="mk-ol"><li>In App Store Connect open your app, then <strong>App Privacy</strong> in the left list. Click <strong>Get Started</strong>. If people can sign up, say yes to collecting data, tick <strong>Email Address</strong> and <strong>Name</strong> (under Contact Info) and <strong>User ID</strong>, each used for <strong>App Functionality</strong>, linked to the user, not used for tracking. Add anything else your site really collects (payments, photos, location). Publish.</li>'
      . '<li>Open <strong>Pricing and Availability</strong> and check the countries.</li>'
      . '<li>Look over the version page in App Store Connect, then press Submit here (or tell the Assistant to submit). Apple usually answers in one to three days. If they reject it, paste their message to the Assistant.</li></ol>'
      . $btn( 'submit', 'Submit for review', 'Send your app to Apple for review now? Make sure App Privacy is done first.' ) . $build_line( 'submit' ), true );
  ?>

  <h2>Android</h2>
  <?php
  $step( 'play_account', 'Google Play developer account', false, '<p>One-off $25 at <a href="https://play.google.com/console/signup" target="_blank" rel="noopener">play.google.com/console/signup</a>. Google checks your identity, which can take a few days. New personal accounts must have 12 testers try the app for 14 days before it can go public; organization accounts skip that.</p>', true );
  $step( 'play_app', 'Make the app in Play Console', false, '<p>In <a href="https://play.google.com/console" target="_blank" rel="noopener">Play Console</a> click <strong>Create app</strong>. Name: <code>' . $name . '</code>. Default language: English (United States). <strong>App</strong>, <strong>Free</strong>. Tick the declarations and create.</p>', true );
  $step( 'google_key', 'Give Marketur access to Google Play', ! empty( $keys['google'] ),
      '<ol class="mk-ol"><li>Open <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud</a> and make a project (any name).</li>'
      . '<li>In that project open <a href="https://console.cloud.google.com/apis/library/androidpublisher.googleapis.com" target="_blank" rel="noopener">Google Play Android Developer API</a> and click <strong>Enable</strong>.</li>'
      . '<li>Open <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener">IAM &gt; Service accounts</a>, click <strong>Create service account</strong>, name it <code>marketur-app-kit</code>, then Done. Click it, open <strong>Keys</strong>, <strong>Add key &gt; Create new key &gt; JSON</strong>. A .json file downloads.</li>'
      . '<li>In Play Console open <strong>Users and permissions</strong>, <strong>Invite new users</strong>, paste the service account\'s email (it ends in iam.gserviceaccount.com), add your app under App permissions with <strong>Admin</strong> rights, and send the invite.</li>'
      . '<li>Choose the .json file below and press Send.</li></ol>'
      . ( ! empty( $keys['google'] ) ? '<p class="mk-ok">Sent on ' . esc_html( $keys['google'] ) . ( $keys['google_email'] ? ' (' . esc_html( $keys['google_email'] ) . ')' : '' ) . '.</p>' : '' )
      . '<div class="mk-form" data-op="google"><p><label>The .json file<br><input type="file" accept=".json,application/json" class="mk-file" data-into="json"></label><input type="hidden" name="json"></p>' . $btn( 'google', 'Send to Marketur' ) . '<p class="description">Goes straight to the build service for this app only. Marketur does not keep a copy.</p></div>' );
  $step( 'android_key', 'Make the Android signing key', ! empty( $keys['android'] ), '<p>Makes the key that signs your Android app and keeps it with the build service. Done once.</p>' . ( empty( $keys['android'] ) ? $btn( 'android_key', 'Make the key' ) : '<p class="mk-ok">Made on ' . esc_html( $keys['android'] ) . '.</p>' ) );
  $step( 'android', 'Build the Android app', ! empty( $done['android'] ), '<p>Builds the file Google Play needs. About 10 minutes.</p>' . $btn( 'android', 'Build the Android app', 'Start an Android build? It counts as one of this month\'s builds.' ) . $build_line( 'android' ) );
  $step( 'first_upload', 'Upload the first build by hand (Google asks for this once)', false,
      '<ol class="mk-ol"><li>' . $btn( 'download', 'Download the app file' ) . '</li>'
      . '<li>In Play Console open your app, <strong>Test and release &gt; Testing &gt; Internal testing</strong>, <strong>Create new release</strong>, upload the file, <strong>Next</strong>, <strong>Save and publish</strong>.</li>'
      . '<li>From now on Marketur uploads new Android builds for you. Press <strong>Make the store page</strong> above again to fill in the Google Play page too.</li></ol>', true );
  $step( 'play_submitted', 'Your last part on Google, then send for review', false, '<p>In Play Console the <strong>Dashboard</strong> lists what is left: App access (add the demo member if your app needs a login), Ads, Content rating, Target audience, Data safety (same answers as Apple\'s App Privacy). Then go to <strong>Production</strong>, create a release from the internal test build and send it for review.</p>', true );
  ?>
  <p class="description"><?php if ( ! empty( $st['quota'] ) ) echo 'Builds this month: ' . (int) $st['quota']['used'] . ' of ' . (int) $st['quota']['per_month'] . '. Register and check runs do not count.'; ?></p>
</div>
<style>
.mk-l .mk-s{background:#fff;border:1px solid #dcdcde;border-radius:10px;margin:0 0 10px;max-width:880px}
.mk-l .mk-s.on{border-color:#a7e3b5;background:#fbfffc}
.mk-l .mk-h{display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer}
.mk-l .mk-h h3{margin:0;font-size:15px}
.mk-l .mk-n{width:26px;height:26px;border-radius:50%;background:#2271b1;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex:0 0 26px}
.mk-l .on .mk-n{background:#00a32a}
.mk-l .mk-c{padding:0 16px 14px 54px}
.mk-l .on .mk-c{display:none}
.mk-l .on.open .mk-c{display:block}
.mk-l .mk-ol{margin:0 0 10px 18px}.mk-l .mk-ol li{margin:0 0 6px}
.mk-l .mk-ul{margin:0 0 0 18px;list-style:disc}
.mk-l .mk-ok{color:#007017;font-weight:600}
.mk-l .mk-msg{margin-left:8px}
.mk-l .mk-msg.bad{color:#b32d2e}.mk-l .mk-msg.good{color:#007017}
.mk-l .mk-b{font-size:12px;color:#50575e}.mk-l .mk-b-failed{color:#b32d2e}.mk-l .mk-b-finished{color:#007017}
.mk-l .mk-tick{display:inline-block;margin-top:6px;font-weight:600}
.mk-l h2{margin-top:26px}
.mk-l .mk-dfy{background:#f0f6fc;border:1px solid #c5d9ed;border-radius:10px;padding:12px 16px;margin:12px 0 4px;max-width:880px;line-height:1.6}
</style>
<script>
(function(){
  var url=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce=<?php echo wp_json_encode( wp_create_nonce( 'mak_launch' ) ); ?>;
  function post(d){var f=new FormData();f.append('action','mak_launch');f.append('nonce',nonce);for(var k in d)f.append(k,d[k]);return fetch(url,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();});}
  document.querySelectorAll('.mk-l .mk-h').forEach(function(h){h.addEventListener('click',function(){h.parentNode.classList.toggle('open');});});
  document.querySelectorAll('.mk-l .mk-file').forEach(function(inp){inp.addEventListener('change',function(){var f=inp.files[0];if(!f)return;var r=new FileReader();r.onload=function(){inp.closest('.mk-form').querySelector('[name='+inp.dataset.into+']').value=r.result;};r.readAsText(f);});});
  document.querySelectorAll('.mk-l .mk-tick input').forEach(function(cb){cb.addEventListener('change',function(){post({op:'tick',step:cb.dataset.step,on:cb.checked?'1':'0'}).then(function(){cb.closest('.mk-s').classList.toggle('on',cb.checked);});});});
  document.querySelectorAll('.mk-l .mk-go').forEach(function(b){b.addEventListener('click',function(){
    var op=b.dataset.op,msg=document.querySelector('.mk-msg[data-for='+op+']');
    if(b.dataset.confirm&&!window.confirm(b.dataset.confirm))return;
    var d={op:op},form=b.closest('.mk-form');
    if(form){form.querySelectorAll('input[name]').forEach(function(i){d[i.name]=i.value;});}
    b.disabled=true;msg.className='mk-msg';msg.textContent='Working...';
    post(d).then(function(j){
      b.disabled=false;
      if(!j||!j.success){msg.className='mk-msg bad';msg.textContent=(j&&j.data&&j.data.message)||'That did not work. Try again.';return;}
      var r=j.data||{};
      if(op==='download'&&r.url){msg.className='mk-msg good';msg.innerHTML='<a href="'+r.url+'">Download '+(r.name||'the file')+'</a> (link works for 1 hour)';return;}
      msg.className='mk-msg good';msg.textContent=r.note||'Done.';
      if(form)form.querySelectorAll('input').forEach(function(i){i.value='';});
      setTimeout(function(){location.reload();},2500);
    }).catch(function(){b.disabled=false;msg.className='mk-msg bad';msg.textContent='Lost the connection. Try again.';});
  });});
})();
</script>
        <?php
    }
}

MAK_Launch::init();
