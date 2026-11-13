<?php
/**
 * Fill it in for me (App Kit 1.6.0).
 *
 * Most of the App Kit settings can be worked out from the site itself or from the details
 * the customer already sent with their order, so nobody should have to type them twice:
 *
 *   order    the app name, bundle id, tabs, colors, first launch screens and store text from
 *            the Marketur order (the form they filled in after paying)
 *   colors   the site's own brand colors (Elementor global colors, then Astra's palette)
 *   tabs     the tab bar from the site's main menu
 *   privacy  publishes the privacy policy page (WordPress's own template when there is none)
 *   delete   makes the Delete Your Account page
 *   home     makes the app home screen
 *   write    the AI writes the store subtitle, description, keywords, home greeting and first
 *            launch screens from what is on the site
 *   all      every one of the above that still needs doing, then switches live settings on
 *
 * Everything is saved straight away and shown on the settings page to look over and change.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Autofill {

    const DEFAULT_TABS = "Home|/|home\nAccount|/my-account/|account";

    public static function init() {
        add_action( 'wp_ajax_mak_auto', [ __CLASS__, 'ajax' ] );
    }

    private static function save( array $changes ) {
        $s = (array) get_option( MAK_OPT, [] );
        foreach ( $changes as $k => $v ) $s[ $k ] = $v;
        $s['updated'] = time();
        update_option( MAK_OPT, $s );
    }

    public static function icon_for( $label ) {
        $l = strtolower( $label );
        $map = [ 'home' => 'home', 'shop' => 'shop', 'store' => 'shop', 'product' => 'shop', 'cart' => 'cart', 'account' => 'account', 'profile' => 'account', 'login' => 'account', 'search' => 'search', 'blog' => 'news', 'news' => 'news', 'article' => 'news', 'chat' => 'chat', 'message' => 'chat', 'community' => 'community', 'forum' => 'community', 'event' => 'calendar', 'book' => 'calendar', 'calendar' => 'calendar', 'schedule' => 'calendar', 'video' => 'play', 'watch' => 'play', 'map' => 'map', 'location' => 'map', 'contact' => 'phone', 'call' => 'phone', 'menu' => 'menu', 'favorite' => 'heart', 'saved' => 'heart', 'about' => 'info', 'service' => 'grid', 'tool' => 'grid', 'pricing' => 'star', 'plan' => 'star' ];
        foreach ( $map as $k => $v ) if ( false !== strpos( $l, $k ) ) return $v;
        return 'star';
    }

    private static function path( $url ) {
        $p = (string) wp_parse_url( $url, PHP_URL_PATH );
        $q = (string) wp_parse_url( $url, PHP_URL_QUERY );
        return ( $p ?: '/' ) . ( $q ? '?' . $q : '' );
    }

    private static function own( $url ) {
        $h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $me = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        return $h === '' || $h === $me || preg_replace( '/^www\./', '', $h ) === preg_replace( '/^www\./', '', $me );
    }

    /* ---------------------------------------------------------------- each job */

    public static function order() {
        $r = MAK_AI_Tools::hub( 'order' );
        if ( is_wp_error( $r ) ) return $r;
        $c = (array) ( $r['customer'] ?? [] );
        if ( ! $c ) return new WP_Error( 'empty', 'Your order has no app details yet.' );
        $ch = [];
        if ( ! empty( $c['name'] ) ) $ch['app_name'] = sanitize_text_field( $c['name'] );
        if ( ! empty( $c['bundle_id'] ) ) $ch['bundle_id'] = sanitize_text_field( $c['bundle_id'] );
        if ( ! empty( $c['android_package'] ) && $c['android_package'] !== ( $c['bundle_id'] ?? '' ) ) $ch['android_package'] = sanitize_text_field( $c['android_package'] );
        if ( ! empty( $c['nav'] ) && isset( Marketur_App_Kit::navs()[ $c['nav'] ] ) ) $ch['nav'] = $c['nav'];
        if ( ! empty( $c['colors'] ) && is_array( $c['colors'] ) ) {
            $cur = Marketur_App_Kit::get( 'colors' );
            foreach ( $c['colors'] as $k => $v ) if ( isset( $cur[ $k ] ) && sanitize_hex_color( $v ) ) $cur[ $k ] = sanitize_hex_color( $v );
            $ch['colors'] = $cur;
        }
        if ( ! empty( $c['tabs'] ) && is_array( $c['tabs'] ) ) {
            $lines = [];
            foreach ( $c['tabs'] as $t ) if ( ! empty( $t['label'] ) && ! empty( $t['url'] ) && self::own( $t['url'] ) ) $lines[] = sanitize_text_field( $t['label'] ) . '|' . self::path( $t['url'] ) . '|' . sanitize_key( $t['icon'] ?? 'star' );
            if ( $lines ) $ch['tabs'] = implode( "\n", array_slice( $lines, 0, 5 ) );
        }
        if ( ! empty( $c['onboarding'] ) && is_array( $c['onboarding'] ) ) {
            $lines = [];
            foreach ( $c['onboarding'] as $p ) if ( ! empty( $p['title'] ) ) $lines[] = sanitize_text_field( $p['title'] ) . '|' . sanitize_text_field( $p['text'] ?? '' );
            if ( $lines ) $ch['onboarding'] = implode( "\n", $lines );
        }
        $st = (array) ( $c['store'] ?? [] );
        foreach ( [ 'subtitle', 'keywords' ] as $k ) if ( ! empty( $st[ $k ] ) ) $ch[ $k ] = sanitize_text_field( $st[ $k ] );
        if ( ! empty( $st['description'] ) ) $ch['description'] = sanitize_textarea_field( $st['description'] );
        if ( ! empty( $st['review']['email'] ) ) $ch['support_email'] = sanitize_email( $st['review']['email'] );
        self::save( $ch );
        return 'Filled in your app name, bundle id, tabs, colors and store text from your order.';
    }

    public static function colors() {
        $primary = $text = '';
        $kit = (int) get_option( 'elementor_active_kit' );
        if ( $kit ) {
            $ps = get_post_meta( $kit, '_elementor_page_settings', true );
            foreach ( (array) ( $ps['system_colors'] ?? [] ) as $c ) {
                if ( ( $c['_id'] ?? '' ) === 'primary' && ! empty( $c['color'] ) ) $primary = $c['color'];
                if ( ( $c['_id'] ?? '' ) === 'text' && ! empty( $c['color'] ) ) $text = $c['color'];
            }
        }
        if ( ! $primary ) {
            $as = get_option( 'astra-settings' );
            $pal = $as['global-color-palette']['palette'] ?? [];
            if ( ! empty( $pal[0] ) ) $primary = $pal[0];
            if ( ! empty( $pal[3] ) ) $text = $text ?: $pal[3];
        }
        $primary = sanitize_hex_color( substr( (string) $primary, 0, 7 ) );
        if ( ! $primary ) return new WP_Error( 'none', 'Could not find your brand colors. Pick them by hand under Colors.' );
        $cur = Marketur_App_Kit::get( 'colors' );
        $cur['active'] = $primary;
        $text = sanitize_hex_color( substr( (string) $text, 0, 7 ) );
        if ( $text ) $cur['ink'] = $text;
        self::save( [ 'colors' => $cur ] );
        return 'Used your site\'s brand color ' . $primary . ' for the app.';
    }

    public static function tabs() {
        $locs = get_nav_menu_locations();
        $menu = 0;
        foreach ( [ 'primary', 'main', 'header', 'menu-1', 'mobile_menu' ] as $k ) if ( ! empty( $locs[ $k ] ) ) { $menu = $locs[ $k ]; break; }
        if ( ! $menu && $locs ) $menu = reset( $locs );
        $items = $menu ? wp_get_nav_menu_items( $menu ) : [];
        $lines = [ 'Home|/|home' ];
        foreach ( (array) $items as $it ) {
            if ( (int) $it->menu_item_parent !== 0 || ! self::own( $it->url ) ) continue;
            $p = self::path( $it->url );
            if ( $p === '/' || $p === '' ) continue;
            $label = wp_strip_all_tags( $it->title );
            if ( $label === '' || mb_strlen( $label ) > 14 ) continue;
            $lines[] = $label . '|' . $p . '|' . self::icon_for( $label );
            if ( count( $lines ) >= 4 ) break;
        }
        $acct = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : ( get_option( 'ihc_general_user_page' ) ? get_permalink( (int) get_option( 'ihc_general_user_page' ) ) : '' );
        if ( $acct && count( $lines ) < 5 && ! preg_grep( '/account/i', $lines ) ) $lines[] = 'Account|' . self::path( $acct ) . '|account';
        if ( count( $lines ) < 2 ) return new WP_Error( 'none', 'Your main menu is empty, so there was nothing to make tabs from.' );
        self::save( [ 'tabs' => implode( "\n", $lines ) ] );
        return 'Made ' . count( $lines ) . ' tabs from your main menu.';
    }

    public static function privacy() {
        $pp = (int) get_option( 'wp_page_for_privacy_policy' );
        if ( $pp && get_post_status( $pp ) === 'publish' ) return 'Your privacy policy page was already set.';
        if ( $pp && get_post( $pp ) ) {
            wp_update_post( [ 'ID' => $pp, 'post_status' => 'publish' ] );
            return 'Published your privacy policy page. Read it over and change anything that does not fit your business.';
        }
        $content = '';
        if ( ! class_exists( 'WP_Privacy_Policy_Content' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
        if ( class_exists( 'WP_Privacy_Policy_Content' ) ) $content = WP_Privacy_Policy_Content::get_default_content();
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Privacy Policy', 'post_content' => $content ?: '<p>Ask the Assistant to write your privacy policy.</p>' ] );
        if ( ! $id || is_wp_error( $id ) ) return new WP_Error( 'fail', 'Could not make the privacy policy page.' );
        update_option( 'wp_page_for_privacy_policy', $id );
        return 'Made and published a privacy policy page from the WordPress template. Read it over and fill in your business details.';
    }

    public static function delete_page() {
        $dp = (int) Marketur_App_Kit::get( 'delete_page' );
        if ( $dp && get_post_status( $dp ) === 'publish' ) return 'Your delete account page was already there.';
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Delete Your Account', 'post_name' => 'delete-account', 'post_content' => '[mak_delete_account]' ] );
        if ( ! $id || is_wp_error( $id ) ) return new WP_Error( 'fail', 'Could not make the delete account page.' );
        self::save( [ 'delete_page' => $id ] );
        return 'Made the Delete Your Account page.';
    }

    public static function home() {
        $had = (int) Marketur_App_Kit::get( 'home_page' ) && get_post_status( (int) Marketur_App_Kit::get( 'home_page' ) ) === 'publish';
        $id = MAK_Home::ensure_page();
        if ( ! $id ) return new WP_Error( 'fail', 'Could not make the app home screen.' );
        return $had ? 'Your app home screen was already there.' : 'Made the app home screen.';
    }

    /** One AI call that writes the store text, greeting and first launch screens. */
    public static function write() {
        $s = Marketur_App_Kit::get();
        $menu = [];
        $locs = get_nav_menu_locations();
        if ( $locs ) foreach ( (array) wp_get_nav_menu_items( reset( $locs ) ) as $it ) if ( (int) $it->menu_item_parent === 0 ) $menu[] = wp_strip_all_tags( $it->title );
        $pages = wp_list_pluck( get_pages( [ 'number' => 20, 'sort_column' => 'menu_order' ] ), 'post_title' );
        $posts = wp_list_pluck( get_posts( [ 'numberposts' => 10 ] ), 'post_title' );
        $front = (int) get_option( 'page_on_front' );
        $about = $front ? wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $front ) ) ), 160 ) : '';
        $products = function_exists( 'wc_get_products' ) ? count( wc_get_products( [ 'limit' => 50, 'return' => 'ids' ] ) ) : 0;
        $brief = "Website: " . home_url( '/' ) . "\nSite name: " . get_bloginfo( 'name' ) . "\nTagline: " . get_bloginfo( 'description' )
            . "\nApp name: " . $s['app_name'] . "\nMain menu: " . implode( ', ', $menu ) . "\nPages: " . implode( ', ', $pages )
            . "\nRecent posts: " . implode( ' | ', $posts ) . ( $products ? "\nProducts for sale: about " . $products : '' )
            . "\nFront page text: " . $about;
        $ask = "Write the app store text for this website's phone app. Use only what the details below say the business does; do not invent features, prices or claims.\n\n" . $brief
            . "\n\nReply with ONLY a JSON object, no other text:\n{\"subtitle\": \"up to 30 characters\", \"description\": \"plain text, 600 to 1200 characters: one short opening paragraph, then a blank line and 4 to 6 lines starting with '- ' saying what people can do in the app\", \"keywords\": \"comma separated, no spaces after commas, up to 95 characters, words not already in the app name\", \"greeting\": \"a short friendly welcome line for the app home screen\", \"onboarding\": [{\"title\": \"up to 30 characters\", \"text\": \"one sentence\"}, {\"title\": \"...\", \"text\": \"...\"}, {\"title\": \"about turning on notifications\", \"text\": \"one sentence\"}]}";
        $r = MAK_AI_Tools::hub( 'assistant', [ 'messages' => [ [ 'role' => 'user', 'content' => $ask ] ], 'tools' => [] ] );
        if ( is_wp_error( $r ) ) return $r;
        $text = '';
        foreach ( (array) ( $r['content'] ?? [] ) as $b ) if ( ( $b['type'] ?? '' ) === 'text' ) $text .= $b['text'];
        $a = strpos( $text, '{' ); $z = strrpos( $text, '}' );
        $j = ( $a !== false && $z ) ? json_decode( substr( $text, $a, $z - $a + 1 ), true ) : null;
        if ( ! is_array( $j ) ) return new WP_Error( 'ai', 'The AI answer could not be read. Try again.' );
        $clean = function ( $t ) { return str_replace( [ "\u{2014}", "\u{2013}" ], [ ',', '-' ], (string) $t ); };
        $ch = [];
        if ( ! empty( $j['subtitle'] ) ) $ch['subtitle'] = mb_substr( sanitize_text_field( $clean( $j['subtitle'] ) ), 0, 30 );
        if ( ! empty( $j['description'] ) ) $ch['description'] = mb_substr( sanitize_textarea_field( $clean( $j['description'] ) ), 0, 4000 );
        if ( ! empty( $j['keywords'] ) ) $ch['keywords'] = mb_substr( preg_replace( '/,\s+/', ',', sanitize_text_field( $j['keywords'] ) ), 0, 100 );
        if ( ! empty( $j['greeting'] ) ) $ch['home_headline'] = sanitize_text_field( $clean( $j['greeting'] ) );
        if ( ! empty( $j['onboarding'] ) && is_array( $j['onboarding'] ) ) {
            $lines = [];
            foreach ( array_slice( $j['onboarding'], 0, 4 ) as $p ) if ( ! empty( $p['title'] ) ) $lines[] = str_replace( '|', '-', sanitize_text_field( $clean( $p['title'] ) ) ) . '|' . str_replace( '|', '-', sanitize_text_field( $clean( $p['text'] ?? '' ) ) );
            if ( $lines ) $ch['onboarding'] = implode( "\n", $lines );
        }
        self::save( $ch );
        return 'The AI wrote your store subtitle, description and keywords, the home screen greeting and the first launch screens. Read them over below.';
    }

    public static function all() {
        $s = Marketur_App_Kit::get();
        $done = [];
        $add = function ( $r ) use ( &$done ) { $done[] = is_wp_error( $r ) ? 'Skipped: ' . $r->get_error_message() : $r; };
        if ( $s['hub_key'] ) $add( self::order() );
        $s = Marketur_App_Kit::get();
        if ( $s['colors']['active'] === Marketur_App_Kit::defaults()['colors']['active'] ) $add( self::colors() );
        if ( trim( (string) $s['tabs'] ) === '' || trim( str_replace( "\r", '', (string) $s['tabs'] ) ) === self::DEFAULT_TABS ) $add( self::tabs() );
        $add( self::privacy() );
        $add( self::delete_page() );
        $add( self::home() );
        if ( $s['hub_key'] && strlen( trim( (string) $s['description'] ) ) < 200 ) $add( self::write() );
        self::save( [ 'live' => 1 ] );
        $done[] = 'Switched live settings on, so changes here reach the app without a new build.';
        return $done;
    }

    public static function ajax() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Only site admins can do this.' ], 403 );
        check_ajax_referer( 'mak_auto', 'nonce' );
        @set_time_limit( 150 );
        $op = sanitize_key( $_POST['op'] ?? '' );
        $map = [ 'order' => 'order', 'colors' => 'colors', 'tabs' => 'tabs', 'privacy' => 'privacy', 'delete' => 'delete_page', 'home' => 'home', 'write' => 'write', 'all' => 'all' ];
        if ( ! isset( $map[ $op ] ) ) wp_send_json_error( [ 'message' => 'Unknown action.' ] );
        $r = call_user_func( [ __CLASS__, $map[ $op ] ] );
        if ( is_wp_error( $r ) ) wp_send_json_error( [ 'message' => $r->get_error_message() ] );
        set_transient( 'mak_auto_done_' . get_current_user_id(), (array) $r, 300 );
        wp_send_json_success( [ 'done' => (array) $r ] );
    }
}

MAK_Autofill::init();
