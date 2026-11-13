<?php
/**
 * The NexURLs look for any site (App Kit 1.8.0).
 *
 * The home screen the NexURLs app opens on, rebuilt so it works on any WordPress site: a dark
 * hero with a glow in the brand color, a greeting by the visitor's own clock, the member's
 * name, plan and numbers, one big main button, a card of colored shortcuts members can
 * rearrange, swipeable rows of products and posts, colored topic cards, the community, the
 * opening hours and a join card for visitors. Sections ease in as you scroll and numbers count
 * up. Every section comes from the site's own content and is left out when there is nothing
 * to show, so it fits a shop, a local business, a blog or a membership site.
 *
 * Colors come from the App Kit settings: paper (the page), ink (text and the hero), and the
 * selected tab color as the signal color, the way NexURLs uses its orange.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Home_App {

    private static $used = false;

    /** The App Home page, showing this look. */
    private static function here() {
        $p = (int) Marketur_App_Kit::get( 'home_page' );
        return $p && is_page( $p ) && Marketur_App_Kit::get( 'home_template' ) === 'app';
    }

    public static function init() {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'mak/v1', '/tiles', [
                'methods'             => 'POST',
                'permission_callback' => 'is_user_logged_in',
                'callback'            => function ( $req ) {
                    $ok  = array_keys( self::catalog() );
                    $ids = [];
                    foreach ( (array) $req->get_param( 'tiles' ) as $t ) {
                        $t = sanitize_key( (string) $t );
                        if ( in_array( $t, $ok, true ) && ! in_array( $t, $ids, true ) ) $ids[] = $t;
                    }
                    $ids = array_slice( $ids, 0, 8 );
                    if ( $ids ) update_user_meta( get_current_user_id(), 'mak_tiles', $ids );
                    else delete_user_meta( get_current_user_id(), 'mak_tiles' );
                    return rest_ensure_response( [ 'tiles' => $ids ] );
                },
            ] );
        } );
        add_filter( 'body_class', function ( $c ) {
            $p = (int) Marketur_App_Kit::get( 'home_page' );
            if ( $p && is_page( $p ) && Marketur_App_Kit::get( 'home_template' ) === 'app' ) $c[] = 'mka-page';
            return $c;
        } );
        add_action( 'wp_enqueue_scripts', function () {
            if ( self::here() ) wp_enqueue_style( 'mka-font', 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap', [], null );
        } );
        // Printed outside the page content, so WordPress's content filters never touch the CSS or the script.
        add_action( 'wp_head', function () { if ( self::here() ) echo self::css(); }, 20 );
        add_action( 'wp_footer', function () { if ( self::here() || self::$used ) echo self::css() . self::js(); }, 20 );
    }

    /* ---------------------------------------------------------------- colors */

    private static function rgb( $hex ) {
        $h = ltrim( (string) $hex, '#' );
        if ( strlen( $h ) === 3 ) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        return [ hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) ];
    }

    /** $a mixed toward $b by $t (0 to 1). */
    public static function mix( $a, $b, $t ) {
        $x = self::rgb( $a ); $y = self::rgb( $b );
        return sprintf( '#%02X%02X%02X', round( $x[0] + ( $y[0] - $x[0] ) * $t ), round( $x[1] + ( $y[1] - $x[1] ) * $t ), round( $x[2] + ( $y[2] - $x[2] ) * $t ) );
    }

    public static function light( $hex ) {
        $c = self::rgb( $hex );
        return ( 0.299 * $c[0] + 0.587 * $c[1] + 0.114 * $c[2] ) > 160;
    }

    private static function rgba( $hex, $a ) {
        $c = self::rgb( $hex );
        return 'rgba(' . $c[0] . ',' . $c[1] . ',' . $c[2] . ',' . $a . ')';
    }

    private static function vars() {
        $c = Marketur_App_Kit::get( 'colors' );
        $paper = $c['paper']; $ink = $c['ink']; $sign = $c['active'];
        // The hero is always dark, like NexURLs. A light ink color falls back to near black.
        $dark = self::light( $ink ) ? '#121212' : $ink;
        $on   = self::light( $sign ) ? $dark : '#FFFFFF';
        return [
            '--x-paper' => $paper, '--x-card' => self::light( $paper ) ? '#FFFFFF' : self::mix( $paper, '#FFFFFF', .08 ),
            '--x-ink' => $ink, '--x-muted' => $c['muted'], '--x-line' => self::mix( $paper, $ink, .12 ),
            '--x-sign' => $sign, '--x-sign-dark' => self::mix( $sign, '#000000', .14 ), '--x-sign-deep' => self::mix( $sign, '#000000', .28 ),
            '--x-on-sign' => $on, '--x-dark' => $dark, '--x-glow' => self::rgba( $sign, .55 ), '--x-glow2' => self::rgba( $sign, .18 ),
            '--x-tint' => self::mix( $sign, '#FFFFFF', .78 ), '--x-soft' => self::mix( $dark, '#FFFFFF', .78 ),
        ];
    }

    /* ---------------------------------------------------------------- data */

    private static function urls() {
        $page = function ( $opt ) { $id = (int) get_option( $opt ); return $id && get_post_status( $id ) === 'publish' ? get_permalink( $id ) : ''; };
        $acct = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : $page( 'ihc_general_user_page' );
        $blog = (int) get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : '';
        return [
            'home'     => home_url( '/' ),
            'search'   => home_url( '/?s=' ),
            'account'  => $acct ?: wp_login_url(),
            'login'    => $page( 'ihc_general_login_default_page' ) ?: ( $acct ?: wp_login_url() ),
            'register' => $page( 'ihc_general_register_default_page' ) ?: ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : ( get_option( 'users_can_register' ) ? wp_registration_url() : '' ) ),
            'shop'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
            'cart'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
            'blog'     => $blog,
            'feed'     => self::has_table( 'qcf_posts' ) ? home_url( '/?qcf_feed=1' ) : '',
        ];
    }

    private static function has_table( $name ) {
        global $wpdb;
        static $seen = [];
        if ( ! isset( $seen[ $name ] ) ) { $t = $wpdb->prefix . $name; $seen[ $name ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t; }
        return $seen[ $name ];
    }

    private static function lines( $text ) {
        $out = [];
        foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $text ) ) ) as $l ) {
            $p = array_map( 'trim', explode( '|', $l ) );
            if ( count( $p ) < 2 || $p[0] === '' ) continue;
            $out[] = [ 'label' => $p[0], 'url' => preg_match( '#^(https?:|tel:|mailto:)#', $p[1] ) ? $p[1] : home_url( '/' . ltrim( $p[1], '/' ) ), 'icon' => ( $p[2] ?? '' ) ?: 'star' ];
        }
        return $out;
    }

    /** Every shortcut a member can pick: the owner's shortcuts, the tabs and the useful pages the site has. */
    public static function catalog() {
        $s = Marketur_App_Kit::get();
        $u = self::urls();
        $all = [];
        $seen = [];
        $add = function ( $label, $url, $icon ) use ( &$all, &$seen ) {
            if ( ! $url ) return;
            $key = strtolower( trim( $label ) );
            if ( isset( $seen[ $key ] ) ) return;
            $seen[ $key ] = 1;
            $all[ 't' . substr( md5( $label . '|' . $url ), 0, 9 ) ] = [ 'label' => $label, 'url' => $url, 'icon' => $icon ];
        };
        foreach ( self::lines( $s['home_tiles'] ) as $t ) $add( $t['label'], $t['url'], $t['icon'] );
        foreach ( Marketur_App_Kit::tabs_list() as $t ) $add( $t['label'], $t['url'], $t['icon'] );
        $add( 'Search', $u['search'], 'search' );
        if ( $u['shop'] ) $add( 'Shop', $u['shop'], 'shop' );
        if ( $u['cart'] ) $add( 'Cart', $u['cart'], 'cart' );
        if ( $u['blog'] ) $add( 'News', $u['blog'], 'news' );
        if ( $u['feed'] ) $add( 'Community', $u['feed'], 'community' );
        if ( $s['home_phone'] ) $add( 'Call', 'tel:' . preg_replace( '/[^0-9+]/', '', $s['home_phone'] ), 'phone' );
        if ( $s['home_book'] ) $add( 'Book', $s['home_book'], 'calendar' );
        $add( 'Account', $u['account'], 'account' );
        return $all;
    }

    private static function defaults_ids() {
        $s = Marketur_App_Kit::get();
        $cat = self::catalog();
        $own = count( self::lines( $s['home_tiles'] ) ) + count( Marketur_App_Kit::tabs_list() );
        return array_slice( array_keys( $cat ), 0, max( 4, min( 8, $own ?: 8 ) ) );
    }

    private static function my_ids( $uid ) {
        $cat = self::catalog();
        $pick = $uid ? get_user_meta( $uid, 'mak_tiles', true ) : [];
        $out = [];
        foreach ( is_array( $pick ) ? $pick : [] as $id ) if ( isset( $cat[ $id ] ) && ! in_array( $id, $out, true ) ) $out[] = $id;
        return $out ? array_slice( $out, 0, 8 ) : array_slice( self::defaults_ids(), 0, 8 );
    }

    private static function plan( $uid ) {
        try {
            if ( class_exists( '\Indeed\Ihc\UserSubscriptions' ) ) {
                foreach ( (array) \Indeed\Ihc\UserSubscriptions::getAllForUser( $uid, true ) as $lid => $d ) {
                    if ( ! empty( $d['is_expired'] ) ) continue;
                    if ( class_exists( '\Indeed\Ihc\Db\Memberships' ) ) { $l = \Indeed\Ihc\Db\Memberships::getOne( (int) $lid ); if ( ! empty( $l['label'] ) ) return (string) $l['label']; }
                }
            }
        } catch ( \Throwable $e ) {}
        if ( user_can( $uid, 'manage_options' ) ) return 'Admin';
        return 'Member';
    }

    private static function stats( $uid ) {
        $out = [];
        try {
            if ( function_exists( 'wc_get_orders' ) ) {
                $n = count( wc_get_orders( [ 'customer_id' => $uid, 'limit' => 200, 'return' => 'ids' ] ) );
                $out[] = [ $n, $n === 1 ? 'order' : 'orders', function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '' ];
                if ( function_exists( 'WC' ) && WC()->cart ) $out[] = [ (int) WC()->cart->get_cart_contents_count(), 'in cart', wc_get_cart_url() ];
            }
            if ( self::has_table( 'qcf_posts' ) ) {
                global $wpdb;
                $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}qcf_posts WHERE user_id = %d", $uid ) );
                $out[] = [ $n, $n === 1 ? 'post' : 'posts', home_url( '/?qcf_feed=1' ) ];
            }
            $c = (int) get_comments( [ 'user_id' => $uid, 'count' => true, 'status' => 'approve' ] );
            if ( $c ) $out[] = [ $c, $c === 1 ? 'comment' : 'comments', '' ];
            $since = (int) floor( ( time() - strtotime( get_userdata( $uid )->user_registered ) ) / DAY_IN_SECONDS );
            $out[] = [ max( 1, $since ), 'days with us', '' ];
        } catch ( \Throwable $e ) {}
        return array_slice( $out, 0, 4 );
    }

    /** The one thing the app is for. Set by the AI (or the owner); otherwise worked out from the site. */
    private static function main_button() {
        $s = Marketur_App_Kit::get();
        $u = self::urls();
        $label = trim( (string) $s['main_label'] );
        $url = trim( (string) $s['main_url'] );
        $sub = trim( (string) $s['main_sub'] );
        if ( $url !== '' && ! preg_match( '#^(https?:|tel:|mailto:)#', $url ) ) $url = home_url( '/' . ltrim( $url, '/' ) );
        if ( $label === '' || $url === '' ) {
            if ( $u['shop'] ) { $label = 'Shop now'; $url = $u['shop']; $sub = $sub ?: 'New in the shop'; }
            elseif ( $s['home_book'] ) { $label = 'Book now'; $url = $s['home_book']; $sub = $sub ?: 'Pick a time that suits you'; }
            elseif ( $s['home_phone'] ) { $label = 'Call us'; $url = 'tel:' . preg_replace( '/[^0-9+]/', '', $s['home_phone'] ); $sub = $sub ?: 'We are happy to help'; }
            elseif ( $u['feed'] ) { $label = 'Open the community'; $url = $u['feed']; $sub = $sub ?: 'See what people are saying'; }
            elseif ( $u['blog'] ) { $label = 'Read the latest'; $url = $u['blog']; $sub = $sub ?: 'Fresh from ' . get_bloginfo( 'name' ); }
            else { $tabs = Marketur_App_Kit::tabs_list(); $label = 'Explore'; $url = isset( $tabs[1] ) ? $tabs[1]['url'] : home_url( '/' ); $sub = $sub ?: 'See what is new'; }
        }
        $btn = trim( (string) $s['main_button'] ) ?: 'Go';
        return '<a class="mka-go" href="' . esc_url( $url, [ 'http', 'https', 'tel', 'mailto' ] ) . '"><span class="mka-go-tx"><em>' . esc_html( $sub ) . '</em><strong>' . esc_html( $label ) . '</strong></span><span class="mka-go-btn">' . esc_html( $btn ) . ' ' . self::i( 'arrow', 22 ) . '</span></a>';
    }

    /* ---------------------------------------------------------------- markup */

    private static function i( $name, $size = 22 ) {
        if ( $name === 'arrow' ) return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
        if ( $name === 'tool' ) return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
        return class_exists( 'MAK_Admin_UI' ) ? MAK_Admin_UI::svg( $name, $size ) : '';
    }

    private static function head( $title, $link = '', $label = 'See all' ) {
        return '<div class="mka-h"><h2>' . esc_html( $title ) . '</h2>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $label ) . ' ' . self::i( 'arrow', 16 ) . '</a>' : '' ) . '</div>';
    }

    private static function hero( $uid ) {
        $s = Marketur_App_Kit::get();
        $u = self::urls();
        if ( ! $uid ) {
            $name = get_bloginfo( 'name' );
            $words = explode( ' ', $name );
            $mark = count( $words ) > 1 ? esc_html( implode( ' ', array_slice( $words, 0, -1 ) ) ) . ' <span>' . esc_html( end( $words ) ) . '</span>' : esc_html( $name );
            $icon = get_site_icon_url( 128 );
            $head = $s['home_headline'] ?: ( get_bloginfo( 'description' ) ?: 'Welcome to ' . $name );
            $lede = trim( (string) $s['home_lede'] );
            return '<section class="mka-hero mka-hero-guest">'
                . '<p class="mka-mark">' . ( $icon ? '<img src="' . esc_url( $icon ) . '" alt="" width="34" height="34">' : '' ) . $mark . '</p>'
                . '<h1>' . esc_html( $head ) . '</h1>'
                . ( $lede !== '' ? '<p class="mka-lede">' . esc_html( $lede ) . '</p>' : '' )
                . self::main_button()
                . ( $u['login'] ? '<p class="mka-signin">Have an account? <a href="' . esc_url( $u['login'] ) . '">Sign in</a></p>' : '' )
                . '</section>';
        }
        $me = get_userdata( $uid );
        $first = trim( (string) $me->first_name ) ?: $me->display_name;
        $h = '<section class="mka-hero"><div class="mka-hello"><a class="mka-av" href="' . esc_url( $u['account'] ) . '"><img src="' . esc_url( get_avatar_url( $uid, [ 'size' => 128 ] ) ) . '" alt="" width="56" height="56"></a>'
            . '<div><p class="mka-date" data-mka-date>' . esc_html( wp_date( 'l, j F' ) ) . '</p>'
            . '<h1><span data-mka-greet>Welcome back</span>, ' . esc_html( ucfirst( $first ) ) . '</h1>'
            . '<span class="mka-plan">' . esc_html( self::plan( $uid ) ) . '</span></div></div>';
        $stats = self::stats( $uid );
        if ( count( $stats ) >= 2 ) {
            $h .= '<div class="mka-stats">';
            foreach ( $stats as $c ) $h .= ( $c[2] ? '<a href="' . esc_url( $c[2] ) . '">' : '<span class="mka-st">' ) . '<strong data-mka-count="' . (int) $c[0] . '">' . esc_html( number_format_i18n( $c[0] ) ) . '</strong><span>' . esc_html( $c[1] ) . '</span>' . ( $c[2] ? '</a>' : '</span>' );
            $h .= '</div>';
        }
        return $h . self::main_button() . '</section>';
    }

    private static function quick( $uid ) {
        $cat = self::catalog();
        $ids = $uid ? self::my_ids( $uid ) : self::defaults_ids();
        if ( ! $ids ) return '';
        $h = '<nav class="mka-quick" aria-label="Shortcuts">';
        foreach ( $ids as $n => $id ) {
            $c = $cat[ $id ];
            $h .= '<a class="mka-tile" href="' . esc_url( $c['url'], [ 'http', 'https', 'tel', 'mailto' ] ) . '"><span class="mka-tile-ic c' . ( ( $n % 8 ) + 1 ) . '">' . self::i( $c['icon'], 24 ) . '</span><span>' . esc_html( $c['label'] ) . '</span></a>';
        }
        $h .= '</nav>';
        if ( ! $uid || count( $cat ) < 2 ) return $h;
        $all = [];
        $n = 0;
        foreach ( $cat as $id => $c ) $all[] = [ 'id' => $id, 'label' => $c['label'], 'color' => 'c' . ( ( $n++ % 8 ) + 1 ), 'icon' => self::i( $c['icon'], 22 ) ];
        $cfg = [ 'all' => $all, 'on' => self::my_ids( $uid ), 'rest' => esc_url_raw( rest_url( 'mak/v1/tiles' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ];
        return $h . '<div class="mka-qedit"><button type="button" data-mka-edit>' . self::i( 'tool', 14 ) . ' Edit shortcuts</button></div>'
            . '<script type="application/json" id="mka-tiles">' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
    }

    private static function actions() {
        $s = Marketur_App_Kit::get();
        $acts = [];
        if ( $s['home_phone'] ) $acts[] = [ 'Call', 'tel:' . preg_replace( '/[^0-9+]/', '', $s['home_phone'] ), 'phone' ];
        if ( $s['home_book'] ) $acts[] = [ 'Book', $s['home_book'], 'calendar' ];
        if ( $s['home_address'] ) $acts[] = [ 'Directions', 'https://maps.apple.com/?q=' . rawurlencode( $s['home_address'] ), 'directions' ];
        if ( $s['home_email'] ) $acts[] = [ 'Email', 'mailto:' . $s['home_email'], 'mail' ];
        if ( count( $acts ) < 2 ) return '';
        $h = '<section class="mka-sec"><div class="mka-acts">';
        foreach ( $acts as $a ) $h .= '<a href="' . esc_url( $a[1], [ 'http', 'https', 'tel', 'mailto' ] ) . '">' . self::i( $a[2], 24 ) . '<span>' . esc_html( $a[0] ) . '</span></a>';
        return $h . '</div></section>';
    }

    private static function card( $url, $img, $kicker, $title, $meta_l, $meta_r ) {
        return '<a class="mka-uc" href="' . esc_url( $url ) . '"><span class="mka-uc-img">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" onerror="this.remove()">' : '' ) . '</span>'
            . ( $kicker !== '' ? '<em>' . esc_html( $kicker ) . '</em>' : '' ) . '<strong>' . esc_html( $title ) . '</strong>'
            . '<span class="mka-uc-m">' . wp_kses_post( $meta_l ) . '<span>' . esc_html( $meta_r ) . '</span></span></a>';
    }

    private static function products() {
        if ( ! function_exists( 'wc_get_products' ) ) return '';
        $ids = wc_get_products( [ 'status' => 'publish', 'limit' => 10, 'featured' => true, 'return' => 'ids' ] );
        $title = 'Featured';
        if ( count( $ids ) < 3 ) { $ids = wc_get_products( [ 'status' => 'publish', 'limit' => 10, 'orderby' => 'popularity', 'return' => 'ids' ] ); $title = 'Popular right now'; }
        if ( ! $ids ) return '';
        $h = '<section class="mka-sec">' . self::head( $title, self::urls()['shop'], 'Shop all' ) . '<div class="mka-row">';
        foreach ( $ids as $id ) {
            $p = wc_get_product( $id );
            if ( ! $p ) continue;
            $cats = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
            $h .= self::card( $p->get_permalink(), wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ), $cats && ! is_wp_error( $cats ) ? $cats[0] : '', $p->get_name(), '<b class="mka-price">' . wp_strip_all_tags( $p->get_price_html() ) . '</b>', $p->is_in_stock() ? '' : 'Sold out' );
        }
        return $h . '</div></section>';
    }

    private static function posts() {
        $q = get_posts( [ 'numberposts' => 8, 'ignore_sticky_posts' => true ] );
        if ( ! $q ) return '';
        $h = '<section class="mka-sec">' . self::head( 'Latest', self::urls()['blog'] ?: '', 'Read more' ) . '<div class="mka-row">';
        foreach ( $q as $p ) {
            $cats = array_values( array_filter( get_the_category( $p->ID ), function ( $c ) { return ! in_array( $c->slug, [ 'uncategorized', 'uncategorised' ], true ); } ) );
            $h .= self::card( get_permalink( $p ), get_the_post_thumbnail_url( $p, 'medium_large' ), $cats ? $cats[0]->name : '', get_the_title( $p ), esc_html( human_time_diff( get_post_time( 'U', true, $p ), time() ) . ' ago' ), '' );
        }
        return $h . '</div></section>';
    }

    private static function topics() {
        $tax = function_exists( 'wc_get_products' ) ? 'product_cat' : 'category';
        $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true, 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC', 'number' => 10 ] );
        if ( is_wp_error( $terms ) ) return '';
        $terms = array_values( array_filter( $terms, function ( $t ) { return ! in_array( $t->slug, [ 'uncategorized', 'uncategorised' ], true ); } ) );
        if ( count( $terms ) < 2 ) return '';
        $icons = [ 'sparkles', 'fire', 'star', 'heart', 'bolt', 'tag', 'gift', 'leaf', 'globe', 'trophy' ];
        $h = '<section class="mka-sec">' . self::head( $tax === 'product_cat' ? 'Shop by category' : 'Pick a topic' ) . '<div class="mka-topics">';
        foreach ( array_slice( $terms, 0, 10 ) as $n => $t ) {
            $word = $tax === 'product_cat' ? ( (int) $t->count === 1 ? 'item' : 'items' ) : ( (int) $t->count === 1 ? 'post' : 'posts' );
            $h .= '<a class="mka-topic t' . ( ( $n % 5 ) + 1 ) . '" href="' . esc_url( get_term_link( $t ) ) . '"><span class="mka-topic-ic">' . self::i( $icons[ $n % 10 ], 22 ) . '</span><strong>' . esc_html( html_entity_decode( $t->name, ENT_QUOTES ) ) . '</strong><span>' . (int) $t->count . ' ' . $word . '</span></a>';
        }
        return $h . '</div></section>';
    }

    private static function community() {
        if ( ! self::has_table( 'qcf_posts' ) ) return '';
        global $wpdb;
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, content, likes_count, comments_count, created_at FROM {$wpdb->prefix}qcf_posts WHERE (is_hidden = 0 OR is_hidden IS NULL) AND (group_id IS NULL OR group_id = 0) ORDER BY created_at DESC LIMIT %d", 4 ) ); // phpcs:ignore
        if ( ! $rows ) return '';
        $h = '<section class="mka-sec">' . self::head( 'In the community', home_url( '/?qcf_feed=1' ), 'Open feed' ) . '<div class="mka-card mka-list">';
        foreach ( $rows as $r ) {
            $u = get_userdata( (int) $r->user_id );
            $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $r->content ) ) );
            $ts = strtotime( (string) $r->created_at . ' UTC' );
            $h .= '<a class="mka-post" href="' . esc_url( add_query_arg( 'qcf_post', (int) $r->id, home_url( '/' ) ) ) . '"><img src="' . esc_url( get_avatar_url( (int) $r->user_id, [ 'size' => 64 ] ) ) . '" alt="" width="40" height="40" loading="lazy">'
                . '<span><span class="mka-post-m"><strong>' . esc_html( $u ? $u->display_name : 'Member' ) . '</strong> ' . esc_html( $ts ? human_time_diff( min( $ts, time() ), time() ) . ' ago' : '' ) . '</span>'
                . '<span class="mka-post-x">' . esc_html( $text !== '' ? mb_strimwidth( $text, 0, 150, '...' ) : 'Shared a post' ) . '</span>'
                . '<span class="mka-post-c">' . (int) $r->likes_count . ' likes - ' . (int) $r->comments_count . ' comments</span></span></a>';
        }
        return $h . '</div></section>';
    }

    private static function hours() {
        $s = Marketur_App_Kit::get();
        if ( trim( (string) $s['home_hours'] ) === '' ) return '';
        return '<section class="mka-sec">' . self::head( 'Opening hours' ) . '<div class="mka-card mka-hours"><span class="mka-hours-ic">' . self::i( 'clock', 22 ) . '</span><div>' . nl2br( esc_html( $s['home_hours'] ) ) . ( $s['home_address'] ? '<p>' . esc_html( $s['home_address'] ) . '</p>' : '' ) . '</div></div></section>';
    }

    private static function join() {
        $u = self::urls();
        if ( ! $u['register'] ) return '';
        $s = Marketur_App_Kit::get();
        return '<section class="mka-sec"><div class="mka-join"><strong>' . esc_html( $s['join_title'] ?: 'Join ' . get_bloginfo( 'name' ) ) . '</strong>'
            . '<span>' . esc_html( $s['join_text'] ?: 'A free account keeps your details, orders and favourites in one place, on any device.' ) . '</span>'
            . '<a class="mka-btn" href="' . esc_url( $u['register'] ) . '">Create a free account</a></div></section>';
    }

    public static function render() {
        if ( is_user_logged_in() && ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
        $uid = get_current_user_id();
        $style = '';
        foreach ( self::vars() as $k => $v ) $style .= $k . ':' . $v . ';';
        $order = function_exists( 'wc_get_products' ) ? [ 'actions', 'products', 'topics', 'posts', 'community', 'hours' ] : [ 'actions', 'posts', 'topics', 'community', 'hours' ];
        $h = '<div class="mka' . ( $uid ? '' : ' mka-guest' ) . '" style="' . esc_attr( $style ) . '">' . self::hero( $uid ) . self::quick( $uid );
        foreach ( $order as $sec ) $h .= self::$sec();
        if ( ! $uid ) $h .= self::join();
        self::$used = true;
        return $h . '</div>';
    }

    /* ---------------------------------------------------------------- look and motion */

    private static function css() {
        static $done = false;
        if ( $done ) return '';
        $done = true;
        $bg = Marketur_App_Kit::get( 'colors' )['paper'];
        return '<style id="mka-css">
body.mka-page{background:' . esc_attr( $bg ) . '!important}
body.mka-page .entry-header,body.mka-page .page-header,body.mka-page .wp-block-post-title,body.mka-page h1.entry-title{display:none!important}
body.mka-page .site-content>.ast-container{max-width:100%!important;padding-left:0!important;padding-right:0!important}
body.mka-page .site-main>article,body.mka-page .ast-article-single,body.mka-page article.page,body.mka-page .entry-content{padding:0!important;margin:0!important;border:0!important;box-shadow:none!important;background:transparent!important;max-width:none!important}
body.mka-page #primary{margin:0!important;padding:0!important}
html.mak-app body.mka-page .site-footer,html.mak-app body.mka-page footer.site-footer{display:none!important}
.mka{color:var(--x-ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.45;max-width:1120px;margin:0 auto;padding:18px 0 40px;box-sizing:border-box;-webkit-font-smoothing:antialiased}
.mka *,.mka *::before,.mka *::after{box-sizing:border-box}
.mka a,.mka a:hover,.mka a:focus{color:inherit;text-decoration:none!important}
.mka h1,.mka h2,.mka strong,.mka-mark,.mka-go-btn,.mka-btn{font-family:"Space Grotesk",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;letter-spacing:-.01em}
.mka-hero{position:relative;overflow:hidden;border-radius:28px;padding:26px 26px 22px;margin-bottom:6px;color:#fff;background:radial-gradient(90% 120% at 100% 0%,var(--x-glow) 0%,transparent 60%),radial-gradient(70% 90% at 0% 100%,rgba(255,201,60,.16) 0%,transparent 60%),var(--x-dark);box-shadow:0 26px 50px -30px var(--x-glow)}
.mka-hero::after{content:"";position:absolute;inset:0;pointer-events:none;opacity:.07;background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:32px 32px;-webkit-mask-image:radial-gradient(80% 80% at 80% 0%,#000,transparent);mask-image:radial-gradient(80% 80% at 80% 0%,#000,transparent)}
.mka-hero>*{position:relative;z-index:1}
.mka h1{font-size:clamp(24px,3.4vw,34px);line-height:1.15;font-weight:700;letter-spacing:-.02em;margin:2px 0 6px;color:#fff!important}
.mka-hello{display:flex;gap:14px;align-items:center}
.mka-av{flex:none;padding:3px;border-radius:50%;background:linear-gradient(135deg,var(--x-sign),#FFC93C);animation:mkaRing 2.4s ease-out infinite}
@keyframes mkaRing{0%{box-shadow:0 0 0 0 var(--x-glow)}70%,100%{box-shadow:0 0 0 12px transparent}}
.mka-av img{width:60px;height:60px;border-radius:50%;object-fit:cover;display:block;border:3px solid var(--x-dark)}
.mka-date{margin:0;font-size:13px;font-weight:600;color:var(--x-tint)}
.mka-plan{display:inline-block;margin-top:2px;padding:4px 11px;border-radius:999px;background:var(--x-glow2);border:1px solid var(--x-glow);color:var(--x-tint);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.mka-stats{display:flex;gap:8px;margin:20px 0 18px}
.mka-stats>a,.mka-stats>.mka-st{flex:1 1 0;min-width:0;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:12px 6px;text-align:center}
.mka-stats strong{display:block;color:#fff;font-size:22px;line-height:1.1}.mka-stats span span,.mka-stats a span{color:var(--x-soft);font-size:12px;white-space:nowrap}
.mka-hero-guest{text-align:center;padding:30px 22px 22px}
.mka-mark{display:inline-flex;align-items:center;gap:10px;font-size:20px;font-weight:700;margin:0 0 12px;color:#fff}.mka-mark span{color:var(--x-sign)}.mka-mark img{border-radius:10px}
.mka-hero-guest h1{font-size:clamp(28px,4vw,38px)}
.mka-lede{color:var(--x-soft);max-width:560px;margin:0 auto 18px}
.mka-signin{margin:14px 0 0;font-size:14px;color:var(--x-soft)}.mka-signin a{font-weight:700;color:#fff!important;text-decoration:underline!important}
.mka-go{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;background:var(--x-sign);color:var(--x-on-sign)!important;border-radius:20px;padding:16px 16px 16px 20px;box-shadow:0 6px 0 var(--x-sign-deep);transition:transform .08s ease,box-shadow .08s ease;text-align:left}
.mka-go:active{transform:translateY(4px);box-shadow:0 2px 0 var(--x-sign-deep)}
.mka-go-tx em{display:block;font-style:normal;font-size:13px;font-weight:600;opacity:.85}.mka-go-tx strong{display:block;font-size:22px;line-height:1.15}
.mka-go-btn{display:inline-flex;align-items:center;gap:6px;background:var(--x-dark);color:#fff;border-radius:999px;padding:12px 20px;font-weight:700;font-size:18px;flex:0 0 auto}
.mka-quick{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:8px;margin:16px 0 4px;padding:16px 10px;background:var(--x-card);border:1.5px solid var(--x-line);border-radius:24px}
.mka-tile{display:flex;flex-direction:column;align-items:center;gap:6px;font-size:12.5px;font-weight:600;text-align:center;padding:4px 0;color:var(--x-ink)!important;transition:transform .15s}
.mka-tile:active{transform:scale(.94)}
.mka-tile-ic{width:56px;height:56px;border-radius:18px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--x-dark);box-shadow:0 3px 0 var(--x-dark)}
.mka .c1{background:var(--x-sign);color:var(--x-on-sign)}.mka .c2,.mka-sheet .c2{background:var(--x-dark);color:#fff}.mka .c3,.mka-sheet .c3{background:var(--x-tint);color:var(--x-dark)}.mka .c4,.mka-sheet .c4{background:#fff;color:var(--x-sign-dark)}
.mka .c5,.mka-sheet .c5{background:#F9E7A8;color:#121212}.mka .c6,.mka-sheet .c6{background:#D7E8D2;color:#121212}.mka .c7,.mka-sheet .c7{background:var(--x-line);color:var(--x-dark)}.mka .c8,.mka-sheet .c8{background:#fff;color:#121212}
.mka-sheet .c1{background:var(--x-sign);color:var(--x-on-sign)}
.mka-qedit{text-align:right;margin:4px 4px 0}.mka-qedit button{background:none;border:0;color:var(--x-muted);font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;gap:4px;align-items:center;padding:4px}
.mka-sec{margin:26px 0 0}
.mka-h{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin:0 4px 12px}.mka-h h2{font-size:21px;margin:0;color:var(--x-ink)!important}
.mka-h a{font-size:14px;font-weight:700;color:var(--x-sign-dark)!important;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;flex:none}
.mka-row{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding:2px 2px 10px;scrollbar-width:none}.mka-row::-webkit-scrollbar{display:none}
.mka-uc{flex:0 0 220px;scroll-snap-align:start;background:var(--x-card);border:1.5px solid var(--x-line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
.mka-uc:hover{transform:translateY(-3px);box-shadow:0 16px 30px -18px rgba(18,18,18,.35)}
.mka-uc-img{display:block;aspect-ratio:16/10;background:radial-gradient(80% 100% at 100% 0%,var(--x-glow2),transparent 70%),linear-gradient(135deg,var(--x-tint),var(--x-line));overflow:hidden}.mka-uc-img img{width:100%;height:100%;object-fit:cover;display:block}
.mka-uc em{font-style:normal;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--x-sign-dark);padding:10px 12px 0}
.mka-uc strong{font-size:15px;line-height:1.25;padding:2px 12px 0;color:var(--x-ink)}
.mka-uc-m{display:flex;align-items:center;gap:4px;margin-top:auto;padding:10px 12px 12px;font-size:13px;color:var(--x-muted)}.mka-uc-m span{margin-left:auto;white-space:nowrap}
.mka-price{color:var(--x-ink);font-family:"Space Grotesk",sans-serif}
.mka-acts{display:grid;grid-template-columns:repeat(auto-fit,minmax(70px,1fr));gap:10px}
.mka-acts a{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 6px;border-radius:18px;background:var(--x-card);border:1.5px solid var(--x-dark);box-shadow:0 3px 0 var(--x-dark);font-weight:700;font-size:13px;color:var(--x-ink)!important}
.mka-acts a:first-child{background:var(--x-sign);color:var(--x-on-sign)!important}
.mka-card{background:var(--x-card);border:1.5px solid var(--x-line);border-radius:18px;overflow:hidden}
.mka-topics{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}
.mka-topic{border-radius:18px;padding:14px;border:1.5px solid var(--x-dark);display:flex;flex-direction:column;gap:2px;min-height:108px;color:var(--x-dark)!important}
.mka-topic-ic{margin-bottom:auto}.mka-topic strong{font-size:15px;line-height:1.2;margin-top:10px;color:inherit}.mka-topic span:last-child{font-size:12px;opacity:.75}
.mka-topic.t1{background:var(--x-sign);color:var(--x-on-sign)!important}.mka-topic.t2{background:var(--x-dark);color:#fff!important}.mka-topic.t3{background:var(--x-tint)}.mka-topic.t4{background:#F9E7A8}.mka-topic.t5{background:#fff}
.mka-post{display:flex;gap:12px;padding:12px 14px;border-top:1px solid var(--x-line)}.mka-post:first-child{border-top:0}
.mka-post img{width:40px;height:40px;border-radius:50%;object-fit:cover;flex:0 0 40px}
.mka-post-m{display:block;font-size:13px;color:var(--x-muted)}.mka-post-m strong{color:var(--x-ink);font-size:14px}
.mka-post-x{display:block;font-size:15px}.mka-post-c{display:block;font-size:12px;color:var(--x-muted);margin-top:2px}
.mka-hours{display:flex;gap:14px;padding:16px;line-height:1.6}.mka-hours p{margin:6px 0 0;color:var(--x-muted)}
.mka-hours-ic{flex:0 0 44px;height:44px;border-radius:14px;background:var(--x-paper);display:flex;align-items:center;justify-content:center;color:var(--x-sign-dark)}
.mka-join{background:var(--x-dark);color:#fff;border-radius:22px;padding:22px;text-align:center}
.mka-join strong{display:block;font-size:22px;margin-bottom:6px;color:#fff}.mka-join span{display:block;color:var(--x-soft);margin:0 auto 16px;max-width:460px}
.mka-btn{display:inline-block;background:var(--x-sign);color:var(--x-on-sign)!important;border-radius:999px;padding:12px 24px;font-weight:700}
.mka-sheet-bg{position:fixed;inset:0;background:rgba(18,18,18,.45);z-index:99999;display:flex;align-items:flex-end;justify-content:center}
.mka-sheet{background:var(--x-paper);color:var(--x-ink);width:100%;max-width:560px;border-radius:24px 24px 0 0;padding:20px 18px calc(18px + env(safe-area-inset-bottom));font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.mka-sheet h3{margin:0 0 4px;font-size:20px;font-family:"Space Grotesk",sans-serif}.mka-sheet p{margin:0 0 14px;color:var(--x-muted);font-size:14px}
.mka-sheet-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;max-height:52vh;overflow-y:auto}
.mka-pick{position:relative;background:none;border:0;padding:4px 0;display:flex;flex-direction:column;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--x-ink);cursor:pointer;opacity:.55}
.mka-pick.is-on{opacity:1}.mka-pick .mka-tile-ic{width:48px;height:48px;border:1.5px solid var(--x-dark);border-radius:16px;display:flex;align-items:center;justify-content:center}
.mka-pick b{position:absolute;top:0;right:6px;background:var(--x-dark);color:#fff;border-radius:999px;font-size:11px;min-width:18px;height:18px;line-height:18px}
.mka-sheet-acts{display:flex;align-items:center;gap:8px;margin-top:16px}.mka-sheet-n{margin-right:auto;font-size:13px;color:var(--x-muted)}
.mka-sheet-acts button{border:1.5px solid var(--x-dark);background:transparent;border-radius:999px;padding:8px 14px;font-weight:700;cursor:pointer;color:var(--x-ink)}
.mka-sheet-acts .mka-save{background:var(--x-sign);border-color:var(--x-sign);color:var(--x-on-sign)}
.mka-reveal{opacity:0;transform:translateY(10px);transition:opacity .35s ease,transform .35s ease}.mka-reveal.is-in{opacity:1;transform:none}
@media (max-width:760px){.mka-quick{grid-template-columns:repeat(4,minmax(0,1fr));row-gap:14px}.mka-topics{grid-template-columns:repeat(2,1fr)}}
@media (max-width:720px){.mka{padding:10px 12px 30px}.mka-hero{border-radius:24px;padding:20px 16px 16px}.mka-hello{gap:12px}.mka-av img{width:52px;height:52px}.mka h1{font-size:23px}.mka-hero-guest h1{font-size:28px}
.mka-stats{gap:6px;margin:16px 0 14px}.mka-stats>a,.mka-stats>.mka-st{padding:10px 4px;border-radius:14px}.mka-stats strong{font-size:19px}.mka-stats a span,.mka-stats span span{font-size:11px}
.mka-go{padding:14px 14px 14px 16px;border-radius:18px}.mka-go-tx strong{font-size:18px}.mka-go-btn{padding:10px 16px;font-size:16px}
.mka-quick{margin-top:12px;padding:14px 6px;border-radius:22px}.mka-tile{font-size:11.5px}.mka-tile-ic{width:52px;height:52px;border-radius:16px}
.mka-sec{margin-top:22px}.mka-h h2{font-size:19px}.mka-row{margin:0 -12px;padding:2px 12px 10px;scroll-padding:0 12px}.mka-uc{flex-basis:76%}}
@media (prefers-reduced-motion:reduce){.mka-reveal{opacity:1;transform:none;transition:none}.mka-av{animation:none}}
</style>';
    }

    private static function js() {
        static $done = false;
        if ( $done ) return '';
        $done = true;
        return <<<'JS'
<script id="mka-js">
(function(){
  function boot(){
    var root=document.querySelector('.mka');if(!root)return;
    var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var now=new Date(),g=root.querySelector('[data-mka-greet]');
    if(g){var h=now.getHours();g.textContent=h<5?'Up late':h<12?'Good morning':h<17?'Good afternoon':'Good evening';}
    var d=root.querySelector('[data-mka-date]');
    if(d&&now.toLocaleDateString){try{d.textContent=now.toLocaleDateString(undefined,{weekday:'long',day:'numeric',month:'long'});}catch(e){}}
    if(!reduce){root.querySelectorAll('[data-mka-count]').forEach(function(el){var to=parseInt(el.getAttribute('data-mka-count'),10)||0;if(to<2)return;var start=null;el.textContent='0';function step(t){if(!start)start=t;var k=Math.min(1,(t-start)/900);k=1-Math.pow(1-k,3);el.textContent=Math.round(to*k).toLocaleString();if(k<1)requestAnimationFrame(step);}requestAnimationFrame(step);});}
    var secs=root.querySelectorAll('.mka-quick,.mka-sec');
    if(!reduce&&'IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('is-in');io.unobserve(e.target);}});},{rootMargin:'0px 0px -8% 0px'});secs.forEach(function(s){s.classList.add('mka-reveal');io.observe(s);});}
    var eb=root.querySelector('[data-mka-edit]'),cfgEl=document.getElementById('mka-tiles');
    if(eb&&cfgEl){var cfg={};try{cfg=JSON.parse(cfgEl.textContent);}catch(e){}
      eb.addEventListener('click',function(){
        var on=(cfg.on||[]).slice(),bg=document.createElement('div');bg.className='mka-sheet-bg';bg.setAttribute('style',root.getAttribute('style'));
        var sh=document.createElement('div');sh.className='mka-sheet';sh.setAttribute('role','dialog');sh.setAttribute('aria-modal','true');sh.setAttribute('aria-label','Edit shortcuts');
        sh.innerHTML='<h3>Your shortcuts</h3><p>Tap up to 8, in the order you want them. Tap again to take one off.</p>';
        var grid=document.createElement('div');grid.className='mka-sheet-grid';var n=document.createElement('span');n.className='mka-sheet-n';
        function paint(){grid.querySelectorAll('.mka-pick').forEach(function(b){var i=on.indexOf(b.getAttribute('data-id'));b.classList.toggle('is-on',i!==-1);var x=b.querySelector('b');x.textContent=i!==-1?String(i+1):'';x.style.display=i!==-1?'':'none';});n.textContent=on.length+' of 8';}
        (cfg.all||[]).forEach(function(t){var b=document.createElement('button');b.type='button';b.className='mka-pick';b.setAttribute('data-id',t.id);b.innerHTML='<span class="mka-tile-ic '+t.color+'">'+t.icon+'</span><span></span><b></b>';b.children[1].textContent=t.label;b.addEventListener('click',function(){var i=on.indexOf(t.id);if(i!==-1)on.splice(i,1);else if(on.length<8)on.push(t.id);paint();});grid.appendChild(b);});
        sh.appendChild(grid);var acts=document.createElement('div');acts.className='mka-sheet-acts';
        function mk(l,c){var x=document.createElement('button');x.type='button';x.textContent=l;if(c)x.className=c;return x;}
        var reset=mk('Reset'),cancel=mk('Cancel'),save=mk('Save','mka-save');
        function close(){if(bg.parentNode)bg.parentNode.removeChild(bg);document.body.style.overflow='';}
        function send(tiles){save.disabled=true;save.textContent='Saving...';fetch(cfg.rest,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce},body:JSON.stringify({tiles:tiles})}).then(function(r){if(!r.ok)throw new Error();location.reload();}).catch(function(){save.disabled=false;save.textContent='Save';n.textContent='That did not save. Try again.';});}
        reset.addEventListener('click',function(){send([]);});cancel.addEventListener('click',close);save.addEventListener('click',function(){send(on);});
        bg.addEventListener('click',function(e){if(e.target===bg)close();});
        acts.appendChild(n);acts.appendChild(reset);acts.appendChild(cancel);acts.appendChild(save);sh.appendChild(acts);bg.appendChild(sh);document.body.appendChild(bg);document.body.style.overflow='hidden';paint();
      });
    }
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
</script>
JS;
    }
}

MAK_Home_App::init();
