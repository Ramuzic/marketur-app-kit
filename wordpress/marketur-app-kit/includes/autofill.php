<?php
/**
 * Fill it in for me (App Kit 1.6.0, the site study 1.7.0, the NexURLs look 1.8.0).
 *
 * Most of the App Kit settings can be worked out from the site itself or from the details
 * the customer already sent with their order, so nobody should have to type them twice:
 *
 *   order    the app name, bundle id, tabs, colors, first launch screens and store text from
 *            the Marketur order (the form they filled in after paying)
 *   colors   the site's own brand colors (Elementor global colors, then Astra's palette), laid out
 *            the NexURLs way: a warm paper page, a dark tab bar, the brand as the signal color,
 *            and a plain launch screen in the page color
 *   tabs     the tab bar from the site's main menu
 *   privacy  publishes the privacy policy page (WordPress's own template when there is none)
 *   delete   makes the Delete Your Account page
 *   home     makes the app home screen
 *   write    the AI writes the store subtitle, description, keywords, home greeting and first
 *            launch screens from what is on the site
 *   study    the AI reads the main pages, products and posts and writes down what the business
 *            is, who it is for and what the app should put up front (saved as site_brief)
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
        $primary = $text = $page = '';
        $kit = (int) get_option( 'elementor_active_kit' );
        if ( $kit ) {
            $ps = get_post_meta( $kit, '_elementor_page_settings', true );
            foreach ( (array) ( $ps['system_colors'] ?? [] ) as $c ) {
                if ( ( $c['_id'] ?? '' ) === 'primary' && ! empty( $c['color'] ) ) $primary = $c['color'];
                if ( ( $c['_id'] ?? '' ) === 'text' && ! empty( $c['color'] ) ) $text = $c['color'];
                if ( ( $c['_id'] ?? '' ) === 'accent' && ! empty( $c['color'] ) && ! $primary ) $primary = $c['color'];
            }
            if ( ! empty( $ps['body_background_color'] ) ) $page = $ps['body_background_color'];
        }
        if ( ! $primary ) {
            $as = get_option( 'astra-settings' );
            $pal = $as['global-color-palette']['palette'] ?? [];
            if ( ! empty( $pal[0] ) ) $primary = $pal[0];
            if ( ! empty( $pal[3] ) ) $text = $text ?: $pal[3];
            if ( ! empty( $pal[4] ) ) $page = $page ?: $pal[4];
        }
        $primary = sanitize_hex_color( substr( (string) $primary, 0, 7 ) );
        if ( ! $primary ) return new WP_Error( 'none', 'Could not find your brand colors. Pick them by hand under Colors.' );
        $mix = [ 'MAK_Home_App', 'mix' ];
        $light = [ 'MAK_Home_App', 'light' ];
        $ink = sanitize_hex_color( substr( (string) $text, 0, 7 ) );
        if ( ! $ink || call_user_func( $light, $ink ) ) $ink = '#121212';
        // A warm paper page with a hint of the brand, unless the site has its own soft page color.
        $page = sanitize_hex_color( substr( (string) $page, 0, 7 ) );
        $paper = ( $page && call_user_func( $light, $page ) && strtoupper( $page ) !== '#FFFFFF' ) ? $page : call_user_func( $mix, '#F6F3EE', $primary, .05 );
        $bar = call_user_func( $mix, $ink, '#000000', .1 );
        $cur = [
            'top' => $paper, 'bar' => $bar, 'active' => $primary, 'inactive' => call_user_func( $mix, $paper, $bar, .32 ),
            'paper' => $paper, 'ink' => $ink, 'muted' => call_user_func( $mix, $ink, $paper, .5 ),
        ];
        self::save( [ 'colors' => $cur, 'splash_color' => '' ] );
        return 'Used your site\'s brand color ' . $primary . ' the NexURLs way: a soft page, a dark tab bar and a plain launch screen in the page color.';
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

    /* ---------------------------------------------------------------- studying the site */

    /** What a visitor reads on a page: the page as it is served, or the builder's text when that cannot be loaded. */
    private static function page_text( $id, $words = 350 ) {
        $text = '';
        $r = wp_remote_get( get_permalink( $id ), [ 'timeout' => 6, 'redirection' => 2, 'sslverify' => apply_filters( 'https_local_ssl_verify', false ) ] );
        if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) {
            $h = (string) wp_remote_retrieve_body( $r );
            if ( preg_match( '#<main\b[^>]*>(.*)</main>#is', $h, $m ) ) $h = $m[1];
            elseif ( preg_match( '#<body\b[^>]*>(.*)</body>#is', $h, $m ) ) $h = $m[1];
            $h = preg_replace( '#<(script|style|noscript|svg|nav|header|footer|form|iframe|template)\b[^>]*>.*?</\1>#is', ' ', $h );
            $text = wp_strip_all_tags( $h );
        }
        if ( mb_strlen( trim( $text ) ) < 80 ) {
            $bits = [];
            $raw = get_post_meta( $id, '_elementor_data', true );
            $d = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
            if ( is_array( $d ) ) {
                $keys = [ 'title', 'editor', 'text', 'description_text', 'title_text', 'tab_title', 'tab_content', 'testimonial_content', 'item_description', 'heading', 'button_text', 'price', 'alert_description' ];
                array_walk_recursive( $d, function ( $v, $k ) use ( &$bits, $keys ) { if ( is_string( $v ) && in_array( $k, $keys, true ) ) $bits[] = wp_strip_all_tags( $v ); } );
            }
            $text = implode( ' ', $bits ) . ' ' . wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) );
        }
        $text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
        return wp_trim_words( $text, $words, '' );
    }

    /** Every page address a tab or shortcut may point at. */
    private static function known_paths() {
        $known = class_exists( 'MAK_Admin_UI' ) ? MAK_Admin_UI::paths() : [ '/' => 'Home page' ];
        $locs = get_nav_menu_locations();
        foreach ( $locs as $m ) foreach ( (array) wp_get_nav_menu_items( $m ) as $it ) if ( self::own( $it->url ) ) $known[ self::path( $it->url ) ] = wp_strip_all_tags( $it->title );
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            foreach ( [ 'shop' => 'Shop', 'cart' => 'Cart', 'myaccount' => 'My account' ] as $k => $t ) { $u = wc_get_page_permalink( $k ); if ( $u ) $known[ self::path( $u ) ] = $t; }
            foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 20 ] ) as $t ) if ( ! is_wp_error( $t ) ) $known[ self::path( get_term_link( $t ) ) ] = 'Products: ' . $t->name;
        }
        return $known;
    }

    private static function clean_links( $list, array $known, $max ) {
        $icons = class_exists( 'MAK_Admin_UI' ) ? MAK_Admin_UI::icons() : [];
        $out = [];
        foreach ( (array) $list as $t ) {
            $label = str_replace( '|', '-', sanitize_text_field( $t['label'] ?? '' ) );
            $p = (string) ( $t['path'] ?? '' );
            if ( preg_match( '#^https?://#', $p ) ) { if ( ! self::own( $p ) ) continue; $p = self::path( $p ); }
            if ( $label === '' || ! isset( $known[ $p ] ) ) continue;
            $icon = sanitize_key( $t['icon'] ?? '' );
            if ( ! isset( $icons[ $icon ] ) ) $icon = self::icon_for( $label );
            $out[] = mb_substr( $label, 0, 14 ) . '|' . $p . '|' . $icon;
            if ( count( $out ) >= $max ) break;
        }
        return $out;
    }

    /**
     * The AI reads the site the way a new employee would (the main pages, products, posts and
     * menu) and writes down what the business is, who it is for and what the app should put
     * up front. That note is saved on the settings page, where the owner can correct it, and
     * everything the AI writes later starts from it.
     */
    public static function study() {
        $t0 = microtime( true );
        $s = Marketur_App_Kit::get();
        $front = (int) get_option( 'page_on_front' );
        $skip = array_filter( [ (int) get_option( 'wp_page_for_privacy_policy' ), (int) $s['delete_page'], (int) $s['home_page'] ] );
        if ( function_exists( 'wc_get_page_id' ) ) foreach ( [ 'cart', 'checkout', 'myaccount' ] as $k ) $skip[] = (int) wc_get_page_id( $k );
        $in_menu = [];
        foreach ( get_nav_menu_locations() as $m ) foreach ( (array) wp_get_nav_menu_items( $m ) as $it ) if ( $it->object === 'page' ) $in_menu[ (int) $it->object_id ] = 1;
        $scored = [];
        foreach ( get_pages( [ 'post_status' => 'publish', 'number' => 80, 'sort_column' => 'menu_order' ] ) as $p ) {
            if ( in_array( (int) $p->ID, $skip, true ) ) continue;
            $sc = 0;
            if ( (int) $p->ID === $front ) $sc += 100;
            if ( preg_match( '/about|service|pricing|price|plan|product|shop|menu|contact|faq|team|book|location|work|portfolio|course|program|offer|feature|how/i', $p->post_name . ' ' . $p->post_title ) ) $sc += 50;
            if ( isset( $in_menu[ $p->ID ] ) ) $sc += 30;
            $scored[ $p->ID ] = $sc;
        }
        arsort( $scored );
        $pages = [];
        foreach ( array_slice( array_keys( $scored ), 0, 8 ) as $i => $id ) {
            if ( $i > 0 && microtime( true ) - $t0 > 35 ) break;
            $txt = self::page_text( $id, $id === $front ? 500 : 300 );
            if ( $txt !== '' ) $pages[] = '## ' . get_the_title( $id ) . ' (' . self::path( get_permalink( $id ) ) . ")\n" . $txt;
        }
        $posts = [];
        foreach ( get_posts( [ 'numberposts' => 12 ] ) as $p ) $posts[] = '- ' . $p->post_title . ': ' . wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 25, '' );
        $products = [];
        if ( function_exists( 'wc_get_products' ) ) {
            foreach ( wc_get_products( [ 'limit' => 25, 'status' => 'publish' ] ) as $pr ) {
                $cats = wp_list_pluck( get_the_terms( $pr->get_id(), 'product_cat' ) ?: [], 'name' );
                $products[] = '- ' . $pr->get_name() . ( $pr->get_price() !== '' ? ' (' . html_entity_decode( wp_strip_all_tags( wc_price( $pr->get_price() ) ) ) . ')' : '' ) . ( $cats ? ' [' . implode( ', ', $cats ) . ']' : '' ) . ': ' . wp_trim_words( wp_strip_all_tags( $pr->get_short_description() ?: $pr->get_description() ), 20, '' );
            }
        }
        $known = self::known_paths();
        $paths = [];
        foreach ( $known as $p => $t ) $paths[] = $p . ' = ' . $t;
        $all = implode( ' ', $pages );
        preg_match_all( '/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]\d{3}[\s.-]\d{4}/', $all, $ph );
        preg_match_all( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $all, $em );
        $facts = "Website: " . home_url( '/' ) . "\nSite name: " . get_bloginfo( 'name' ) . "\nTagline: " . get_bloginfo( 'description' )
            . "\nShop: " . ( class_exists( 'WooCommerce' ) ? 'yes, ' . count( $products ) . ' products shown below' : 'no online shop' )
            . "\nMembers can sign up: " . ( get_option( 'users_can_register' ) || class_exists( 'Ihc_Db' ) || class_exists( 'WooCommerce' ) ? 'yes' : 'not that we can see' )
            . ( $ph[0] ? "\nPhone numbers seen: " . implode( ', ', array_unique( array_slice( $ph[0], 0, 3 ) ) ) : '' )
            . ( $em[0] ? "\nEmails seen: " . implode( ', ', array_unique( array_slice( $em[0], 0, 3 ) ) ) : '' );
        $ask = "You are setting up a phone app for this website. Study the site below carefully, the way a new employee would on their first day, and work out exactly what this business is and does.\n\n"
            . $facts . "\n\nPAGES (what visitors read):\n" . implode( "\n\n", $pages )
            . ( $products ? "\n\nPRODUCTS:\n" . implode( "\n", $products ) : '' )
            . ( $posts ? "\n\nRECENT POSTS:\n" . implode( "\n", $posts ) : '' )
            . "\n\nPAGE ADDRESSES THAT EXIST (use only these for links):\n" . implode( "\n", array_slice( $paths, 0, 120 ) )
            . "\n\nIcons you can use: " . implode( ', ', array_keys( class_exists( 'MAK_Admin_UI' ) ? MAK_Admin_UI::icons() : [] ) )
            . "\n\nUse only what the site says. Do not invent services, prices, places or claims. If something is not on the site, leave it empty. Plain words, no em dashes.\n"
            . "Reply with ONLY a JSON object, no other text:\n"
            . "{\"business\": \"3 to 5 sentences: what the business is, what it sells or does, how it makes money, and where it is if local\", \"audience\": \"who the customers are, one sentence\", \"offers\": [\"the main things it sells or offers, up to 8\"], \"tone\": \"how the site talks, a few words\", \"app_reasons\": \"one or two sentences: what people would open the app to do, most often first\", \"home_template\": \"dashboard (most sites), store (sells products online), feed (mostly articles or news) or services (a local business people call, book or visit)\", \"tabs\": [{\"label\": \"short, up to 12 characters\", \"path\": \"an address from the list\", \"icon\": \"an icon name\"}], \"shortcuts\": [{\"label\": \"...\", \"path\": \"...\", \"icon\": \"...\"}], \"phone\": \"\", \"email\": \"\", \"address\": \"\", \"hours\": \"\", \"booking_url\": \"\"}\n"
            . "Tabs: 3 to 5, the first is Home with path /, the rest are what app users need most, and add an account tab when members can sign up. Shortcuts: 4 to 6 other useful pages that are not tabs."
            . ' Put these keys in the same JSON object too: "main": {"sub": "a short line above the big button, under 30 characters", "label": "the one thing people most come to do, 2 to 5 words, like Shop the new drop or Book a session", "path": "an address from the list, or the booking link, or tel: and the phone number", "button": "one short word on the button, like Shop, Book, Read, Go"}, "lede": "one friendly sentence for visitors who are not signed in, under 140 characters", "join": {"title": "a short title inviting visitors to make an account, only if members can sign up", "text": "one sentence on what an account gives them"}.';
        $r = MAK_AI_Tools::hub( 'assistant', [ 'messages' => [ [ 'role' => 'user', 'content' => $ask ] ], 'tools' => [] ], 100 );
        if ( is_wp_error( $r ) ) return $r;
        $text = '';
        foreach ( (array) ( $r['content'] ?? [] ) as $b ) if ( ( $b['type'] ?? '' ) === 'text' ) $text .= $b['text'];
        $a = strpos( $text, '{' ); $z = strrpos( $text, '}' );
        $j = ( $a !== false && $z ) ? json_decode( substr( $text, $a, $z - $a + 1 ), true ) : null;
        if ( ! is_array( $j ) || empty( $j['business'] ) ) return new WP_Error( 'ai', 'The AI could not make sense of the site this time. Try again.' );
        $clean = function ( $t ) { return trim( str_replace( [ "\u{2014}", "\u{2013}" ], [ ',', '-' ], sanitize_textarea_field( (string) $t ) ) ); };
        $offers = array_filter( array_map( $clean, array_slice( (array) ( $j['offers'] ?? [] ), 0, 8 ) ) );
        $brief = $clean( $j['business'] )
            . ( ! empty( $j['audience'] ) ? "\n\nWho it is for: " . $clean( $j['audience'] ) : '' )
            . ( $offers ? "\nWhat it offers: " . implode( ', ', $offers ) : '' )
            . ( ! empty( $j['tone'] ) ? "\nHow it talks: " . $clean( $j['tone'] ) : '' )
            . ( ! empty( $j['app_reasons'] ) ? "\nWhy people open the app: " . $clean( $j['app_reasons'] ) : '' );
        $tpl = sanitize_key( $j['home_template'] ?? '' );
        $sug = [
            'when'      => time(),
            'template'  => class_exists( 'MAK_Home' ) && isset( MAK_Home::templates()[ $tpl ] ) ? $tpl : '',
            'tabs'      => self::clean_links( $j['tabs'] ?? [], $known, 5 ),
            'shortcuts' => self::clean_links( $j['shortcuts'] ?? [], $known, 6 ),
            'phone'     => $clean( $j['phone'] ?? '' ),
            'email'     => sanitize_email( $j['email'] ?? '' ),
            'address'   => $clean( $j['address'] ?? '' ),
            'hours'     => $clean( $j['hours'] ?? '' ),
            'book'      => esc_url_raw( $j['booking_url'] ?? '' ),
            'main'      => self::clean_main( (array) ( $j['main'] ?? [] ), $known, $clean ),
            'lede'      => mb_substr( $clean( $j['lede'] ?? '' ), 0, 160 ),
            'join'      => [ 'title' => mb_substr( $clean( $j['join']['title'] ?? '' ), 0, 60 ), 'text' => mb_substr( $clean( $j['join']['text'] ?? '' ), 0, 200 ) ],
        ];
        update_option( 'mak_study', $sug, false );
        self::save( [ 'site_brief' => $brief ] );
        self::apply_study( false );
        return 'The AI studied ' . count( $pages ) . ' pages' . ( $products ? ', ' . count( $products ) . ' products' : '' ) . ( $posts ? ' and ' . count( $posts ) . ' posts' : '' ) . ' and wrote down what your business is about. Read it under What the AI knows, and fix anything it got wrong.';
    }

    private static function clean_main( array $m, array $known, $clean ) {
        $p = trim( (string) ( $m['path'] ?? '' ) );
        if ( preg_match( '#^tel:[0-9+ ()-]{6,}$#', $p ) ) $p = 'tel:' . preg_replace( '/[^0-9+]/', '', substr( $p, 4 ) );
        elseif ( preg_match( '#^https?://#', $p ) ) $p = self::own( $p ) ? self::path( $p ) : esc_url_raw( $p );
        if ( strpos( $p, '/' ) === 0 && ! isset( $known[ $p ] ) ) $p = '';
        $label = mb_substr( $clean( $m['label'] ?? '' ), 0, 40 );
        if ( $p === '' || $label === '' ) return [];
        return [ 'sub' => mb_substr( $clean( $m['sub'] ?? '' ), 0, 40 ), 'label' => $label, 'url' => $p, 'button' => mb_substr( $clean( $m['button'] ?? '' ), 0, 12 ) ];
    }

    /**
     * Uses what the study found. Fills only what is still empty or still the plugin's default,
     * unless $all is true (Set up my app), which also picks the home screen look and the tabs
     * when those have not been changed from the defaults.
     */
    public static function apply_study( $all = true ) {
        $g = (array) get_option( 'mak_study', [] );
        if ( ! $g ) return [];
        $s = Marketur_App_Kit::get();
        $ch = []; $done = [];
        if ( $all && in_array( $s['home_template'], [ 'dashboard', '' ], true ) ) { $ch['home_template'] = 'app'; $done[] = 'Switched the home screen to the NexURLs look.'; }
        if ( ! empty( $g['main'] ) && ( trim( (string) $s['main_label'] ) === '' || trim( (string) $s['main_url'] ) === '' ) ) {
            $ch['main_label'] = $g['main']['label']; $ch['main_url'] = $g['main']['url']; $ch['main_sub'] = $g['main']['sub']; $ch['main_button'] = $g['main']['button'];
            $done[] = 'Made the big button: ' . $g['main']['label'] . '.';
        }
        if ( ! empty( $g['lede'] ) && trim( (string) $s['home_lede'] ) === '' ) $ch['home_lede'] = $g['lede'];
        if ( ! empty( $g['join']['title'] ) && trim( (string) $s['join_title'] ) === '' ) { $ch['join_title'] = $g['join']['title']; $ch['join_text'] = $g['join']['text']; }
        $tabs_default = trim( (string) $s['tabs'] ) === '' || trim( str_replace( "\r", '', (string) $s['tabs'] ) ) === self::DEFAULT_TABS;
        if ( $all && $tabs_default && count( $g['tabs'] ) >= 2 ) { $ch['tabs'] = implode( "\n", $g['tabs'] ); $done[] = 'Made ' . count( $g['tabs'] ) . ' tabs for what your app users need most.'; }
        if ( trim( (string) $s['home_tiles'] ) === '' && $g['shortcuts'] ) { $ch['home_tiles'] = implode( "\n", $g['shortcuts'] ); $done[] = 'Added ' . count( $g['shortcuts'] ) . ( count( $g['shortcuts'] ) === 1 ? ' shortcut' : ' shortcuts' ) . ' to the home screen.'; }
        foreach ( [ 'phone' => 'home_phone', 'email' => 'home_email', 'address' => 'home_address', 'hours' => 'home_hours', 'book' => 'home_book' ] as $from => $to ) if ( ! empty( $g[ $from ] ) && trim( (string) $s[ $to ] ) === '' ) $ch[ $to ] = $g[ $from ];
        if ( $ch ) self::save( $ch );
        return $done;
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
        if ( trim( (string) $s['site_brief'] ) !== '' ) $brief = "What the business is about (studied from the whole site and checked by the owner):\n" . $s['site_brief'] . "\n\n" . $brief;
        $ask = "Write the app store text for this website's phone app. Write it for the people described below, in the way the business talks. Use only what the details below say the business does; do not invent features, prices or claims.\n\n" . $brief
            . "\n\nReply with ONLY a JSON object, no other text:\n{\"subtitle\": \"up to 30 characters\", \"description\": \"plain text, 600 to 1200 characters: one short opening paragraph, then a blank line and 4 to 6 lines starting with '- ' saying what people can do in the app\", \"keywords\": \"comma separated, no spaces after commas, up to 95 characters, words not already in the app name\", \"greeting\": \"a short friendly welcome line for the app home screen\", \"onboarding\": [{\"title\": \"up to 30 characters\", \"text\": \"one sentence\"}, {\"title\": \"...\", \"text\": \"...\"}, {\"title\": \"about turning on notifications\", \"text\": \"one sentence\"}]}";
        $r = MAK_AI_Tools::hub( 'assistant', [ 'messages' => [ [ 'role' => 'user', 'content' => $ask ] ], 'tools' => [] ], 100 );
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
        // What the study found (the page runs Study first, then this).
        foreach ( self::apply_study( true ) as $d ) $done[] = $d;
        $s = Marketur_App_Kit::get();
        // Still the starting colors (this version's or an older one's): use the brand, laid out the NexURLs way.
        if ( in_array( strtoupper( $s['colors']['active'] ), [ '#FF5A1F', '#2563EB' ], true ) ) $add( self::colors() );
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
        $map = [ 'study' => 'study', 'order' => 'order', 'colors' => 'colors', 'tabs' => 'tabs', 'privacy' => 'privacy', 'delete' => 'delete_page', 'home' => 'home', 'write' => 'write', 'all' => 'all' ];
        if ( ! isset( $map[ $op ] ) ) wp_send_json_error( [ 'message' => 'Unknown action.' ] );
        $r = call_user_func( [ __CLASS__, $map[ $op ] ] );
        if ( is_wp_error( $r ) ) wp_send_json_error( [ 'message' => $r->get_error_message() ] );
        $prev = ! empty( $_POST['keep'] ) ? (array) get_transient( 'mak_auto_done_' . get_current_user_id() ) : [];
        set_transient( 'mak_auto_done_' . get_current_user_id(), array_merge( array_filter( $prev ), (array) $r ), 300 );
        wp_send_json_success( [ 'done' => (array) $r ] );
    }
}

MAK_Autofill::init();
