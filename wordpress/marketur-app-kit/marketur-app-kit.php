<?php
/**
 * Plugin Name: Marketur App Kit
 * Description: Gets a WordPress site ready to be an iPhone and Android app made with the Marketur App Kit. Hides the web menus inside the app, adds the delete account page app stores ask for, serves the app link files, checks the site is ready, and sends the app its live settings (tabs, colors, bar style) so most changes need no new build.
 * Version: 1.8.1
 * Author: Marketur
 * Author URI: https://marketur.net
 * License: GPLv2 or later
 * Text Domain: marketur-app-kit
 */

defined( 'ABSPATH' ) || exit;

define( 'MAK_VERSION', '1.8.1' );
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
        // The phone preview on the Your app page shows the site the way the app does.
        add_filter( 'show_admin_bar', function ( $v ) { return ! empty( $_GET['mak_preview'] ) ? false : $v; } );
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
            // The NexURLs palette is the starting point; Use my site's colors swaps in the brand.
            'colors'         => [ 'top' => '#F4F0E8', 'bar' => '#121212', 'active' => '#FF5A1F', 'inactive' => '#B9B2A6', 'paper' => '#F4F0E8', 'ink' => '#121212', 'muted' => '#6B665E' ],
            'tabs'           => "Home|/|home\nAccount|/my-account/|account",
            'nav'            => 'floating',
            'onboarding'     => '',
            'live'           => 0,
            'updated'        => 0,
            'hub_key'        => '',
            'home_template'  => 'app',
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
            'site_brief'     => '',
            'look'           => 'app',
            'feel'           => 1,
            'splash'         => 'plain',
            'splash_color'   => '',
            'main_label'     => '',
            'main_sub'       => '',
            'main_url'       => '',
            'main_button'    => '',
            'home_lede'      => '',
            'join_title'     => '',
            'join_text'      => '',
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
        $in  = ( $tag !== '' && stripos( $ua, $tag ) !== false ) || stripos( $ua, 'MarketurApp' ) !== false || ! empty( $_GET['mak_preview'] );
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

    public static function looks() {
        return [
            'app' => [ 'The NexURLs look', 'Native tabs along the bottom, the NexURLs style home screen in your colors, the in-app browser, loading bar and buzzes. Best for getting through the App Store review.' ],
            'pwa' => [ 'My PWA look', 'Your site exactly as PWA PowerUp shows it when installed: its bottom bar, launch animation and app styles. The native tabs are switched off.' ],
        ];
    }

    /** True when the PWA look can be used: PWA PowerUp is installed and its bottom bar is on. */
    public static function has_pwa() {
        if ( ! class_exists( 'Marketur_PWA' ) ) return false;
        $o = (array) get_option( 'mpwa_nav', [] );
        return ( $o['enabled'] ?? '' ) === '1';
    }

    /** The launch screen color: the home screen's background, so opening the app feels seamless (the NexURLs way). */
    public static function splash_color() {
        $s = self::get();
        if ( sanitize_hex_color( $s['splash_color'] ) ) return $s['splash_color'];
        if ( $s['look'] === 'pwa' && class_exists( 'Marketur_PWA' ) ) {
            $o = (array) get_option( 'mpwa_splash', [] );
            $c = ( $o['boot_mode'] ?? '0' ) === '1' ? ( $o['boot_bg1'] ?? '' ) : ( $o['bg_color'] ?? '' );
            if ( sanitize_hex_color( $c ) ) return $c;
        }
        return $s['colors']['paper'];
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
        $nav = $s['look'] === 'pwa' ? 'none' : ( isset( self::navs()[ $s['nav'] ] ) ? $s['nav'] : 'floating' );
        $c = [ 'host' => $host, 'updated' => (int) $s['updated'], 'nav' => $nav, 'colors' => $s['colors'] ];
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
        foreach ( [ 'app_name', 'subtitle', 'keywords', 'home_headline', 'home_phone', 'home_address', 'main_label', 'main_sub', 'main_url', 'main_button', 'home_lede', 'join_title', 'join_text' ] as $k ) if ( isset( $in[ $k ] ) ) $s[ $k ] = sanitize_text_field( $in[ $k ] );
        if ( isset( $in['look'] ) && isset( self::looks()[ $in['look'] ] ) ) $s['look'] = $in['look'];
        if ( isset( $in['splash'] ) && in_array( $in['splash'], [ 'plain', 'logo' ], true ) ) $s['splash'] = $in['splash'];
        if ( isset( $in['splash_color'] ) ) $s['splash_color'] = (string) sanitize_hex_color( $in['splash_color'] );
        if ( isset( $in['home_book'] ) ) $s['home_book'] = esc_url_raw( $in['home_book'] );
        if ( isset( $in['home_email'] ) ) $s['home_email'] = sanitize_email( $in['home_email'] );
        if ( isset( $in['home_template'] ) && class_exists( 'MAK_Home' ) && isset( MAK_Home::templates()[ $in['home_template'] ] ) ) $s['home_template'] = $in['home_template'];
        if ( isset( $in['start'] ) ) $s['start'] = esc_url_raw( $in['start'] );
        foreach ( [ 'tabs', 'onboarding', 'description', 'hide', 'home_tiles', 'home_hours', 'site_brief' ] as $k ) if ( isset( $in[ $k ] ) ) $s[ $k ] = sanitize_textarea_field( $in[ $k ] );
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
        // The PWA look shows the site the way the installed PWA does, header and all; only install prompts are hidden.
        if ( self::get( 'look' ) === 'pwa' ) $hide = array_filter( $hide, function ( $x ) { return stripos( $x, 'install' ) !== false; } );
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
        $add( self::start_is_home(), 'The app opens on its own home screen', 'Apple turns down apps that are just the website.', 'home' );
        $add( ! ( class_exists( 'WooCommerce' ) && self::sells_digital() ), 'No digital products sold on the site', 'You sell downloads or virtual products. Apple wants those bought with in-app purchase inside the iPhone app; ask us before launch.' );
        return $out;
    }

    /** The app opens on a real home screen: App Kit's own, or one the site already has (Marketur App Home, NexURLs App Home). */
    public static function start_is_home() {
        $s = self::get();
        $hp = (int) $s['home_page'];
        if ( $hp && get_post_status( $hp ) === 'publish' && untrailingslashit( (string) $s['start'] ) === untrailingslashit( (string) get_permalink( $hp ) ) ) return true;
        $id = url_to_postid( (string) $s['start'] );
        if ( ! $id || get_post_status( $id ) !== 'publish' ) return false;
        $c = (string) get_post_field( 'post_content', $id );
        foreach ( [ '[mak_app_home', '[marketur_app_home', '[nexurls_app_home' ] as $code ) if ( strpos( $c, $code ) !== false ) return true;
        return false;
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
            'onesignal_app_id' => '', 'nav' => $s['look'] === 'pwa' ? 'none' : $s['nav'], 'colors' => $s['colors'], 'tabs' => array_slice( $tabs, 0, 5 ),
            'splash' => [ 'style' => $s['splash'], 'color' => self::splash_color(), 'dark' => self::splash_color() ],
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
        foreach ( [ 'app_name', 'ua_tag', 'bundle_id', 'android_package', 'apple_team_id', 'subtitle', 'keywords', 'home_headline', 'home_phone', 'home_address', 'main_label', 'main_sub', 'main_url', 'main_button', 'home_lede', 'join_title', 'join_text' ] as $k ) $s[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
        $s['look'] = isset( self::looks()[ $in['look'] ?? '' ] ) ? $in['look'] : 'app';
        $s['feel'] = empty( $in['feel'] ) ? 0 : 1;
        $s['splash'] = ( $in['splash'] ?? '' ) === 'logo' ? 'logo' : 'plain';
        $s['splash_color'] = (string) sanitize_hex_color( $in['splash_color'] ?? '' );
        $s['home_book'] = esc_url_raw( $in['home_book'] ?? '' );
        $s['home_email'] = sanitize_email( $in['home_email'] ?? '' );
        $s['home_tiles'] = sanitize_textarea_field( $in['home_tiles'] ?? '' );
        $s['home_hours'] = sanitize_textarea_field( $in['home_hours'] ?? '' );
        if ( class_exists( 'MAK_Home' ) && isset( MAK_Home::templates()[ $in['home_template'] ?? '' ] ) ) $s['home_template'] = $in['home_template'];
        $s['start'] = esc_url_raw( $in['start'] ?? '' );
        $s['support_email'] = sanitize_email( $in['support_email'] ?? '' );
        foreach ( [ 'hide', 'tabs', 'android_sha256', 'description', 'site_brief' ] as $k ) $s[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
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
        $ai = function ( $op, $label, $big = false ) { return '<button type="button" class="button mak-auto' . ( $big ? ' big' : '' ) . '" data-op="' . esc_attr( $op ) . '">' . MAK_Admin_UI::sparkle( $big ? 18 : 15 ) . '<span>' . esc_html( $label ) . '</span></button>'; };
        $msg = function ( $op ) { return '<span class="mak-msg" data-for="' . esc_attr( $op ) . '"></span>'; };
        $help = function ( $text ) { return '<details class="mak-help"><summary>What is this?</summary><p>' . wp_kses_post( $text ) . '</p></details>'; };
        $ask = function ( $q ) { return '<a class="mak-ask" href="' . esc_url( admin_url( 'admin.php?page=mak-assistant&q=' . rawurlencode( $q ) ) ) . '">Ask the assistant</a>'; };
        $rows = function ( $k, $max, $noun, $help ) use ( $s ) {
            return '<div class="mak-rows" data-target="mak_' . esc_attr( $k ) . '" data-max="' . (int) $max . '" data-noun="' . esc_attr( $noun ) . '"></div>'
                . '<details class="mak-raw"><summary>Edit as text</summary><textarea class="large-text code" rows="5" id="mak_' . esc_attr( $k ) . '" name="mak[' . esc_attr( $k ) . ']">' . esc_textarea( $s[ $k ] ) . '</textarea><p class="description">' . wp_kses_post( $help ) . '</p></details>';
        };
        $checks = self::checks();
        $todo = array_filter( $checks, function ( $c ) { return ! $c['ok'] && ! $c['later']; } );
        $ready = count( $checks ) - count( $todo );
        $done = get_transient( 'mak_auto_done_' . get_current_user_id() );
        if ( $done ) delete_transient( 'mak_auto_done_' . get_current_user_id() );
        $connected = (bool) $s['hub_key'];
        $icon = get_site_icon_url( 128 );
        $start = $s['start'] ?: home_url( '/' );
        ?>
<div class="wrap mak-set">
  <div class="mak-head">
    <span class="mak-appicon"><?php if ( $icon ) echo '<img src="' . esc_url( $icon ) . '" alt="">'; else echo MAK_Admin_UI::svg( 'home', 28 ); ?></span>
    <div class="mak-head-t">
      <h1>Your app</h1>
      <div class="mak-chips">
        <span class="mak-chip <?php echo $connected ? 'ok' : 'warn'; ?>"><?php echo $connected ? 'Connected to Marketur' : 'Not connected yet'; ?></span>
        <span class="mak-chip <?php echo $s['live'] ? 'ok' : ''; ?>"><?php echo $s['live'] ? 'Live settings on' : 'Live settings off'; ?></span>
        <span class="mak-chip <?php echo $todo ? 'warn' : 'ok'; ?>"><?php echo $todo ? count( $todo ) . ' left to do' : 'Ready for the stores'; ?></span>
      </div>
    </div>
    <a class="button button-primary mak-head-go" href="<?php echo esc_url( admin_url( 'admin.php?page=mak-launch' ) ); ?>">Launch &rarr;</a>
  </div>
  <?php if ( isset( $_GET['saved'] ) && ! $done ) echo '<div class="notice notice-success"><p>Saved.</p></div>'; ?>
  <?php if ( isset( $_GET['made'] ) ) echo '<div class="notice notice-success"><p>The delete account page was made.</p></div>'; ?>
  <?php if ( $done ) echo '<div class="notice notice-success mak-done"><p><strong>Done. Look it over below and change anything you like.</strong></p><ul>' . implode( '', array_map( function ( $d ) { return '<li>' . esc_html( $d ) . '</li>'; }, (array) $done ) ) . '</ul></div>'; ?>

  <div class="mak-grid"><div class="mak-main">

  <?php if ( ! $connected ) : ?>
  <div class="mak-card mak-key">
    <h2>Step 1: connect to Marketur</h2>
    <p>Paste the App Kit key you got after sending your app details. It fills in most of this page for you and switches the AI on.</p>
    <form method="post" class="mak-inline"><?php wp_nonce_field( 'mak_save' ); ?><input type="hidden" name="mak_save" value="1"><input type="hidden" name="mak_key_only" value="1">
      <input type="password" name="mak[hub_key]" class="regular-text" placeholder="mak_..." autocomplete="off"> <button class="button button-primary">Connect</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="mak-card mak-hero">
    <div class="mak-hero-t">
      <h2>Let the AI set it up</h2>
      <p>The AI reads your whole site, works out what your business is about, then fills in your app: home screen, tabs, colors, store text, privacy page and delete account page. You look it over after. Takes a minute or two.</p>
      <?php echo $msg( 'all' ); ?>
    </div>
    <div class="mak-hero-b"><?php echo $ai( 'all', 'Set up my app', true ); ?></div>
  </div>

  <div class="mak-card">
    <h2><?php echo $todo ? 'Left to do' : 'Ready for the stores'; ?> <span class="mak-sub"><?php echo (int) $ready; ?> of <?php echo count( $checks ); ?> checks pass</span></h2>
    <?php if ( ! $todo ) echo '<p class="mak-good">Everything the stores need from your site is in place. Next is the Launch page.</p>'; ?>
    <?php foreach ( $checks as $c ) : if ( $c['ok'] ) continue; ?>
      <div class="mak-chk<?php echo $c['later'] ? ' later' : ''; ?>">
        <span class="mak-ic"><?php echo $c['later'] ? '&#9675;' : '!'; ?></span>
        <div><strong><?php echo esc_html( $c['label'] ); ?></strong><span class="description"><?php echo esc_html( $c['fix'] ); ?></span></div>
        <div class="mak-chk-b"><?php if ( $c['op'] ) echo $ai( $c['op'], 'Fix it' ) . $msg( $c['op'] ); ?></div>
      </div>
    <?php endforeach; ?>
    <details class="mak-help"><summary>See the <?php echo (int) $ready; ?> that pass</summary><ul class="mak-pass"><?php foreach ( $checks as $c ) if ( $c['ok'] ) echo '<li>' . esc_html( $c['label'] ) . '</li>'; ?></ul></details>
  </div>

  <form method="post" id="mak-form">
    <?php wp_nonce_field( 'mak_save' ); ?>
    <input type="hidden" name="mak_save" value="1">

    <div class="mak-card">
      <div class="mak-card-h"><h2>What the AI knows about your business</h2><div><?php echo $ai( 'study', $s['site_brief'] ? 'Study my site again' : 'Study my site' ) . $msg( 'study' ); ?></div></div>
      <?php echo $help( 'Before the AI writes anything for your app, it reads your main pages, products and posts, the way a new employee would on day one, and writes down what your business is, who it is for and why people would open your app. Everything it writes afterwards (store text, greeting, tabs, the assistant\'s answers) starts from this note. If it got something wrong or missed something, fix it here and press Save.' ); ?>
      <textarea class="large-text" rows="7" id="mak_site_brief" name="mak[site_brief]" placeholder="Press Study my site and the AI reads your site and writes this for you. Or write a few lines yourself: what you do, who your customers are, and what they would use the app for."><?php echo esc_textarea( $s['site_brief'] ); ?></textarea>
    </div>

    <div class="mak-card">
      <h2>How the app looks</h2>
      <?php echo $help( 'This is what people see when they open your app. The name shows under the icon on their phone. The home screen is the first page of the app. Tabs are the buttons along the bottom. With live settings on, the app picks these up from your site every time it opens, so you can change them later without a new version.' ); ?>
      <div class="mak-looks"><?php foreach ( self::looks() as $k => $v ) {
          $off = $k === 'pwa' && ! self::has_pwa();
          echo '<label class="mak-look' . ( $off ? ' off' : '' ) . '"><input type="radio" name="mak[look]" value="' . esc_attr( $k ) . '"' . checked( $s['look'], $k, false ) . disabled( $off && $s['look'] !== 'pwa', true, false ) . '><strong>' . esc_html( $v[0] ) . '</strong><span>' . esc_html( $v[1] ) . '</span>' . ( $off ? '<em>Needs PWA PowerUp with its bottom bar switched on.</em>' : '' ) . '</label>';
      } ?></div>
      <table class="form-table">
        <?php $f( 'app_name', 'App name', 'Up to 30 characters. Under the icon on a phone it shows about 12.' ); ?>
        <tr><th>Home screen</th><td>
          <div class="mak-tpls"><?php foreach ( ( class_exists( 'MAK_Home' ) ? MAK_Home::templates() : [] ) as $k => $v ) { $p = explode( ':', $v, 2 ); echo '<label class="mak-tpl"><input type="radio" name="mak[home_template]" value="' . esc_attr( $k ) . '"' . checked( $s['home_template'], $k, false ) . '><span class="mak-tpl-i">' . MAK_Admin_UI::svg( [ 'app' => 'sparkles', 'dashboard' => 'grid', 'store' => 'shop', 'feed' => 'news', 'services' => 'phone' ][ $k ] ?? 'star', 22 ) . '</span><strong>' . esc_html( $p[0] ) . '</strong><span>' . esc_html( trim( $p[1] ?? '' ) ) . '</span></label>'; } ?></div>
          <?php $hp = (int) $s['home_page']; if ( $hp && get_post_status( $hp ) === 'publish' ) echo '<p><a class="button" href="' . esc_url( get_permalink( $hp ) ) . '" target="_blank">See the home screen</a></p>'; else echo '<p>' . $ai( 'home', 'Make the home screen' ) . $msg( 'home' ) . '</p>'; ?>
          <p class="description">Required: Apple turns down apps that are just the website. Built from your own posts, products and links. The NexURLs look is the one the NexURLs app opens on.</p>
        </td></tr>
        <tr><th>The big button</th><td>
          <div class="mak-main-btn">
            <input type="text" name="mak[main_sub]" value="<?php echo esc_attr( $s['main_sub'] ); ?>" placeholder="Small line on top, e.g. New this week">
            <input type="text" name="mak[main_label]" value="<?php echo esc_attr( $s['main_label'] ); ?>" placeholder="What it says, e.g. Shop the new drop">
            <input type="text" name="mak[main_url]" value="<?php echo esc_attr( $s['main_url'] ); ?>" placeholder="/shop/ or a full link" list="mak-paths">
            <input type="text" name="mak[main_button]" value="<?php echo esc_attr( $s['main_button'] ); ?>" placeholder="Button word, e.g. Shop">
          </div>
          <p class="description">The one thing your app is for, right under the greeting (NexURLs has Next here). Leave it empty and it is worked out from your site; Set up my app has the AI pick it.</p>
        </td></tr>
        <?php $f( 'home_lede', 'Line for visitors', 'One sentence under the greeting for people who are not signed in.' ); ?>
        <?php $f( 'home_headline', 'Home screen greeting', 'Visitors see this. Members see Hi and their first name.' ); ?>
        <tr><th>Home screen shortcuts</th><td><?php echo $rows( 'home_tiles', 8, 'shortcut', 'One per line: <code>Label|/page-address/|icon</code>. Empty uses the tabs.' ); ?><p class="description">Big buttons on the home screen. Leave empty to show your tabs there.</p></td></tr>
        <tr><th>Bottom tabs</th><td>
          <?php echo $rows( 'tabs', 5, 'tab', 'One per line: <code>Label|/page-address/|icon</code>, up to 5.' ); ?>
          <p class="mak-btns"><?php echo $ai( 'tabs', 'Make tabs from my menu' ) . $msg( 'tabs' ) . $ask( 'Which tabs should my app have?' ); ?></p>
        </td></tr>
        <tr><th><label for="mak_nav">Tab bar style</label></th><td>
          <select id="mak_nav" name="mak[nav]"><?php foreach ( self::navs() as $k => $v ) echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['nav'], $k, false ) . '>' . esc_html( $v ) . '</option>'; ?></select>
        </td></tr>
        <tr><th>Launch screen</th><td>
          <label style="margin-right:16px"><input type="radio" name="mak[splash]" value="plain" <?php checked( $s['splash'], 'plain' ); ?>> Plain, like NexURLs (recommended)</label>
          <label><input type="radio" name="mak[splash]" value="logo" <?php checked( $s['splash'], 'logo' ); ?>> With your icon in the middle</label>
          <p class="mak-splash"><label class="mak-col"><input type="color" name="mak[splash_color]" value="<?php echo esc_attr( self::splash_color() ); ?>"><span>Color</span></label> <span class="description">What shows for a second while the app starts. NexURLs uses its page color, so the home screen seems to just appear. Set for you when the AI picks your colors. Changes here need a new build.</span></p>
        </td></tr>
        <tr><th>Colors</th><td>
          <div class="mak-cols"><?php $names = [ 'top' => 'Top bar', 'bar' => 'Tab bar', 'active' => 'Selected tab', 'inactive' => 'Other tabs', 'paper' => 'Background', 'ink' => 'Text', 'muted' => 'Soft text' ];
          foreach ( $s['colors'] as $k => $v ) echo '<label class="mak-col"><input type="color" name="mak[colors][' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '"><span>' . esc_html( $names[ $k ] ?? ucfirst( $k ) ) . '</span></label>'; ?></div>
          <p class="mak-btns"><?php echo $ai( 'colors', 'Use my site\'s colors' ) . $msg( 'colors' ); ?></p>
        </td></tr>
      </table>
    </div>

    <div class="mak-card">
      <div class="mak-card-h"><h2>The store page</h2><div><?php echo $ai( 'write', 'Write it with AI' ) . $msg( 'write' ); ?></div></div>
      <?php echo $help( 'What people read on the App Store and Google Play before they download your app. The subtitle sits under the app name, the description says what the app does, and the keywords help people find it in search (iPhone only, nobody sees them). Write it with AI also writes the home screen greeting and the first launch screens, from what the AI knows about your business.' ); ?>
      <table class="form-table">
        <?php $f( 'subtitle', 'Subtitle', 'Up to 30 characters.' ); ?>
        <?php $t( 'description', 'Description', 'Up to 4000 characters.', 9 ); ?>
        <?php $f( 'keywords', 'Keywords', 'Comma separated, up to 100 characters.' ); ?>
        <?php $f( 'support_email', 'Support email', 'Apple\'s reviewers and Google Play show this.', 'email' ); ?>
        <?php $t( 'onboarding', 'First launch screens', 'Shown once, the first time someone opens the app. One per line: <code>Title|Text</code>. The last one asks to turn on notifications.', 4 ); ?>
      </table>
    </div>

    <details class="mak-card mak-adv">
      <summary><h2>Advanced</h2><span class="mak-sub">local business details, what is hidden in the app, app ids, your key</span></summary>
      <table class="form-table">
        <?php $f( 'start', 'Start page', 'The page the app opens on. Set for you when the home screen is made. If your site already has its own app home screen (like Marketur App Home), put its address here.' ); ?>
        <tr><th>App feel</th><td>
          <label><input type="checkbox" name="mak[feel]" value="1" <?php checked( (int) $s['feel'], 1 ); ?>> The NexURLs feel inside the app: other websites open in an in-app browser, a loading bar between pages, buzzes when something posts, a rating prompt for happy users</label>
        </td></tr>
        <?php $f( 'join_title', 'Join card title', 'Shown to visitors at the bottom of the home screen. Empty uses Join and your site name.' ); ?>
        <?php $f( 'join_text', 'Join card text' ); ?>
        <tr><th>Live settings</th><td>
          <label><input type="checkbox" name="mak[live]" value="1" <?php checked( (int) $s['live'], 1 ); ?>> The app reads its tabs, colors, tab bar style and start page from here each time it opens</label>
          <p class="description">Turned on for you by Set up my app. With it off, the app keeps what it was built with.</p>
        </td></tr>
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
          <?php if ( $s['hub_key'] ) : ?><label style="margin:0 10px"><input type="checkbox" name="mak[hub_key_clear]" value="1"> Remove</label> <?php echo $ai( 'order', 'Fill in from my order again' ) . $msg( 'order' ); ?><?php endif; ?>
        </td></tr>
        <?php $f( 'bundle_id', 'App id (bundle id)', 'From your order, for example com.yoursite.app.' ); ?>
        <?php $f( 'android_package', 'Android app id', 'Leave empty when it is the same.' ); ?>
        <?php $f( 'apple_team_id', 'Apple Team ID', 'Optional, so links to your site open in the app. developer.apple.com &gt; Account &gt; Membership details.' ); ?>
        <?php $t( 'android_sha256', 'Android key fingerprint', 'Filled in for you on the Launch page.', 2 ); ?>
        <?php $f( 'ua_tag', 'App tag', 'Leave this unless Marketur asks.' ); ?>
      </table>
      <p class="description">Marketur App Kit <?php echo esc_html( MAK_VERSION ); ?>. <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mak_export' ), 'mak_export' ) ); ?>">Download these settings</a></p>
    </details>

    <div class="mak-save"><button class="button button-primary button-hero">Save changes</button> <a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=mak-launch' ) ); ?>">Next: Launch &rarr;</a></div>
  </form>
  </div>

  <aside class="mak-side">
    <div class="mak-phone" data-nav="<?php echo esc_attr( $s['look'] === 'pwa' ? 'none' : $s['nav'] ); ?>">
      <div class="mak-ptop"><span class="mak-pname"></span></div>
      <div class="mak-pscreen"><iframe src="<?php echo esc_url( add_query_arg( 'mak_preview', '1', $start ) ); ?>" title="App preview" loading="lazy"></iframe></div>
      <div class="mak-pbar"></div>
    </div>
    <p class="mak-pnote">Your app, roughly. The tabs and colors change as you edit; the page shows after you save. <a href="<?php echo esc_url( $start ); ?>" target="_blank">Open the start page</a></p>
  </aside>
  </div>
  <?php MAK_Admin_UI::editor_js(); ?>
</div>
<style>
.mak-set .mak-head{display:flex;align-items:center;gap:16px;margin:14px 0 18px;max-width:1320px}
.mak-set .mak-appicon{width:58px;height:58px;border-radius:16px;overflow:hidden;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(16,24,40,.12);flex:none}
.mak-set .mak-appicon img{width:100%;height:100%;object-fit:cover}
.mak-set .mak-head-t{flex:1}.mak-set .mak-head h1{margin:0 0 6px;padding:0}
.mak-set .mak-chips{display:flex;flex-wrap:wrap;gap:6px}
.mak-set .mak-chip{font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;background:#f2f4f7;color:#475467}
.mak-set .mak-chip.ok{background:#ecfdf3;color:#067647}.mak-set .mak-chip.warn{background:#fffaeb;color:#b54708}
.mak-set .mak-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:28px;align-items:start;max-width:1320px}
.mak-set .mak-card{background:#fff;border:1px solid #e4e7ec;border-radius:16px;padding:20px 24px;margin:0 0 16px;box-shadow:0 1px 3px rgba(16,24,40,.06)}
.mak-set .mak-card h2{margin:0 0 6px;font-size:17px;font-weight:700}
.mak-set .mak-card-h{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
.mak-set .mak-card-h>div{text-align:right}
.mak-set .mak-hero{display:flex;gap:24px;align-items:center;background:linear-gradient(135deg,#f5f3ff 0%,#eff6ff 60%,#fff 100%);border-color:#ddd6fe}
.mak-set .mak-hero-t{flex:1}.mak-set .mak-hero-t p{margin:4px 0 0;color:#475467;max-width:640px}
.mak-set .mak-hero-b{flex:none}
.mak-set .mak-key{border-color:#fedf89;background:#fffcf5}.mak-set .mak-inline{display:flex;gap:8px;flex-wrap:wrap}
.mak-set .mak-sub{font-weight:500;color:#667085;font-size:13px;margin-left:8px}
.mak-set .mak-good{background:#ecfdf3;color:#067647;border-radius:10px;padding:10px 14px;font-weight:600}
.mak-set .mak-chk{display:grid;grid-template-columns:28px 1fr auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid #f2f4f7}
.mak-set .mak-chk .description{display:block;margin-top:2px}
.mak-set .mak-chk-b{text-align:right}
.mak-set .mak-ic{width:26px;height:26px;border-radius:50%;background:#fffaeb;color:#dc6803;font-weight:800;display:flex;align-items:center;justify-content:center}
.mak-set .later .mak-ic{background:#f2f4f7;color:#98a2b3}.mak-set .later strong{color:#475467;font-weight:500}
.mak-set .mak-pass{margin:6px 0 0 4px}.mak-set .mak-pass li:before{content:"\2713";color:#12b76a;font-weight:800;margin-right:8px}
.mak-set .mak-help summary{cursor:pointer;color:#2563eb;margin:2px 0 10px;font-weight:500;width:max-content}
.mak-set .mak-help p{background:#f8f9fc;border:1px solid #eaecf0;border-radius:10px;padding:12px 14px;max-width:780px;color:#344054;line-height:1.55}
.mak-set .mak-adv>summary{cursor:pointer;list-style:none;display:flex;align-items:baseline;gap:4px}
.mak-set .mak-adv>summary::-webkit-details-marker{display:none}
.mak-set .mak-adv>summary h2{margin:0}.mak-set .mak-adv>summary:before{content:"\25B8";color:#667085;margin-right:6px}.mak-set .mak-adv[open]>summary:before{content:"\25BE"}
.mak-set .form-table th{width:190px;font-weight:600;color:#344054}
.mak-set .mak-msg{display:block;margin-top:6px;font-size:13px;color:#6d28d9;min-height:0}
.mak-set .mak-msg:empty{display:none}.mak-set .mak-msg.bad{color:#b42318}.mak-set .mak-msg.good{color:#067647}
.mak-set .mak-btns{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.mak-set .mak-btns .mak-msg{margin:0}
.mak-set .mak-ask{font-weight:500}
.mak-set .mak-done ul{list-style:disc;margin:0 0 6px 22px}
.mak-set .mak-tpls{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-bottom:8px}
.mak-set .mak-tpl{position:relative;display:flex;flex-direction:column;gap:3px;padding:12px 14px;border:1.5px solid #e4e7ec;border-radius:12px;cursor:pointer;background:#fff}
.mak-set .mak-tpl input{position:absolute;opacity:0}.mak-set .mak-tpl span:last-child{font-size:12px;color:#667085;line-height:1.4}
.mak-set .mak-tpl-i{color:#667085}.mak-set .mak-tpl:has(input:checked){border-color:#2563eb;background:#f5f8ff;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.mak-set .mak-tpl:has(input:checked) .mak-tpl-i{color:#2563eb}
.mak-set .mak-cols{display:flex;flex-wrap:wrap;gap:10px}
.mak-set .mak-looks{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin:6px 0 4px}
.mak-set .mak-look{position:relative;display:flex;flex-direction:column;gap:4px;padding:14px 16px;border:1.5px solid #e4e7ec;border-radius:14px;cursor:pointer;background:#fff}
.mak-set .mak-look input{position:absolute;opacity:0}.mak-set .mak-look span{font-size:13px;color:#475467;line-height:1.45}.mak-set .mak-look em{font-size:12px;color:#b54708;font-style:normal}
.mak-set .mak-look:has(input:checked){border-color:#2563eb;background:#f5f8ff;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.mak-set .mak-look.off{opacity:.6;cursor:default}
.mak-set .mak-main-btn{display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:640px}.mak-set .mak-main-btn input{width:100%;box-sizing:border-box}
.mak-set .mak-splash{display:flex;align-items:center;gap:12px;margin:8px 0 0}
.mak-set .mak-col{display:flex;flex-direction:column;align-items:center;gap:4px;font-size:12px;color:#475467;width:76px;text-align:center}
.mak-set .mak-col input{width:52px;height:36px;padding:2px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;cursor:pointer}
.mak-set .mak-row{display:grid;grid-template-columns:44px minmax(100px,170px) minmax(0,1fr) auto 32px;gap:8px;align-items:center;margin:0 0 8px;padding:6px;border:1px solid #eaecf0;border-radius:12px;background:#fcfcfd}
.mak-set .mak-row input{width:100%;min-height:38px;box-sizing:border-box;margin:0}
.mak-set .mak-ib{width:44px;height:40px;border-radius:10px;border:1px solid #d0d5dd;background:#fff;color:#2563eb;cursor:pointer;display:flex;align-items:center;justify-content:center}
.mak-set .mak-ib:hover{border-color:#2563eb;background:#f5f8ff}
.mak-set .mak-mv{display:flex}.mak-set .mak-mv button,.mak-set .mak-x{border:0;background:transparent;cursor:pointer;color:#667085;font-size:16px;width:30px;height:34px;border-radius:8px}
.mak-set .mak-mv button:hover,.mak-set .mak-x:hover{background:#f2f4f7;color:#101828}.mak-set .mak-x:hover{color:#b42318}
.mak-set .mak-raw summary{cursor:pointer;color:#667085;font-size:12.5px;margin:4px 0}
.mak-set .mak-card textarea.large-text,.mak-set .mak-card input.regular-text{width:100%;max-width:640px;box-sizing:border-box}.mak-set .mak-card textarea.large-text{max-width:none}
.mak-set .mak-save{position:sticky;bottom:0;z-index:5;display:flex;gap:10px;padding:14px 0;background:linear-gradient(transparent,#f5f6fa 35%)}
.mak-pick{position:absolute;z-index:100000;width:430px;background:#fff;border:1px solid #e4e7ec;border-radius:14px;box-shadow:0 16px 40px rgba(16,24,40,.2);padding:12px}
.mak-pick-h{display:flex;align-items:center;justify-content:space-between;gap:10px;font-weight:700;font-size:13px;margin:0 0 8px;color:#101828}
.mak-pick-q{width:170px;min-height:32px;padding:4px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:13px}
.mak-pick-g{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;max-height:330px;overflow-y:auto}
.mak-pick-g button{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 2px;border:1px solid transparent;border-radius:10px;background:transparent;color:#344054;cursor:pointer;font-size:10.5px}
.mak-pick-g button:hover{background:#f5f8ff;border-color:#bfd2fb;color:#2563eb}
.mak-set .mak-side{position:sticky;top:46px}
.mak-set .mak-phone{position:relative;width:300px;height:620px;border-radius:46px;border:10px solid #101828;background:#fff;overflow:hidden;box-shadow:0 24px 50px rgba(16,24,40,.22)}
.mak-set .mak-ptop{position:absolute;top:0;left:0;right:0;height:30px;z-index:2;display:flex;align-items:flex-end;justify-content:center;font-size:10px;font-weight:700;color:#101828;padding-bottom:3px;box-sizing:border-box}
.mak-set .mak-pscreen{position:absolute;top:30px;left:0;right:0;bottom:0;overflow:hidden}
.mak-set .mak-pscreen iframe{width:390px;height:800px;border:0;transform:scale(.718);transform-origin:0 0;background:#fff}
.mak-set .mak-pbar{position:absolute;z-index:3;display:flex;justify-content:space-around;align-items:center}
.mak-set .mak-pbar span{display:flex;flex-direction:column;align-items:center;gap:2px;font-size:9px;min-width:0}.mak-set .mak-pbar b{font-weight:600;white-space:nowrap;max-width:52px;overflow:hidden;text-overflow:ellipsis}
.mak-set .mak-phone[data-nav=floating] .mak-pbar{left:10px;right:10px;bottom:12px;height:54px;border-radius:24px;box-shadow:0 6px 20px rgba(16,24,40,.22)}
.mak-set .mak-phone[data-nav=fixed] .mak-pbar{left:0;right:0;bottom:0;height:62px;padding-bottom:8px;box-sizing:border-box;border-top:1px solid rgba(16,24,40,.08)}
.mak-set .mak-phone[data-nav=none] .mak-pbar{display:none}
.mak-set .mak-pnote{font-size:12px;color:#667085;width:300px;text-align:center}
@media (max-width:1200px){.mak-set .mak-grid{grid-template-columns:1fr}.mak-set .mak-side{display:none}}
@media (max-width:782px){.mak-set .mak-hero{flex-direction:column;align-items:flex-start}.mak-set .mak-row{grid-template-columns:44px 1fr 32px}.mak-set .mak-row .mak-p{grid-column:1/-1}.mak-set .mak-mv{display:none}}
</style>
<script>
(function(){
  var url=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce=<?php echo wp_json_encode( wp_create_nonce( 'mak_auto' ) ); ?>,connected=<?php echo $connected ? 'true' : 'false'; ?>;
  var words={study:'Reading your site and working out what your business is about. This takes a minute...',all:'Setting up your app...',write:'Writing your store page. This can take a minute...'};
  function call(op,keep){var f=new FormData();f.append('action','mak_auto');f.append('nonce',nonce);f.append('op',op);if(keep)f.append('keep','1');return fetch(url,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();});}
  document.querySelectorAll('.mak-set .mak-auto').forEach(function(b){b.addEventListener('click',function(){
    var op=b.dataset.op,msg=document.querySelector('.mak-set .mak-msg[data-for="'+op+'"]');
    var steps=op==='all'&&connected?['study','all']:[op];
    function say(t,c){if(msg){msg.className='mak-msg'+(c?' '+c:'');msg.textContent=t;}}
    b.disabled=true;b.classList.add('is-busy');
    var i=0,errs=[];
    (function next(){
      var s=steps[i];say(words[s]||'Working on it...');
      call(s,i>0).then(function(j){
        if(!j||!j.success){errs.push((j&&j.data&&j.data.message)||'That did not work.');if(steps.length===1||i===steps.length-1){b.disabled=false;b.classList.remove('is-busy');say(errs.join(' '),'bad');return;}}
        i++;if(i<steps.length)return next();
        location.href=location.pathname+'?page=marketur-app-kit&auto=1';
      }).catch(function(){b.disabled=false;b.classList.remove('is-busy');say('Lost the connection. Try again.','bad');});
    })();
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
require_once __DIR__ . '/includes/admin-ui.php';
require_once __DIR__ . '/includes/ai-tools.php';
require_once __DIR__ . '/includes/assistant.php';
require_once __DIR__ . '/includes/launch.php';
require_once __DIR__ . '/includes/home.php';
require_once __DIR__ . '/includes/home-app.php';
require_once __DIR__ . '/includes/app-feel.php';
require_once __DIR__ . '/includes/starter.php';
require_once __DIR__ . '/includes/autofill.php';
