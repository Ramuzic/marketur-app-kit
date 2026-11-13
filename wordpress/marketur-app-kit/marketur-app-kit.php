<?php
/**
 * Plugin Name: Marketur App Kit
 * Description: Gets a WordPress site ready to be an iPhone and Android app made with the Marketur App Kit. Hides the web menus inside the app, adds the delete account page app stores ask for, serves the app link files, checks the site is ready, and sends the app its live settings (tabs, colors, bar style) so most changes need no new build.
 * Version: 1.5.0
 * Author: Marketur
 * Author URI: https://marketur.net
 * License: GPLv2 or later
 * Text Domain: marketur-app-kit
 */

defined( 'ABSPATH' ) || exit;

define( 'MAK_VERSION', '1.5.0' );
define( 'MAK_OPT', 'mak_settings' );

final class Marketur_App_Kit {

    public static function instance() {
        static $i = null;
        if ( ! $i ) { $i = new self(); }
        return $i;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'well_known' ], 0 );
        add_action( 'rest_api_init', [ $this, 'routes' ] );
        add_action( 'wp_head', [ $this, 'head' ], 1 );
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_action( 'wp_footer', [ $this, 'footer' ], 99 );
        add_shortcode( 'mak_delete_account', [ $this, 'delete_shortcode' ] );
        add_action( 'template_redirect', [ $this, 'delete_post' ] );
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'save' ] );
        add_action( 'admin_post_mak_export', [ $this, 'export' ] );
        add_action( 'admin_post_mak_make_delete_page', [ $this, 'make_delete_page' ] );
        add_action( 'admin_post_mak_make_home', function () {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No' );
            check_admin_referer( 'mak_make_home' );
            if ( class_exists( 'MAK_Home' ) ) MAK_Home::ensure_page();
            wp_safe_redirect( admin_url( 'options-general.php?page=marketur-app-kit&made_home=1' ) ); exit;
        } );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $l ) {
            array_unshift( $l, '<a href="' . esc_url( admin_url( 'options-general.php?page=marketur-app-kit' ) ) . '">Settings</a>' );
            return $l;
        } );
    }

    /* ---------------------------------------------------------------- settings */

    public static function defaults() {
        return [
            'app_name'       => get_bloginfo( 'name' ),
            'ua_tag'         => 'MarketurApp',
            'hide'           => "header.site-header\nfooter.site-footer\n#masthead\n#colophon\n.elementor-location-header\n.elementor-location-footer\n.pwa-install-prompt\n#pwa-install\n.cookie-notice-container",
            'css'            => '',
            'start'          => home_url( '/' ),
            'bundle_id'      => '',
            'android_package'=> '',
            'apple_team_id'  => '',
            'android_sha256' => '',
            'delete_page'    => 0,
            'delete_mode'    => 'now',
            'colors'         => [ 'top' => '#FFFFFF', 'bar' => '#FFFFFF', 'active' => '#2563EB', 'inactive' => '#9CA3AF', 'paper' => '#FFFFFF', 'ink' => '#111111', 'muted' => '#6B7280' ],
            'tabs'           => "Home|/|home\nAccount|/my-account/|account",
            'nav'            => 'floating',
            'onboarding'     => '',
            'live'           => 0,
            'updated'        => 0,
            'hub_key'        => '',
            'home_template'  => 'dashboard',
            'home_page'      => 0,
            'home_headline'  => '',
            'home_tiles'     => '',
            'home_phone'     => '',
            'home_book'      => '',
            'home_address'   => '',
            'home_hours'     => '',
            'home_email'     => '',
            'description'    => '',
            'subtitle'       => '',
            'keywords'       => '',
            'support_email'  => get_option( 'admin_email' ),
        ];
    }

    public static function get( $k = null ) {
        $o = wp_parse_args( (array) get_option( MAK_OPT, [] ), self::defaults() );
        $o['colors'] = wp_parse_args( (array) $o['colors'], self::defaults()['colors'] );
        return $k === null ? $o : ( $o[ $k ] ?? null );
    }

    /** True inside a Kit app (the app adds MarketurApp to its user agent). */
    public static function in_app() {
        $ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $tag = trim( (string) self::get( 'ua_tag' ) );
        $in  = ( $tag !== '' && stripos( $ua, $tag ) !== false ) || stripos( $ua, 'MarketurApp' ) !== false;
        return (bool) apply_filters( 'mak_in_app', $in, $ua );
    }

    /* ---------------------------------------------------------------- app links */

    /** /.well-known/apple-app-site-association and /.well-known/assetlinks.json */
    public function well_known() {
        $path = strtok( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', '?' );
        $s = self::get();
        if ( $path === '/.well-known/apple-app-site-association' || $path === '/apple-app-site-association' ) {
            if ( ! $s['apple_team_id'] || ! $s['bundle_id'] ) return;
            $id = $s['apple_team_id'] . '.' . $s['bundle_id'];
            $this->json( [
                'applinks'     => [ 'details' => [ [ 'appIDs' => [ $id ], 'components' => [ [ '/' => '/wp-admin/*', 'exclude' => true ], [ '/' => '/wp-login.php*', 'exclude' => true ], [ '/' => '*' ] ] ] ] ],
                'webcredentials' => [ 'apps' => [ $id ] ],
            ] );
        }
        if ( $path === '/.well-known/assetlinks.json' ) {
            $pkg = $s['android_package'] ?: $s['bundle_id'];
            $fps = array_values( array_filter( array_map( 'trim', preg_split( '/[\s,]+/', strtoupper( (string) $s['android_sha256'] ) ) ) ) );
            if ( ! $pkg || ! $fps ) return;
            $this->json( [ [ 'relation' => [ 'delegate_permission/common.handle_all_urls' ], 'target' => [ 'namespace' => 'android_app', 'package_name' => $pkg, 'sha256_cert_fingerprints' => $fps ] ] ] );
        }
    }

    private function json( $data ) {
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: application/json' );
        echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        exit;
    }

    /* ---------------------------------------------------------------- live app settings */

    public static function navs() {
        return [
            'floating' => 'Floating bar (a rounded bar over the page, the newest iPhone look)',
            'fixed'    => 'Fixed bar (a full width bar that always stays at the bottom)',
            'none'     => 'No bar (just the site, for sites with their own menu)',
        ];
    }

    public static function tabs_list() {
        $tabs = [];
        foreach ( array_filter( array_map( 'trim', explode( "\n", (string) self::get( 'tabs' ) ) ) ) as $line ) {
            $p = array_map( 'trim', explode( '|', $line ) );
            if ( count( $p ) < 2 || $p[0] === '' ) continue;
            $url = preg_match( '#^https?://#', $p[1] ) ? $p[1] : home_url( '/' . ltrim( $p[1], '/' ) );
            $t = [ 'label' => $p[0], 'url' => $url, 'icon' => ( $p[2] ?? '' ) !== '' ? $p[2] : 'home' ];
            if ( ! empty( $p[3] ) ) $t['action'] = $p[3];
            if ( ! $tabs ) $t['match'] = [ '/' ];
            $tabs[] = $t;
            if ( count( $tabs ) === 5 ) break;
        }
        return $tabs;
    }

    public static function onboarding_list() {
        $out = [];
        $lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) self::get( 'onboarding' ) ) ) ) );
        foreach ( $lines as $i => $line ) {
            $p = array_map( 'trim', explode( '|', $line ) );
            if ( count( $p ) < 2 ) continue;
            $last = $i === count( $lines ) - 1;
            $out[] = [ 'icon' => $p[2] ?? ( $last ? 'bell' : 'home' ), 'title' => $p[0], 'text' => $p[1], 'push' => $last ];
        }
        return $out;
    }

    /** What the app reads each time it opens (only when Live settings is on). */
    public static function live_config() {
        $s = self::get();
        $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        if ( 0 === strpos( $host, 'www.' ) ) $host = substr( $host, 4 );
        $c = [ 'host' => $host, 'updated' => (int) $s['updated'], 'nav' => isset( self::navs()[ $s['nav'] ] ) ? $s['nav'] : 'floating', 'colors' => $s['colors'] ];
        if ( $s['start'] ) $c['start'] = $s['start'];
        $tabs = self::tabs_list();
        if ( $tabs ) $c['tabs'] = $tabs;
        $ob = self::onboarding_list();
        if ( $ob ) $c['onboarding'] = $ob;
        return (array) apply_filters( 'mak_live_config', $c );
    }

    public function routes() {
        register_rest_route( 'mak/v1', '/app-config', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => function () {
                if ( ! self::get( 'live' ) ) return new WP_Error( 'mak_off', 'Live settings are off.', [ 'status' => 404 ] );
                $r = rest_ensure_response( self::live_config() );
                $r->header( 'Cache-Control', 'no-store' );
                return $r;
            },
        ] );
    }

    /**
     * Changes the app settings in one go, for the AI tools, so they follow the same rules
     * as the settings page.
     */
    public static function update_app( array $in ) {
        $s = self::get();
        foreach ( [ 'app_name', 'subtitle', 'keywords', 'home_headline', 'home_phone', 'home_address' ] as $k ) if ( isset( $in[ $k ] ) ) $s[ $k ] = sanitize_text_field( $in[ $k ] );
        if ( isset( $in['home_book'] ) ) $s['home_book'] = esc_url_raw( $in['home_book'] );
        if ( isset( $in['home_email'] ) ) $s['home_email'] = sanitize_email( $in['home_email'] );
        if ( isset( $in['home_template'] ) && class_exists( 'MAK_Home' ) && isset( MAK_Home::templates()[ $in['home_template'] ] ) ) $s['home_template'] = $in['home_template'];
        if ( isset( $in['start'] ) ) $s['start'] = esc_url_raw( $in['start'] );
        foreach ( [ 'tabs', 'onboarding', 'description', 'hide', 'home_tiles', 'home_hours' ] as $k ) if ( isset( $in[ $k ] ) ) $s[ $k ] = sanitize_textarea_field( $in[ $k ] );
        if ( isset( $in['css'] ) ) $s['css'] = wp_strip_all_tags( $in['css'] );
        if ( isset( $in['nav'] ) && isset( self::navs()[ $in['nav'] ] ) ) $s['nav'] = $in['nav'];
        if ( isset( $in['live'] ) ) $s['live'] = $in['live'] ? 1 : 0;
        if ( isset( $in['colors'] ) && is_array( $in['colors'] ) ) {
            foreach ( array_keys( self::defaults()['colors'] ) as $c ) { $v = sanitize_hex_color( $in['colors'][ $c ] ?? '' ); if ( $v ) $s['colors'][ $c ] = $v; }
        }
        $s['updated'] = time();
        update_option( MAK_OPT, $s );
        return $s;
    }

    /* ---------------------------------------------------------------- inside the app */

    public function body_class( $c ) {
        if ( self::in_app() ) $c[] = 'mak-app';
        return $c;
    }

    public function head() {
        if ( ! self::in_app() ) return;
        $hide = array_filter( array_map( 'trim', explode( "\n", (string) self::get( 'hide' ) ) ) );
        $sel  = implode( ',', array_map( function ( $x ) { return 'html.mak-app ' . $x; }, $hide ) );
        echo "<script>document.documentElement.classList.add('mak-app');</script>\n";
        echo '<style id="mak-app-css">';
        if ( $sel ) echo wp_strip_all_tags( $sel ) . '{display:none!important}';
        echo 'html.mak-app body{-webkit-tap-highlight-color:transparent;overscroll-behavior-y:none}';
        echo 'html.mak-app{scroll-padding-bottom:80px}';
        echo wp_strip_all_tags( (string) self::get( 'css' ) );
        echo "</style>\n";
    }

    public function footer() {
        if ( ! self::in_app() ) return;
        ?>
<script id="mak-app-js">
(function(){
  /* Native share sheet: the web share button works inside the app too. */
  var C = window.Capacitor, P = C && C.Plugins;
  if (P && P.Share && !navigator.share) {
    navigator.share = function (d) { return P.Share.share({ title: d && d.title, text: d && d.text, url: d && d.url }); };
  }
  /* Install-the-app prompts make no sense inside the app. */
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); });
})();
</script>
        <?php
    }

    /* ---------------------------------------------------------------- delete account */

    public function delete_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="mak-del"><p>Sign in first, then come back to this page to delete your account.</p><p><a class="button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Sign in</a></p></div>';
        }
        if ( isset( $_GET['mak_deleted'] ) ) return '<div class="mak-del"><p>Your request was sent. We will delete your account and email you when it is done.</p></div>';
        $u = wp_get_current_user();
        if ( user_can( $u, 'manage_options' ) ) return '<div class="mak-del"><p>Site admins can not delete their account here.</p></div>';
        $err = isset( $_GET['mak_err'] ) ? '<p style="color:#b91c1c">Type DELETE in capitals to confirm.</p>' : '';
        $now = self::get( 'delete_mode' ) !== 'request';
        ob_start(); ?>
<div class="mak-del">
  <p><?php echo $now ? 'This deletes your account and everything tied to it on this site. It can not be undone.' : 'Send a request to delete your account and everything tied to it on this site.'; ?></p>
  <p>Signed in as <strong><?php echo esc_html( $u->user_login ); ?></strong> (<?php echo esc_html( $u->user_email ); ?>).</p>
  <?php echo $err; ?>
  <form method="post">
    <?php wp_nonce_field( 'mak_delete', 'mak_nonce' ); ?>
    <p><label>Type DELETE to confirm<br><input type="text" name="mak_confirm" autocomplete="off" style="max-width:240px"></label></p>
    <p><button type="submit" name="mak_delete" value="1" class="button" style="background:#b91c1c;color:#fff;border-color:#b91c1c"><?php echo $now ? 'Delete my account' : 'Request deletion'; ?></button></p>
  </form>
</div>
        <?php
        return ob_get_clean();
    }

    public function delete_post() {
        if ( empty( $_POST['mak_delete'] ) || ! is_user_logged_in() ) return;
        if ( ! isset( $_POST['mak_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mak_nonce'] ) ), 'mak_delete' ) ) return;
        $back = remove_query_arg( [ 'mak_err', 'mak_deleted' ], wp_get_referer() ?: home_url( '/' ) );
        if ( trim( (string) ( $_POST['mak_confirm'] ?? '' ) ) !== 'DELETE' ) { wp_safe_redirect( add_query_arg( 'mak_err', 1, $back ) ); exit; }
        $u = wp_get_current_user();
        if ( user_can( $u, 'manage_options' ) ) { wp_safe_redirect( $back ); exit; }
        if ( self::get( 'delete_mode' ) === 'request' ) {
            wp_mail( get_option( 'admin_email' ), 'Account deletion request: ' . $u->user_login, 'Member ' . $u->user_login . ' (' . $u->user_email . ', ID ' . $u->ID . ') asked for their account to be deleted from the app. Apple and Google expect this within a reasonable time.' );
            update_user_meta( $u->ID, 'mak_delete_requested', time() );
            wp_safe_redirect( add_query_arg( 'mak_deleted', 1, $back ) ); exit;
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        do_action( 'mak_before_delete_account', $u->ID );
        $email = $u->user_email;
        wp_logout();
        wp_delete_user( $u->ID );
        if ( $email ) wp_mail( $email, 'Your account was deleted', 'Your account on ' . get_bloginfo( 'name' ) . ' has been deleted.' );
        wp_safe_redirect( home_url( '/?mak_account_deleted=1' ) ); exit;
    }

    public function make_delete_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No' );
        check_admin_referer( 'mak_make_delete_page' );
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Delete Your Account', 'post_name' => 'delete-account', 'post_content' => '[mak_delete_account]' ] );
        if ( $id && ! is_wp_error( $id ) ) { $s = (array) get_option( MAK_OPT, [] ); $s['delete_page'] = $id; update_option( MAK_OPT, $s ); }
        wp_safe_redirect( admin_url( 'options-general.php?page=marketur-app-kit&made=1' ) ); exit;
    }

    /* ---------------------------------------------------------------- readiness */

    public static function checks() {
        $s = self::get();
        $out = [];
        $add = function ( $ok, $label, $fix = '' ) use ( &$out ) { $out[] = [ 'ok' => $ok, 'label' => $label, 'fix' => $fix ]; };
        $add( is_ssl() || strpos( home_url(), 'https://' ) === 0, 'The site uses https', 'Apps only load https sites. Turn on SSL with your host.' );
        $pp = (int) get_option( 'wp_page_for_privacy_policy' );
        $add( $pp && get_post_status( $pp ) === 'publish', 'A privacy policy page is published and set', 'Settings > Privacy: pick or make the privacy policy page and publish it. Both stores ask for its link.' );
        $reg = get_option( 'users_can_register' ) || class_exists( 'WooCommerce' ) || class_exists( 'Ihc_Db' ) || function_exists( 'ihc_is_magic_feat_active' );
        $dp  = (int) $s['delete_page'];
        $add( ! $reg || ( $dp && get_post_status( $dp ) === 'publish' ), $reg ? 'People can make accounts, so there is a delete account page' : 'No public sign-up found (a delete account page is still a good idea)', 'Use the Make the delete account page button below. Apple rejects apps with sign-up but no way to delete the account.' );
        $icon = (int) get_option( 'site_icon' );
        $meta = $icon ? wp_get_attachment_metadata( $icon ) : [];
        $add( $icon && ! empty( $meta['width'] ) && $meta['width'] >= 512, 'The site icon is at least 512 x 512', 'Appearance > Customize > Site Identity: a square icon, 1024 x 1024 is best. It becomes the app icon.' );
        $social = [];
        foreach ( [ 'nextend-facebook-connect/nextend-facebook-connect.php' => 'Nextend Social Login', 'miniorange-login-openid/miniorange_openid_sso_settings.php' => 'miniOrange Social Login', 'wordpress-social-login/wp-social-login.php' => 'WordPress Social Login', 'super-socializer/super_socializer.php' => 'Super Socializer' ] as $f => $n ) {
            if ( mak_is_plugin_active( $f ) ) $social[] = $n;
        }
        $add( ! $social, $social ? 'Social sign-in found (' . implode( ', ', $social ) . '): the app needs Sign in with Apple too' : 'No Google or Facebook sign-in found', 'Apple guideline 4.8: offer Sign in with Apple next to other social sign-ins, or hide them inside the app.' );
        $add( (bool) $s['bundle_id'], 'App bundle id is filled in', 'Fill in the bundle id below (from your App Kit order).' );
        $add( $s['apple_team_id'] && $s['bundle_id'], 'iPhone links open in the app (Apple Team ID set)', 'Add the Apple Team ID (developer.apple.com > Membership).' );
        $add( (bool) trim( (string) $s['android_sha256'] ), 'Android links open in the app (SHA-256 set)', 'Play Console > Test and release > App integrity > App signing key SHA-256, paste it below.' );
        $add( strlen( wp_strip_all_tags( (string) $s['description'] ) ) >= 200, 'Store description written (200+ characters)', 'Write what people can do in the app. It goes on both store pages.' );
        $hp = (int) $s['home_page'];
        $add( $hp && get_post_status( $hp ) === 'publish' && untrailingslashit( (string) $s['start'] ) === untrailingslashit( (string) get_permalink( $hp ) ), 'The app opens on its own home screen', 'Apple turns down apps that are just the website. Pick a home screen look below and press Make the app home screen.' );
        $add( ! ( class_exists( 'WooCommerce' ) && self::sells_digital() ), 'No digital products sold on the site', 'You sell downloads or virtual products. Apple wants those bought with in-app purchase inside the iPhone app; ask us before launch.' );
        return $out;
    }

    private static function sells_digital() {
        if ( ! function_exists( 'wc_get_products' ) ) return false;
        $p = wc_get_products( [ 'limit' => 1, 'status' => 'publish', 'virtual' => true, 'return' => 'ids' ] );
        $d = wc_get_products( [ 'limit' => 1, 'status' => 'publish', 'downloadable' => true, 'return' => 'ids' ] );
        return ! empty( $p ) || ! empty( $d );
    }

    /* ---------------------------------------------------------------- export */

    public static function customer_json() {
        $s = self::get();
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $slug = sanitize_title( preg_replace( '/^www\./', '', (string) $host ) );
        $slug = preg_replace( '/[^a-z0-9]/', '', explode( '-', $slug )[0] ) ?: 'site';
        $tabs = [];
        foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $s['tabs'] ) ) ) as $line ) {
            $p = array_map( 'trim', explode( '|', $line ) );
            if ( count( $p ) < 2 ) continue;
            $url = preg_match( '#^https?://#', $p[1] ) ? $p[1] : home_url( $p[1] );
            $t = [ 'label' => $p[0], 'url' => $url, 'icon' => $p[2] ?? 'home' ];
            if ( ! $tabs ) $t['match'] = [ '/' ];
            $tabs[] = $t;
        }
        $pp = (int) get_option( 'wp_page_for_privacy_policy' );
        $name = (string) $s['app_name'];
        return [
            'slug' => $slug, 'name' => $name, 'short_name' => mb_substr( $name, 0, 12 ),
            'bundle_id' => $s['bundle_id'], 'android_package' => $s['android_package'] ?: $s['bundle_id'],
            'site' => home_url( '/' ), 'start' => $s['start'] ?: home_url( '/' ), 'version' => '1.0.0',
            'onesignal_app_id' => '', 'nav' => $s['nav'], 'colors' => $s['colors'], 'tabs' => array_slice( $tabs, 0, 5 ),
            'onboarding' => [
                [ 'icon' => 'home', 'title' => 'Welcome to ' . $name, 'text' => get_bloginfo( 'description' ) ?: 'Everything from ' . $name . ', in one place.' ],
                [ 'icon' => 'bell', 'title' => 'Stay in the loop', 'text' => 'Turn on notifications for news and replies. You can change this any time.', 'push' => true ],
            ],
            'shortcuts' => [],
            'features' => [ 'apple_sign_in' => false ],
            'store' => [
                'subtitle' => $s['subtitle'], 'description' => $s['description'], 'keywords' => $s['keywords'],
                'support_url' => home_url( '/' ), 'marketing_url' => home_url( '/' ),
                'privacy_url' => $pp ? get_permalink( $pp ) : '',
                'copyright' => gmdate( 'Y' ) . ' ' . $name,
                'review' => [ 'email' => $s['support_email'], 'notes' => '' ],
                'play' => [ 'short_description' => $s['subtitle'], 'contact_email' => $s['support_email'], 'contact_website' => home_url( '/' ) ],
            ],
            'screenshots' => array_map( function ( $t, $i ) { return [ 'name' => sprintf( '%02d-', $i + 2 ) . sanitize_title( $t['label'] ), 'url' => $t['url'], 'wait' => 14 ]; }, array_slice( $tabs, 0, 5 ), array_keys( array_slice( $tabs, 0, 5 ) ) ),
        ];
    }

    public function export() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No' );
        check_admin_referer( 'mak_export' );
        nocache_headers();
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename=customer.json' );
        echo wp_json_encode( self::customer_json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /* ---------------------------------------------------------------- admin */

    public function menu() {
        add_options_page( 'App Kit', 'App Kit', 'manage_options', 'marketur-app-kit', [ $this, 'page' ] );
    }

    public function save() {
        if ( empty( $_POST['mak_save'] ) || ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'mak_save' );
        $in = wp_unslash( $_POST['mak'] ?? [] );
        $s  = self::get();
        foreach ( [ 'app_name', 'ua_tag', 'bundle_id', 'android_package', 'apple_team_id', 'subtitle', 'keywords', 'home_headline', 'home_phone', 'home_address' ] as $k ) $s[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
        $s['home_book'] = esc_url_raw( $in['home_book'] ?? '' );
        $s['home_email'] = sanitize_email( $in['home_email'] ?? '' );
        $s['home_tiles'] = sanitize_textarea_field( $in['home_tiles'] ?? '' );
        $s['home_hours'] = sanitize_textarea_field( $in['home_hours'] ?? '' );
        if ( class_exists( 'MAK_Home' ) && isset( MAK_Home::templates()[ $in['home_template'] ?? '' ] ) ) $s['home_template'] = $in['home_template'];
        $s['start'] = esc_url_raw( $in['start'] ?? '' );
        $s['support_email'] = sanitize_email( $in['support_email'] ?? '' );
        foreach ( [ 'hide', 'tabs', 'android_sha256', 'description' ] as $k ) $s[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
        $s['css'] = wp_strip_all_tags( $in['css'] ?? '' );
        $s['delete_mode'] = ( $in['delete_mode'] ?? '' ) === 'request' ? 'request' : 'now';
        $s['delete_page'] = absint( $in['delete_page'] ?? 0 );
        $s['onboarding'] = sanitize_textarea_field( $in['onboarding'] ?? '' );
        $s['nav'] = isset( self::navs()[ $in['nav'] ?? '' ] ) ? $in['nav'] : 'floating';
        $s['live'] = empty( $in['live'] ) ? 0 : 1;
        $s['updated'] = time();
        foreach ( array_keys( self::defaults()['colors'] ) as $c ) { $v = sanitize_hex_color( $in['colors'][ $c ] ?? '' ); if ( $v ) $s['colors'][ $c ] = $v; }
        // The Marketur key is only replaced when a new one is typed in.
        $hk = trim( sanitize_text_field( $in['hub_key'] ?? '' ) );
        if ( $hk !== '' ) $s['hub_key'] = $hk;
        if ( ! empty( $in['hub_key_clear'] ) ) $s['hub_key'] = '';
        update_option( MAK_OPT, $s );
        wp_safe_redirect( admin_url( 'options-general.php?page=marketur-app-kit&saved=1' ) ); exit;
    }

    public function page() {
        $s = self::get();
        $f = function ( $k, $label, $help = '' ) use ( $s ) {
            echo '<tr><th><label for="mak_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="mak_' . esc_attr( $k ) . '" name="mak[' . esc_attr( $k ) . ']" value="' . esc_attr( $s[ $k ] ) . '">' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . '</td></tr>';
        };
        $t = function ( $k, $label, $help = '', $rows = 4 ) use ( $s ) {
            echo '<tr><th><label for="mak_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><textarea class="large-text code" rows="' . (int) $rows . '" id="mak_' . esc_attr( $k ) . '" name="mak[' . esc_attr( $k ) . ']">' . esc_textarea( $s[ $k ] ) . '</textarea>' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . '</td></tr>';
        };
        ?>
<div class="wrap">
  <h1>Marketur App Kit</h1>
  <?php if ( isset( $_GET['saved'] ) ) echo '<div class="notice notice-success"><p>Saved.</p></div>'; ?>
  <?php if ( isset( $_GET['made'] ) ) echo '<div class="notice notice-success"><p>The delete account page was made.</p></div>'; ?>
  <h2>Is the site ready?</h2>
  <table class="widefat striped" style="max-width:900px"><tbody>
  <?php foreach ( self::checks() as $c ) : ?>
    <tr><td style="width:28px;font-size:18px"><?php echo $c['ok'] ? '<span style="color:#16a34a">&#10003;</span>' : '<span style="color:#d97706">!</span>'; ?></td>
    <td><strong><?php echo esc_html( $c['label'] ); ?></strong><?php if ( ! $c['ok'] && $c['fix'] ) echo '<br><span class="description">' . esc_html( $c['fix'] ) . '</span>'; ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <p>
    <?php if ( ! $s['delete_page'] || get_post_status( (int) $s['delete_page'] ) !== 'publish' ) : ?>
      <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mak_make_delete_page' ), 'mak_make_delete_page' ) ); ?>">Make the delete account page</a>
    <?php endif; ?>
    <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mak_export' ), 'mak_export' ) ); ?>">Download customer.json</a>
    <span class="description">Send this file with your App Kit order. It holds the app name, colors, tabs and store text below.</span>
  </p>

  <form method="post">
    <?php wp_nonce_field( 'mak_save' ); ?>
    <input type="hidden" name="mak_save" value="1">
    <h2>The app</h2>
    <table class="form-table">
      <?php $f( 'app_name', 'App name', 'Up to 30 characters. Under the icon it is cut to about 12.' ); ?>
      <?php $f( 'start', 'Start page', 'The page the app opens on.' ); ?>
      <tr><th>Live settings</th><td>
        <label><input type="checkbox" name="mak[live]" value="1" <?php checked( (int) $s['live'], 1 ); ?>> The app takes its tabs, colors, bar style and start page from here</label>
        <p class="description">The app picks up changes the next time it is opened, with no new build. Leave off until these settings are right, or the app uses these instead of the ones it was built with.</p>
      </td></tr>
      <tr><th><label for="mak_nav">Bar style</label></th><td>
        <select id="mak_nav" name="mak[nav]"><?php foreach ( self::navs() as $k => $v ) echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['nav'], $k, false ) . '>' . esc_html( $v ) . '</option>'; ?></select>
      </td></tr>
      <?php $t( 'tabs', 'Tab bar', 'One tab per line, up to 5: <code>Label|/path/|icon</code>. Icons: home, shop, cart, account, search, news, chat, community, bell, heart, star, map, phone, calendar, play, grid, info, menu.' ); ?>
      <?php $t( 'onboarding', 'First launch screens', 'One screen per line: <code>Title|Text</code>. The last one asks to turn on notifications. Empty keeps the screens the app was built with.', 4 ); ?>
      <tr><th>Colors</th><td>
        <?php foreach ( $s['colors'] as $k => $v ) : ?>
          <label style="display:inline-block;margin:0 14px 8px 0"><?php echo esc_html( ucfirst( $k ) ); ?><br><input type="color" name="mak[colors][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $v ); ?>"></label>
        <?php endforeach; ?>
        <p class="description">Top: the bar behind the clock. Bar: the tab bar. Active and Inactive: the tab icons. Paper and Ink: the onboarding and offline screens.</p>
      </td></tr>
    </table>
    <h2>App home screen</h2>
    <?php if ( isset( $_GET['made_home'] ) ) echo '<div class="notice notice-success inline"><p>The App Home page is made and the app opens on it.</p></div>'; ?>
    <table class="form-table">
      <tr><th>Look</th><td>
        <?php foreach ( ( class_exists( 'MAK_Home' ) ? MAK_Home::templates() : [] ) as $k => $v ) echo '<label style="display:block;margin:0 0 6px"><input type="radio" name="mak[home_template]" value="' . esc_attr( $k ) . '"' . checked( $s['home_template'], $k, false ) . '> ' . esc_html( $v ) . '</label>'; ?>
        <p>
          <?php $hp = (int) $s['home_page']; if ( $hp && get_post_status( $hp ) === 'publish' ) : ?>
            <a class="button" href="<?php echo esc_url( get_permalink( $hp ) ); ?>" target="_blank">See the home screen</a>
          <?php else : ?>
            <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mak_make_home' ), 'mak_make_home' ) ); ?>">Make the app home screen</a>
          <?php endif; ?>
        </p>
        <p class="description">Required. Apple turns down apps that are just the website, so the app opens on this screen. It uses your own posts, products and links.</p>
      </td></tr>
      <?php $f( 'home_headline', 'Greeting', 'Shown to visitors. Members see Hi and their first name.' ); ?>
      <?php $t( 'home_tiles', 'Shortcut tiles', 'Up to 8, one per line: <code>Label|/path/|icon</code>. Empty uses the tabs.', 4 ); ?>
      <?php $f( 'home_phone', 'Phone (Local business look)' ); ?>
      <?php $f( 'home_book', 'Booking link (Local business look)' ); ?>
      <?php $f( 'home_address', 'Address (Local business look)' ); ?>
      <?php $f( 'home_email', 'Email (Local business look)' ); ?>
      <?php $t( 'home_hours', 'Opening hours (Local business look)', 'One line per day or range.', 3 ); ?>
    </table>
    <h2>Store pages</h2>
    <table class="form-table">
      <?php $f( 'subtitle', 'Subtitle', 'Up to 30 characters, under the name on the App Store. Also the start of the Google Play short description.' ); ?>
      <?php $t( 'description', 'Description', 'What people can do in the app. Plain text, short lines with a dash work well. Up to 4000 characters.', 8 ); ?>
      <?php $f( 'keywords', 'Keywords', 'Comma separated, up to 100 characters, no spaces after commas. iPhone only.' ); ?>
      <?php $f( 'support_email', 'Support email', 'Shown to App Review and on Google Play.' ); ?>
    </table>
    <h2>Inside the app</h2>
    <table class="form-table">
      <?php $t( 'hide', 'Hide inside the app', 'CSS selectors, one per line. The app has its own tab bar, so the site menu and footer are usually hidden.', 6 ); ?>
      <?php $t( 'css', 'Extra CSS inside the app', 'Only loads inside the app. Every page has <code>html.mak-app</code> there.', 4 ); ?>
      <tr><th>Delete account</th><td>
        <label><input type="radio" name="mak[delete_mode]" value="now" <?php checked( $s['delete_mode'], 'now' ); ?>> Delete straight away</label><br>
        <label><input type="radio" name="mak[delete_mode]" value="request" <?php checked( $s['delete_mode'], 'request' ); ?>> Email me a request and I delete it</label>
        <p class="description">Page with <code>[mak_delete_account]</code>: <?php wp_dropdown_pages( [ 'name' => 'mak[delete_page]', 'selected' => (int) $s['delete_page'], 'show_option_none' => 'None' ] ); ?></p>
      </td></tr>
      <?php $f( 'ua_tag', 'App tag', 'The app adds this to its user agent. Leave it unless we tell you.' ); ?>
    </table>
    <h2>Marketur</h2>
    <table class="form-table">
      <tr><th><label for="mak_hub_key">App Kit key</label></th><td>
        <input type="password" class="regular-text" id="mak_hub_key" name="mak[hub_key]" value="" autocomplete="off" placeholder="<?php echo $s['hub_key'] ? 'Saved. Paste a new one to replace it.' : 'From your Marketur account'; ?>">
        <?php if ( $s['hub_key'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="mak[hub_key_clear]" value="1"> Remove</label><?php endif; ?>
        <p class="description">Lets this site (and the AI connected to it) start new app builds through Marketur. It is never shown again after saving.</p>
      </td></tr>
    </table>
    <h2>App links</h2>
    <table class="form-table">
      <?php $f( 'bundle_id', 'Bundle id', 'For example com.yoursite.app (from your App Kit order).' ); ?>
      <?php $f( 'android_package', 'Android package', 'Leave empty when it is the same as the bundle id.' ); ?>
      <?php $f( 'apple_team_id', 'Apple Team ID', '10 letters and digits, developer.apple.com > Membership details.' ); ?>
      <?php $t( 'android_sha256', 'Android SHA-256', 'Play Console > App integrity > App signing key certificate SHA-256 (and the upload key one). One per line.', 3 ); ?>
    </table>
    <?php submit_button(); ?>
  </form>
  <p class="description">Marketur App Kit <?php echo esc_html( MAK_VERSION ); ?>. Links: <code><?php echo esc_html( home_url( '/.well-known/apple-app-site-association' ) ); ?></code> and <code><?php echo esc_html( home_url( '/.well-known/assetlinks.json' ) ); ?></code></p>
</div>
        <?php
    }
}

function mak_is_plugin_active( $f ) {
    if ( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    return is_plugin_active( $f );
}

Marketur_App_Kit::instance();
require_once __DIR__ . '/includes/ai-tools.php';
require_once __DIR__ . '/includes/assistant.php';
require_once __DIR__ . '/includes/launch.php';
require_once __DIR__ . '/includes/home.php';
require_once __DIR__ . '/includes/starter.php';
