<?php
/**
 * App home screens (App Kit 1.5.0).
 *
 * The page the app opens on. Apple turns down apps that are just a website in a frame
 * (guideline 4.2), and a real home screen is one of the biggest reasons the NexURLs app got
 * through, so every Kit app gets one. Four looks, all built from the site's own content, so
 * they work on any site with or without a page builder:
 *
 *   dashboard  greeting, shortcut tiles, latest posts
 *   store      product categories and products (WooCommerce), falls back to posts
 *   feed       posts as big cards
 *   services   call, book, directions and email buttons, opening hours, tiles, news
 *
 * [mak_app_home] shows the chosen one; [mak_app_home template="store"] forces a look.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Home {

    public static function init() {
        add_shortcode( 'mak_app_home', [ __CLASS__, 'render' ] );
        add_filter( 'wp_robots', function ( $r ) {
            $p = (int) Marketur_App_Kit::get( 'home_page' );
            if ( $p && is_page( $p ) ) $r['noindex'] = true;
            return $r;
        } );
    }

    public static function templates() {
        return [
            'dashboard' => 'Dashboard: greeting, shortcut tiles and the latest posts',
            'store'     => 'Store: product categories and products (needs WooCommerce, otherwise shows posts)',
            'feed'      => 'Feed: your posts as big cards',
            'services'  => 'Local business: call, book, directions, opening hours and news',
        ];
    }

    /** Makes the App Home page (once) and points the app at it. Returns the page id. */
    public static function ensure_page() {
        $id = (int) Marketur_App_Kit::get( 'home_page' );
        if ( $id && get_post_status( $id ) === 'publish' ) return $id;
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'App Home', 'post_name' => 'app-home', 'post_content' => '[mak_app_home]' ] );
        if ( ! $id || is_wp_error( $id ) ) return 0;
        $s = (array) get_option( MAK_OPT, [] );
        $s['home_page'] = $id;
        $s['start'] = get_permalink( $id );
        $s['updated'] = time();
        update_option( MAK_OPT, $s );
        return $id;
    }

    private static function icon( $name ) {
        $p = [
            'home' => 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z',
            'shop' => 'M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2z',
            'cart' => 'M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 20 4H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
            'account' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
            'search' => 'M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
            'news' => 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z',
            'chat' => 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z',
            'community' => 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
            'bell' => 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z',
            'heart' => 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
            'star' => 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z',
            'map' => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z',
            'phone' => 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C10.61 21 3 13.39 3 4c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
            'calendar' => 'M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z',
            'play' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z',
            'grid' => 'M4 8h4V4H4v4zm6 12h4v-4h-4v4zm-6 0h4v-4H4v4zm0-6h4v-4H4v4zm6 0h4v-4h-4v4zm6-10v4h4V4h-4zm-6 4h4V4h-4v4zm6 6h4v-4h-4v4zm0 6h4v-4h-4v4z',
            'info' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
            'menu' => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
            'mail' => 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z',
            'clock' => 'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z',
        ];
        return '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="' . ( $p[ $name ] ?? $p['star'] ) . '"/></svg>';
    }

    private static function tiles() {
        $out = [];
        foreach ( array_filter( array_map( 'trim', explode( "\n", (string) Marketur_App_Kit::get( 'home_tiles' ) ) ) ) as $l ) {
            $p = array_map( 'trim', explode( '|', $l ) );
            if ( count( $p ) < 2 ) continue;
            $out[] = [ 'label' => $p[0], 'url' => preg_match( '#^(https?:|tel:|mailto:)#', $p[1] ) ? $p[1] : home_url( '/' . ltrim( $p[1], '/' ) ), 'icon' => $p[2] ?? 'star' ];
        }
        if ( ! $out ) foreach ( Marketur_App_Kit::tabs_list() as $t ) $out[] = [ 'label' => $t['label'], 'url' => $t['url'], 'icon' => $t['icon'] ];
        return array_slice( $out, 0, 8 );
    }

    private static function posts( $n, $big = false ) {
        $q = new WP_Query( [ 'post_type' => 'post', 'posts_per_page' => $n, 'ignore_sticky_posts' => true, 'no_found_rows' => true ] );
        if ( ! $q->have_posts() ) return '';
        $h = '<div class="mah-list' . ( $big ? ' big' : '' ) . '">';
        while ( $q->have_posts() ) {
            $q->the_post();
            $img = get_the_post_thumbnail_url( get_the_ID(), $big ? 'large' : 'thumbnail' );
            $h .= '<a class="mah-post" href="' . esc_url( get_permalink() ) . '">' . ( $img ? '<span class="mah-img" style="background-image:url(' . esc_url( $img ) . ')"></span>' : '' ) . '<span class="mah-pt"><strong>' . esc_html( get_the_title() ) . '</strong>' . ( $big ? '<span class="mah-ex">' . esc_html( wp_trim_words( get_the_excerpt(), 22 ) ) . '</span>' : '' ) . '<span class="mah-date">' . esc_html( get_the_date() ) . '</span></span></a>';
        }
        wp_reset_postdata();
        $blog = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );
        return $h . '</div><a class="mah-more" href="' . esc_url( $blog ) . '">See all</a>';
    }

    private static function store() {
        if ( ! function_exists( 'wc_get_products' ) ) return self::section( 'Latest', self::posts( 6, true ) );
        $h = '';
        $cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 10, 'parent' => 0 ] );
        if ( $cats && ! is_wp_error( $cats ) ) {
            $h .= '<div class="mah-chips">';
            foreach ( $cats as $c ) if ( $c->slug !== 'uncategorized' ) $h .= '<a href="' . esc_url( get_term_link( $c ) ) . '">' . esc_html( $c->name ) . '</a>';
            $h .= '</div>';
        }
        $ids = wc_get_products( [ 'status' => 'publish', 'limit' => 8, 'featured' => true, 'return' => 'ids' ] );
        if ( ! $ids ) $ids = wc_get_products( [ 'status' => 'publish', 'limit' => 8, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' ] );
        $g = '<div class="mah-grid">';
        foreach ( $ids as $id ) {
            $p = wc_get_product( $id );
            if ( ! $p ) continue;
            $img = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
            $g .= '<a class="mah-prod" href="' . esc_url( $p->get_permalink() ) . '"><span class="mah-pimg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span><strong>' . esc_html( $p->get_name() ) . '</strong><span class="mah-price">' . wp_kses_post( $p->get_price_html() ) . '</span></a>';
        }
        $g .= '</div>';
        $shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
        return $h . self::section( 'Popular', $g . '<a class="mah-more" href="' . esc_url( $shop ) . '">Shop all</a>' );
    }

    private static function section( $title, $body ) {
        return $body ? '<section class="mah-sec"><h2>' . esc_html( $title ) . '</h2>' . $body . '</section>' : '';
    }

    public static function render( $atts = [] ) {
        $s = Marketur_App_Kit::get();
        $tpl = sanitize_key( $atts['template'] ?? '' ) ?: ( $s['home_template'] ?? 'dashboard' );
        if ( ! isset( self::templates()[ $tpl ] ) ) $tpl = 'dashboard';
        $c = $s['colors'];
        $u = wp_get_current_user();
        $hello = is_user_logged_in() && $u->first_name ? 'Hi, ' . $u->first_name : ( $s['home_headline'] ?: 'Welcome to ' . get_bloginfo( 'name' ) );
        $icon = get_site_icon_url( 128 );
        $h = '<div class="mah mah-' . esc_attr( $tpl ) . '" style="--a:' . esc_attr( $c['active'] ) . ';--ink:' . esc_attr( $c['ink'] ) . ';--mu:' . esc_attr( $c['muted'] ) . ';--pa:' . esc_attr( $c['paper'] ) . '">';
        $h .= '<header class="mah-top">' . ( $icon ? '<img src="' . esc_url( $icon ) . '" alt="" width="44" height="44">' : '' ) . '<div><h1>' . esc_html( $hello ) . '</h1>' . ( get_bloginfo( 'description' ) ? '<p>' . esc_html( get_bloginfo( 'description' ) ) . '</p>' : '' ) . '</div></header>';

        if ( $tpl === 'services' ) {
            $acts = [];
            if ( $s['home_phone'] ) $acts[] = [ 'Call', 'tel:' . preg_replace( '/[^0-9+]/', '', $s['home_phone'] ), 'phone' ];
            if ( $s['home_book'] ) $acts[] = [ 'Book', $s['home_book'], 'calendar' ];
            if ( $s['home_address'] ) $acts[] = [ 'Directions', 'https://maps.apple.com/?q=' . rawurlencode( $s['home_address'] ), 'map' ];
            if ( $s['home_email'] ) $acts[] = [ 'Email', 'mailto:' . $s['home_email'], 'mail' ];
            if ( $acts ) {
                $h .= '<div class="mah-acts">';
                foreach ( $acts as $a ) $h .= '<a href="' . esc_url( $a[1], [ 'http', 'https', 'tel', 'mailto' ] ) . '">' . self::icon( $a[2] ) . '<span>' . esc_html( $a[0] ) . '</span></a>';
                $h .= '</div>';
            }
            if ( $s['home_hours'] ) $h .= self::section( 'Opening hours', '<div class="mah-card mah-hours">' . nl2br( esc_html( $s['home_hours'] ) ) . ( $s['home_address'] ? '<p class="mah-addr">' . esc_html( $s['home_address'] ) . '</p>' : '' ) . '</div>' );
        }

        if ( $tpl !== 'feed' ) {
            $tiles = self::tiles();
            if ( $tiles ) {
                $t = '<div class="mah-tiles">';
                foreach ( $tiles as $x ) $t .= '<a href="' . esc_url( $x['url'], [ 'http', 'https', 'tel', 'mailto' ] ) . '"><span class="mah-ti">' . self::icon( $x['icon'] ) . '</span><span>' . esc_html( $x['label'] ) . '</span></a>';
                $h .= self::section( $tpl === 'store' ? 'Shortcuts' : 'Quick links', $t . '</div>' );
            }
        }
        if ( $tpl === 'store' ) $h .= self::store();
        if ( $tpl === 'dashboard' ) $h .= self::section( 'Latest', self::posts( 4 ) );
        if ( $tpl === 'feed' ) $h .= self::posts( 12, true );
        if ( $tpl === 'services' ) $h .= self::section( 'News', self::posts( 3 ) );
        $h .= '</div>';
        return $h . self::css();
    }

    private static function css() {
        static $done = false;
        if ( $done ) return '';
        $done = true;
        return '<style>
.mah{max-width:720px;margin:0 auto;padding:18px 16px 28px;color:var(--ink);font-family:-apple-system,system-ui,"Segoe UI",Roboto,sans-serif}
.mah *{box-sizing:border-box}.mah a{text-decoration:none;color:inherit}
.mah-top{display:flex;gap:12px;align-items:center;margin:4px 0 18px}.mah-top img{border-radius:12px}
.mah-top h1{font-size:26px;line-height:1.15;margin:0;letter-spacing:-.02em;color:var(--ink)}.mah-top p{margin:2px 0 0;color:var(--mu);font-size:14px}
.mah-sec{margin:0 0 22px}.mah-sec h2{font-size:17px;margin:0 0 10px;color:var(--ink)}
.mah-tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.mah-tiles a{display:flex;flex-direction:column;align-items:center;gap:6px;font-size:12px;font-weight:600;text-align:center;color:var(--ink)}
.mah-ti{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--a) 12%,transparent);color:var(--a)}
.mah-acts{display:grid;grid-template-columns:repeat(auto-fit,minmax(70px,1fr));gap:10px;margin:0 0 22px}
.mah-acts a{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 6px;border-radius:16px;background:var(--a);color:#fff!important;font-weight:700;font-size:13px}
.mah-card{background:color-mix(in srgb,var(--ink) 4%,transparent);border-radius:14px;padding:14px;line-height:1.6}.mah-addr{margin:8px 0 0;color:var(--mu)}
.mah-list{display:flex;flex-direction:column;gap:10px}
.mah-post{display:flex;gap:12px;align-items:center;padding:10px;border-radius:14px;background:color-mix(in srgb,var(--ink) 4%,transparent)}
.mah-img{flex:0 0 64px;height:64px;border-radius:10px;background:#ddd center/cover}
.mah-pt{display:flex;flex-direction:column;gap:3px;min-width:0}.mah-pt strong{font-size:15px;line-height:1.3;color:var(--ink)}
.mah-date{font-size:12px;color:var(--mu)}.mah-ex{font-size:14px;color:var(--mu);line-height:1.45}
.mah-list.big .mah-post{flex-direction:column;align-items:stretch;padding:0;overflow:hidden}
.mah-list.big .mah-img{flex:none;width:100%;height:auto;aspect-ratio:16/9;border-radius:0}
.mah-list.big .mah-pt{padding:12px 14px 14px}.mah-list.big .mah-pt strong{font-size:18px}
.mah-more{display:inline-block;margin-top:10px;font-weight:700;color:var(--a)!important}
.mah-chips{display:flex;gap:8px;overflow-x:auto;margin:0 -16px 18px;padding:0 16px 4px}
.mah-chips a{flex:0 0 auto;padding:8px 14px;border-radius:999px;background:color-mix(in srgb,var(--a) 12%,transparent);color:var(--a)!important;font-weight:700;font-size:13px}
.mah-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.mah-prod{display:flex;flex-direction:column;gap:6px}.mah-prod strong{font-size:14px;line-height:1.3}
.mah-pimg{aspect-ratio:1;border-radius:14px;background:#eee center/cover}.mah-price{font-size:14px;color:var(--a);font-weight:700}
@media(min-width:600px){.mah-grid{grid-template-columns:repeat(3,1fr)}}
</style>';
    }
}

MAK_Home::init();
