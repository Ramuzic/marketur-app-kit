<?php
/**
 * Plugin Name: Marketur App Kit
 * Description: Gets a WordPress site ready to be an iPhone and Android app made with the Marketur App Kit. Hides the web menus inside the app, adds the delete account page app stores ask for, serves the app link files, checks the site is ready, and sends the app its live settings (tabs, colors, bar style) so most changes need no new build.
 * Version: 1.6.0
 * Author: Marketur
 * Author URI: https://marketur.net
 * License: GPLv2 or later
 * Text Domain: marketur-app-kit
 */

defined( 'ABSPATH' ) || exit;

define( 'MAK_VERSION', '1.6.0' );
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
            wp_safe_redirect( admin_url( 'admin.php?page=marketur-app-kit&made_home=1' ) ); exit;
        } );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $l ) {
            array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=marketur-app-kit' ) ) . '">Settings</a>' );
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
        wp_safe_redirect( admin_url( 'admin.php?page=marketur-app-kit&made=1' ) ); exit;
    }

    /* ---------------------------------------------------------------- readiness */

    public static function checks() {
        $s = self::get();
        $out = [];
        $add = function ( $ok, $label, $fix = '', $op = '', $later = false ) use ( &$out ) { $out[] = [ 'ok' => $ok, 'label' => $label, 'fix' => $fix, 'op' => $op, 'later' => $later ]; };
        $add( is_ssl() || strpos( home_url(), 'https://' ) === 0, 'The site uses https', 'Apps only load https sites. Turn on SSL with your host.' );
        $pp = (int) get_option( 'wp_page_for_privacy_policy' );
        $add( $pp && get_post_status( $pp ) === 'publish', 'A privacy policy page is published and set', 'Both stores ask for a link to it.', 'privacy' );
        $reg = get_option( 'users_can_register' ) || class_exists( 'WooCommerce' ) || class_exists( 'Ihc_Db' ) || function_exists( 'ihc_is_magic_feat_active' );
        $dp  = (int) $s['delete_page'];
        $add( ! $reg || ( $dp && get_post_status( $dp ) === 'publish' ), $reg ? 'People can make accounts, so there is a delete account page' : 'No public sign-up found (a delete account page is still a good idea)', 'Apple rejects apps where people can sign up but cannot delete their account.', 'delete' );
        $icon = (int) get_option( 'site_icon' );
        $meta = $icon ? wp_get_attachment_metadata( $icon ) : [];
        $add( $icon && ! empty( $meta['width'] ) && $meta['width'] >= 512, 'The site icon is at least 512 x 512', 'Appearance > Customize > Site Identity: a square icon, 1024 x 1024 is best. It becomes the app icon.' );
        $social = [];
        foreach ( [ 'nextend-facebook-connect/nextend-facebook-connect.php' => 'Nextend Social Login', 'miniorange-login-openid/miniorange_openid_sso_settings.php' => 'miniOrange Social Login', 'wordpress-social-login/wp-social-login.php' => 'WordPress Social Login', 'super-socializer/super_socializer.php' => 'Super Socializer' ] as $f => $n ) {
            if ( mak_is_plugin_active( $f ) ) $social[] = $n;
        }
        $add( ! $social, $social ? 'Social sign-in found (' . implode( ', ', $social ) . '): the app needs Sign in with Apple too' : 'No Google or Facebook sign-in found', 'Apple guideline 4.8: offer Sign in with Apple next to other social sign-ins, or hide them inside the app.' );
        $add( (bool) $s['bundle_id'], 'The app has its id (from your order)', 'It comes with your Marketur order.', $s['hub_key'] ? 'order' : '' );
        $add( strlen( wp_strip_all_tags( (string) $s['description'] ) ) >= 200, 'The store description is written', 'It goes on both store pages.', $s['hub_key'] ? 'write' : '' );
        $add( $s['apple_team_id'] && $s['bundle_id'], 'Links to the site open in the iPhone app', 'Optional. Add your Apple Team ID under Advanced once you have your Apple account.', '', true );
        $add( (bool) trim( (string) $s['android_sha256'] ), 'Links to the site open in the Android app', 'Optional. Filled in for you when you make the Android key on the Launch page.', '', true );
        $hp = (int) $s['home_page'];
        $add( $hp && get_post_status( $hp ) === 'publish' && untrailingslashit( (string) $s['start'] ) === untrailingslashit( (string) get_permalink( $hp ) ), 'The app opens on its own home screen', 'Apple turns down apps that are just the website.', 'home' );
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
        add_menu_page( 'Your app', 'App Kit', 'manage_options', 'marketur-app-kit', [ $this, 'page' ], 'dashicons-smartphone', 3 );
        add_submenu_page( 'marketur-app-kit', 'Your app', 'Your app', 'manage_options', 'marketur-app-kit', [ $this, 'page' ] );
    }

    public function save() {
        if ( empty( $_POST['mak_save'] ) || ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'mak_save' );
        $in = wp_unslash( $_POST['mak'] ?? [] );
        $s  = self::get();
        if ( ! empty( $_POST['mak_key_only'] ) ) {
            $hk = trim( sanitize_text_field( $in['hub_key'] ?? '' ) );
            if ( $hk !== '' ) {
                $s['hub_key'] = $hk;
                update_option( MAK_OPT, $s );
                // Fill in what the order already knows straight away.
                if ( class_exists( 'MAK_Autofill' ) ) { $r = MAK_Autofill::order(); set_transient( 'mak_auto_done_' . get_current_user_id(), [ is_wp_error( $r ) ? 'Connected. ' . $r->get_error_message() : 'Connected to Marketur. ' . $r ], 300 ); }
            }
            wp_safe_redirect( admin_url( 'admin.php?page=marketur-app-kit&saved=1' ) ); exit;
        }
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
        wp_safe_redirect( admin_url( 'admin.php?page=marketur-app-kit&saved=1' ) ); exit;
    }

    public function page() {
        $s = self::get();
        $f = function ( $k, $label, $help = '', $type = 'text' ) use ( $s ) {
            echo '<tr><th><label for="mak_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" class="regular-text" id="mak_' . esc_attr( $k ) . '" name="mak[' . esc_attr( $k ) . ']" value="' . esc_attr( $s[ $k ] ) . '">' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . '</td></tr>';
        };
        $t = function ( $k, $label, $help = '', $rows = 4, $extra = '' ) use ( $s ) {
            echo '<tr><th><label for="mak_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="' . (int) $rows . '" id="mak_' . esc_attr( $k ) . '" name="mak[' . esc_attr( $k ) . ']">' . esc_textarea( $s[ $k ] ) . '</textarea>' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . $extra . '</td></tr>';
        };
        $ai = function ( $op, $label ) { return '<button type="button" class="button mak-auto" data-op="' . esc_attr( $op ) . '"><span class="dashicons dashicons-admin-generic" style="margin-top:4px"></span> ' . esc_html( $label ) . '</button>'; };
        $help = function ( $text ) { return '<details class="mak-help"><summary>What is this?</summary><p>' . wp_kses_post( $text ) . '</p></details>'; };
        $ask = function ( $q ) { return '<a class="mak-ask" href="' . esc_url( admin_url( 'admin.php?page=mak-assistant&q=' . rawurlencode( $q ) ) ) . '">Ask the assistant</a>'; };
        $checks = self::checks();
        $todo = array_filter( $checks, function ( $c ) { return ! $c['ok'] && ! $c['later']; } );
        $ready = count( $checks ) - count( $todo );
        $done = get_transient( 'mak_auto_done_' . get_current_user_id() );
        if ( $done ) delete_transient( 'mak_auto_done_' . get_current_user_id() );
        $connected = (bool) $s['hub_key'];
        ?>
<div class="wrap mak-set">
  <h1>Your app</h1>
  <?php if ( isset( $_GET['saved'] ) ) echo '<div class="notice notice-success"><p>Saved.</p></div>'; ?>
  <?php if ( isset( $_GET['made'] ) ) echo '<div class="notice notice-success"><p>The delete account page was made.</p></div>'; ?>
  <?php if ( $done ) echo '<div class="notice notice-success"><p><strong>Done. Look it over below and change anything you like.</strong></p><ul style="list-style:disc;margin-left:20px">' . implode( '', array_map( function ( $d ) { return '<li>' . esc_html( $d ) . '</li>'; }, $done ) ) . '</ul></div>'; ?>

  <?php if ( ! $connected ) : ?>
  <div class="mak-card mak-key">
    <h2>Step 1: connect to Marketur</h2>
    <p>Paste the App Kit key you got after sending your app details. It fills in most of this page for you.</p>
    <form method="post"><?php wp_nonce_field( 'mak_save' ); ?><input type="hidden" name="mak_save" value="1"><input type="hidden" name="mak_key_only" value="1">
      <input type="password" name="mak[hub_key]" class="regular-text" placeholder="mak_..." autocomplete="off"> <button class="button button-primary">Connect</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="mak-card mak-hero">
    <div>
      <h2>Let the AI set it up</h2>
      <p>Fills in everything it can from your order and your site: name, tabs, colors, store text, privacy page, delete account page and the app home screen. Then you look it over. Takes about a minute.</p>
    </div>
    <div><?php echo $ai( 'all', 'Set up my app' ); ?><span class="mak-msg" data-for="all"></span></div>
  </div>

  <div class="mak-card">
    <h2><?php echo count( $todo ) ? 'Left to do: ' . count( $todo ) : 'Ready for the stores'; ?> <span class="mak-sub"><?php echo (int) $ready; ?> of <?php echo count( $checks ); ?> checks pass</span></h2>
    <?php foreach ( $checks as $c ) : if ( $c['ok'] ) continue; ?>
      <div class="mak-chk<?php echo $c['later'] ? ' later' : ''; ?>">
        <span class="mak-ic"><?php echo $c['later'] ? '&#9675;' : '!'; ?></span>
        <div><strong><?php echo esc_html( $c['label'] ); ?></strong><br><span class="description"><?php echo esc_html( $c['fix'] ); ?></span></div>
        <div><?php if ( $c['op'] ) echo $ai( $c['op'], 'Fix it' ) . '<span class="mak-msg" data-for="' . esc_attr( $c['op'] ) . '"></span>'; ?></div>
      </div>
    <?php endforeach; ?>
    <details class="mak-help"><summary>See the <?php echo (int) $ready; ?> that pass</summary><ul><?php foreach ( $checks as $c ) if ( $c['ok'] ) echo '<li>&#10003; ' . esc_html( $c['label'] ) . '</li>'; ?></ul></details>
  </div>

  <form method="post">
    <?php wp_nonce_field( 'mak_save' ); ?>
    <input type="hidden" name="mak_save" value="1">

    <div class="mak-card">
      <h2>How the app looks</h2>
      <?php echo $help( 'This is what people see when they open your app. The name shows under the icon on their phone. The home screen is the first page of the app. Tabs are the buttons along the bottom. The app reads these from your site every time it opens, so you can change them later without a new version.' ); ?>
      <table class="form-table">
        <?php $f( 'app_name', 'App name', 'Up to 30 characters. Under the icon on a phone it shows about 12.' ); ?>
        <tr><th>Home screen</th><td>
          <?php foreach ( ( class_exists( 'MAK_Home' ) ? MAK_Home::templates() : [] ) as $k => $v ) echo '<label style="display:block;margin:0 0 6px"><input type="radio" name="mak[home_template]" value="' . esc_attr( $k ) . '"' . checked( $s['home_template'], $k, false ) . '> ' . esc_html( $v ) . '</label>'; ?>
          <?php $hp = (int) $s['home_page']; if ( $hp && get_post_status( $hp ) === 'publish' ) echo '<p><a class="button" href="' . esc_url( get_permalink( $hp ) ) . '" target="_blank">See it</a></p>'; else echo '<p>' . $ai( 'home', 'Make the home screen' ) . '<span class="mak-msg" data-for="home"></span></p>'; ?>
          <p class="description">Required: Apple turns down apps that are just the website. Built from your own posts, products and links.</p>
        </td></tr>
        <?php $t( 'tabs', 'Bottom tabs', 'One per line: <code>Label|/page-address/|icon</code>, up to 5. Icons: home, shop, cart, account, search, news, chat, community, bell, heart, star, map, phone, calendar, play, grid, info, menu.', 5, '<p>' . $ai( 'tabs', 'Make tabs from my menu' ) . '<span class="mak-msg" data-for="tabs"></span> ' . $ask( 'Which tabs should my app have?' ) . '</p>' ); ?>
        <tr><th><label for="mak_nav">Tab bar style</label></th><td>
          <select id="mak_nav" name="mak[nav]"><?php foreach ( self::navs() as $k => $v ) echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['nav'], $k, false ) . '>' . esc_html( $v ) . '</option>'; ?></select>
        </td></tr>
        <tr><th>Colors</th><td>
          <?php $names = [ 'top' => 'Top bar', 'bar' => 'Tab bar', 'active' => 'Selected tab', 'inactive' => 'Other tabs', 'paper' => 'Background', 'ink' => 'Text', 'muted' => 'Soft text' ];
          foreach ( $s['colors'] as $k => $v ) echo '<label class="mak-col">' . esc_html( $names[ $k ] ?? ucfirst( $k ) ) . '<br><input type="color" name="mak[colors][' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '"></label>'; ?>
          <p><?php echo $ai( 'colors', 'Use my site\'s colors' ); ?><span class="mak-msg" data-for="colors"></span></p>
        </td></tr>
      </table>
    </div>

    <div class="mak-card">
      <h2>The store page</h2>
      <?php echo $help( 'What people read on the App Store and Google Play before they download your app. The subtitle sits under the app name, the description says what the app does, and the keywords help people find it in search (iPhone only, nobody sees them).' ); ?>
      <p><?php echo $ai( 'write', 'Write it with AI' ); ?><span class="mak-msg" data-for="write"></span> <span class="description">Writes the subtitle, description, keywords, home screen greeting and first launch screens from your site.</span></p>
      <table class="form-table">
        <?php $f( 'subtitle', 'Subtitle', 'Up to 30 characters.' ); ?>
        <?php $t( 'description', 'Description', 'Up to 4000 characters.', 8 ); ?>
        <?php $f( 'keywords', 'Keywords', 'Comma separated, up to 100 characters.' ); ?>
        <?php $f( 'support_email', 'Support email', 'Apple\'s reviewers and Google Play show this.', 'email' ); ?>
      </table>
    </div>

    <details class="mak-card mak-adv">
      <summary><h2 style="display:inline">Advanced</h2> <span class="mak-sub">first launch screens, home screen details, what is hidden in the app, links, your key</span></summary>
      <table class="form-table">
        <?php $f( 'start', 'Start page', 'The page the app opens on. Set for you when the home screen is made.' ); ?>
        <tr><th>Live settings</th><td>
          <label><input type="checkbox" name="mak[live]" value="1" <?php checked( (int) $s['live'], 1 ); ?>> The app reads its tabs, colors, tab bar style and start page from here each time it opens</label>
          <p class="description">Turned on for you by Set up my app. With it off, the app keeps what it was built with.</p>
        </td></tr>
        <?php $t( 'onboarding', 'First launch screens', 'Shown once, the first time someone opens the app. One per line: <code>Title|Text</code>. The last one asks to turn on notifications.', 4 ); ?>
        <?php $f( 'home_headline', 'Home screen greeting', 'Visitors see this. Members see Hi and their first name.' ); ?>
        <?php $t( 'home_tiles', 'Home screen shortcuts', 'Up to 8, one per line: <code>Label|/page-address/|icon</code>. Empty uses the tabs.', 4 ); ?>
        <?php $f( 'home_phone', 'Phone (local business look)' ); ?>
        <?php $f( 'home_book', 'Booking link (local business look)' ); ?>
        <?php $f( 'home_address', 'Address (local business look)' ); ?>
        <?php $f( 'home_email', 'Email (local business look)' ); ?>
        <?php $t( 'home_hours', 'Opening hours (local business look)', 'One line per day or range.', 3 ); ?>
        <?php $t( 'hide', 'Hidden inside the app', 'Parts of your site the app hides, because it has its own tabs (one CSS selector per line). The usual header and footer are already here.', 5 ); ?>
        <?php $t( 'css', 'Extra CSS inside the app', 'Only loads inside the app. Pages there have <code>html.mak-app</code>.', 3 ); ?>
        <tr><th>Delete account</th><td>
          <label><input type="radio" name="mak[delete_mode]" value="now" <?php checked( $s['delete_mode'], 'now' ); ?>> Delete straight away</label><br>
          <label><input type="radio" name="mak[delete_mode]" value="request" <?php checked( $s['delete_mode'], 'request' ); ?>> Email me a request and I delete it</label>
          <p class="description">Page: <?php wp_dropdown_pages( [ 'name' => 'mak[delete_page]', 'selected' => (int) $s['delete_page'], 'show_option_none' => 'None' ] ); ?></p>
        </td></tr>
        <tr><th><label for="mak_hub_key">App Kit key</label></th><td>
          <input type="password" class="regular-text" id="mak_hub_key" name="mak[hub_key]" value="" autocomplete="off" placeholder="<?php echo $s['hub_key'] ? 'Saved. Paste a new one to replace it.' : 'From your Marketur order'; ?>">
          <?php if ( $s['hub_key'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="mak[hub_key_clear]" value="1"> Remove</label> <?php echo $ai( 'order', 'Fill in from my order again' ); ?><span class="mak-msg" data-for="order"></span><?php endif; ?>
        </td></tr>
        <?php $f( 'bundle_id', 'App id (bundle id)', 'From your order, for example com.yoursite.app.' ); ?>
        <?php $f( 'android_package', 'Android app id', 'Leave empty when it is the same.' ); ?>
        <?php $f( 'apple_team_id', 'Apple Team ID', 'Optional, so links to your site open in the app. developer.apple.com &gt; Account &gt; Membership details.' ); ?>
        <?php $t( 'android_sha256', 'Android key fingerprint', 'Filled in for you on the Launch page.', 2 ); ?>
        <?php $f( 'ua_tag', 'App tag', 'Leave this unless Marketur asks.' ); ?>
      </table>
      <p class="description">Marketur App Kit <?php echo esc_html( MAK_VERSION ); ?>. <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mak_export' ), 'mak_export' ) ); ?>">Download these settings</a></p>
    </details>

    <p><button class="button button-primary button-hero">Save</button> &nbsp; <a href="<?php echo esc_url( admin_url( 'admin.php?page=mak-launch' ) ); ?>">Next: Launch &rarr;</a></p>
  </form>
</div>
<style>
.mak-set .mak-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px 20px;margin:14px 0;max-width:980px}
.mak-set .mak-card h2{margin:4px 0 8px;font-size:17px}
.mak-set .mak-hero{display:flex;gap:20px;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#f0f6fc,#fff);border-color:#c5d9ed}
.mak-set .mak-hero .mak-auto{font-size:15px;padding:6px 18px;background:#2271b1;color:#fff;border-color:#2271b1}
.mak-set .mak-key{border-color:#dba617;background:#fffbea}
.mak-set .mak-sub{font-weight:400;color:#646970;font-size:13px;margin-left:6px}
.mak-set .mak-chk{display:grid;grid-template-columns:24px 1fr auto;gap:10px;align-items:center;padding:10px 0;border-top:1px solid #f0f0f1}
.mak-set .mak-ic{color:#d97706;font-weight:700;font-size:18px;text-align:center}
.mak-set .later .mak-ic{color:#8c8f94}.mak-set .later strong{color:#50575e;font-weight:500}
.mak-set .mak-help summary{cursor:pointer;color:#2271b1;margin:2px 0 8px}
.mak-set .mak-help p{background:#f6f7f7;border-radius:8px;padding:10px 12px;max-width:760px}
.mak-set .mak-adv>summary{cursor:pointer;list-style:none}
.mak-set .mak-col{display:inline-block;margin:0 14px 8px 0;font-size:12px}
.mak-set .mak-msg{margin-left:8px;font-size:13px}.mak-set .mak-msg.bad{color:#b32d2e}.mak-set .mak-msg.good{color:#007017}
.mak-set .mak-ask{margin-left:10px}
.mak-set .form-table th{width:180px}
</style>
<script>
(function(){
  var url=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce=<?php echo wp_json_encode( wp_create_nonce( 'mak_auto' ) ); ?>;
  document.querySelectorAll('.mak-set .mak-auto').forEach(function(b){b.addEventListener('click',function(){
    var op=b.dataset.op,msg=document.querySelector('.mak-set .mak-msg[data-for="'+op+'"]');
    b.disabled=true;if(msg){msg.className='mak-msg';msg.textContent=op==='all'||op==='write'?'Working on it, this can take a minute...':'Working...';}
    var f=new FormData();f.append('action','mak_auto');f.append('nonce',nonce);f.append('op',op);
    fetch(url,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
      b.disabled=false;
      if(!j||!j.success){if(msg){msg.className='mak-msg bad';msg.textContent=(j&&j.data&&j.data.message)||'That did not work. Try again.';}return;}
      location.href=location.pathname+'?page=marketur-app-kit&auto=1';
    }).catch(function(){b.disabled=false;if(msg){msg.className='mak-msg bad';msg.textContent='Lost the connection. Try again.';}});
  });});
})();
</script>
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
require_once __DIR__ . '/includes/autofill.php';
