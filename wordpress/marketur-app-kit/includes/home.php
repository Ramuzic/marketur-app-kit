<?php
/**
 * App home screens (App Kit 1.5.0; the NexURLs look 1.8.0).
 *
 * The page the app opens on. Apple turns down apps that are just a website in a frame
 * (guideline 4.2), and a real home screen is one of the biggest reasons the NexURLs app got
 * through, so every Kit app gets one. Four looks, all built from the site's own content, so
 * they work on any site with or without a page builder:
 *
 *   app        the NexURLs look (includes/home-app.php): dark hero, big main button, colored
 *              shortcuts members can rearrange, swipe rows, topics, community. The default.
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
            'app'       => 'The NexURLs look: greeting, a big main button, colored shortcuts, products, posts and topics (the default)',
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
        return class_exists( 'MAK_Admin_UI' ) ? MAK_Admin_UI::svg( $name, 24 ) : '';
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
        $tpl = sanitize_key( $atts['template'] ?? '' ) ?: ( $s['home_template'] ?? 'app' );
        if ( ! isset( self::templates()[ $tpl ] ) ) $tpl = 'app';
        if ( $tpl === 'app' && class_exists( 'MAK_Home_App' ) ) return MAK_Home_App::render();
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
