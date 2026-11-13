<?php
/**
 * AI tools for the app (App Kit 1.2.0).
 *
 * Adds app tools to the Marketur MCP plugin on this site (through its mmcp_extension_tools
 * filter), so any AI connected to the site can change the app the same way it changes pages:
 * read the settings, change tabs, colors, bar style and store text, undo the last change,
 * run the readiness check, and start a new build through Marketur.
 *
 * Every tool still goes through the MCP plugin's own checks (token, capability, the write
 * switch, the audit log). Builds go through Marketur with this site's App Kit key, so the
 * site never holds any build service or store keys.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_AI_Tools {

    const PREV = 'mak_settings_prev';

    public static function init() {
        add_filter( 'mmcp_extension_tools', [ __CLASS__, 'tools' ] );
    }

    private static function icons() {
        return [ 'home', 'shop', 'cart', 'account', 'search', 'news', 'chat', 'community', 'bell', 'heart', 'star', 'map', 'phone', 'calendar', 'play', 'grid', 'info', 'menu' ];
    }

    public static function tools( $tools ) {
        $tools['app_get_settings'] = [
            'description' => 'Read everything about this site\'s phone app (Marketur App Kit): name, start page, bar style (floating, fixed or none), tabs, colors, first launch screens, store text, what is hidden inside the app, whether live settings are on, and exactly what the app receives right now. Read this before changing anything.',
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
            'callback'    => [ __CLASS__, 'get' ],
            'write'       => false,
            'cap'         => 'manage_options',
        ];
        $tools['app_update_settings'] = [
            'description' => 'Change the phone app. Only the fields you pass change. With live settings on, tabs, colors, bar style, start page and first launch screens reach the app the next time it is opened, with no new build. The name, icon and store text need a new build (app_build). The previous settings are kept and app_restore_settings puts them back. Tabs are lines "Label|/path/|icon", up to 5. Icons: ' . implode( ', ', self::icons() ) . '. Onboarding is lines "Title|Text"; the last screen asks for notifications. Colors are hex: top (behind the clock), bar (tab bar), active and inactive (tab icons), paper and ink (onboarding and offline screens), muted.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'app_name'    => [ 'type' => 'string', 'description' => 'Up to 30 characters. Needs a new build.' ],
                    'start'       => [ 'type' => 'string', 'description' => 'Full URL on this site the app opens on.' ],
                    'nav'         => [ 'type' => 'string', 'enum' => array_keys( Marketur_App_Kit::navs() ), 'description' => 'Bar style.' ],
                    'tabs'        => [ 'type' => 'string', 'description' => 'One tab per line: Label|/path/|icon' ],
                    'colors'      => [ 'type' => 'object', 'description' => 'Any of top, bar, active, inactive, paper, ink, muted as #RRGGBB.' ],
                    'onboarding'  => [ 'type' => 'string', 'description' => 'One screen per line: Title|Text' ],
                    'hide'        => [ 'type' => 'string', 'description' => 'CSS selectors hidden inside the app, one per line.' ],
                    'css'         => [ 'type' => 'string', 'description' => 'Extra CSS that only loads inside the app (pages there have html.mak-app).' ],
                    'subtitle'    => [ 'type' => 'string', 'description' => 'Store subtitle, up to 30 characters.' ],
                    'description' => [ 'type' => 'string', 'description' => 'Store description, up to 4000 characters.' ],
                    'keywords'    => [ 'type' => 'string', 'description' => 'App Store keywords, comma separated, up to 100 characters.' ],
                    'live'        => [ 'type' => 'boolean', 'description' => 'Turn live settings on or off.' ],
                ],
            ],
            'callback'    => [ __CLASS__, 'update' ],
            'write'       => true,
            'cap'         => 'manage_options',
        ];
        $tools['app_restore_settings'] = [
            'description' => 'Put the phone app settings back to what they were before the last change.',
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
            'callback'    => [ __CLASS__, 'restore' ],
            'write'       => true,
            'cap'         => 'manage_options',
        ];
        $tools['app_check'] = [
            'description' => 'Run the App Kit readiness check: the things Apple and Google reject apps for (no privacy page, sign up without a delete account page, social sign-in without Sign in with Apple, digital sales, small icon, missing app link settings). Returns each check with how to fix it.',
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
            'callback'    => [ __CLASS__, 'check' ],
            'write'       => false,
            'cap'         => 'manage_options',
        ];
        $tools['app_build'] = [
            'description' => 'Start a new build of the phone app through Marketur. kind: ios (new iPhone build to TestFlight), store (new screenshots and store pages), android (new Google Play build), check (what is left before submitting). Sends the current app settings with it. Builds are limited per month by the plan, so only build when the name, icon, store text or something native changed; tabs, colors and bar style do not need one when live settings are on. Never submits the app for review.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'kind'    => [ 'type' => 'string', 'enum' => [ 'ios', 'store', 'android', 'check' ] ],
                    'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true to start. Without it, says what would happen and how many builds are left.' ],
                ],
                'required'   => [ 'kind' ],
            ],
            'callback'    => [ __CLASS__, 'build' ],
            'write'       => true,
            'cap'         => 'manage_options',
        ];
        $tools['app_launch_status'] = [
            'description' => 'Where this app is on the way into the App Store and Google Play: which keys have been handed over, which builds are done, which steps the owner ticked as done on the Launch page, and the exact app name, bundle id and SKU to type into App Store Connect. Use it to tell the owner the next step.',
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
            'callback'    => [ __CLASS__, 'launch' ],
            'write'       => false,
            'cap'         => 'manage_options',
        ];
        $tools['app_build_status'] = [
            'description' => 'How the latest phone app builds went (running, finished, failed and why), from Marketur.',
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
            'callback'    => [ __CLASS__, 'status' ],
            'write'       => false,
            'cap'         => 'manage_options',
        ];
        return $tools;
    }

    public static function get( $a ) {
        $s = Marketur_App_Kit::get();
        unset( $s['hub_key'] );
        return [
            'settings'     => $s,
            'bar_styles'   => Marketur_App_Kit::navs(),
            'icons'        => self::icons(),
            'app_receives' => Marketur_App_Kit::get( 'live' ) ? Marketur_App_Kit::live_config() : 'Live settings are off, so the app uses the settings it was built with.',
            'can_undo'     => (bool) get_option( self::PREV ),
            'connected_to_marketur' => (bool) Marketur_App_Kit::get( 'hub_key' ),
        ];
    }

    public static function update( $a ) {
        $before = Marketur_App_Kit::get();
        $in = [];
        foreach ( [ 'app_name', 'start', 'nav', 'tabs', 'colors', 'onboarding', 'hide', 'css', 'subtitle', 'description', 'keywords', 'live' ] as $k ) {
            if ( array_key_exists( $k, $a ) ) $in[ $k ] = $a[ $k ];
        }
        if ( ! $in ) return new WP_Error( 'nothing', 'Pass at least one field to change.' );
        if ( isset( $in['nav'] ) && ! isset( Marketur_App_Kit::navs()[ $in['nav'] ] ) ) return new WP_Error( 'bad_nav', 'Bar style must be one of: ' . implode( ', ', array_keys( Marketur_App_Kit::navs() ) ) );
        if ( isset( $in['start'] ) && ! self::own( $in['start'] ) ) return new WP_Error( 'bad_start', 'The start page has to be on this site.' );
        if ( isset( $in['tabs'] ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", (string) $in['tabs'] ) ) );
            if ( count( $lines ) > 5 ) return new WP_Error( 'too_many', 'Five tabs at most.' );
            foreach ( $lines as $l ) {
                $p = array_map( 'trim', explode( '|', $l ) );
                if ( count( $p ) < 2 ) return new WP_Error( 'bad_tab', 'Each tab is Label|/path/|icon. This line is not: ' . $l );
                if ( preg_match( '#^https?://#', $p[1] ) && ! self::own( $p[1] ) ) return new WP_Error( 'bad_tab', 'Tabs must open pages on this site: ' . $p[1] );
                if ( ! empty( $p[2] ) && ! in_array( $p[2], self::icons(), true ) ) return new WP_Error( 'bad_icon', 'Unknown icon ' . $p[2] . '. Use one of: ' . implode( ', ', self::icons() ) );
            }
        }
        if ( isset( $in['app_name'] ) && mb_strlen( $in['app_name'] ) > 30 ) return new WP_Error( 'too_long', 'The app name can be 30 characters at most.' );
        if ( isset( $in['subtitle'] ) && mb_strlen( $in['subtitle'] ) > 30 ) return new WP_Error( 'too_long', 'The subtitle can be 30 characters at most.' );
        if ( isset( $in['keywords'] ) && strlen( $in['keywords'] ) > 100 ) return new WP_Error( 'too_long', 'Keywords can be 100 characters at most.' );

        update_option( self::PREV, $before, false );
        $after = Marketur_App_Kit::update_app( $in );
        $changed = [];
        foreach ( array_keys( $in ) as $k ) if ( ( $before[ $k ] ?? null ) !== ( $after[ $k ] ?? null ) ) $changed[] = $k;
        $needs_build = array_values( array_intersect( $changed, [ 'app_name', 'subtitle', 'description', 'keywords' ] ) );
        $live_keys = array_values( array_intersect( $changed, [ 'start', 'nav', 'tabs', 'colors', 'onboarding' ] ) );
        $notes = [];
        if ( $live_keys ) $notes[] = $after['live'] ? 'The app picks up ' . implode( ', ', $live_keys ) . ' the next time it is opened.' : 'Live settings are off, so the app will not see ' . implode( ', ', $live_keys ) . ' until they are turned on (live: true) or a new build is made.';
        if ( array_intersect( $changed, [ 'hide', 'css' ] ) ) $notes[] = 'What is hidden and the extra CSS apply to the site inside the app straight away (clear the page cache if it does not show).';
        if ( $needs_build ) $notes[] = implode( ', ', $needs_build ) . ' only reach the stores with a new build: app_build ios and app_build store.';
        return [ 'ok' => true, 'changed' => $changed, 'notes' => $notes, 'undo' => 'app_restore_settings puts the previous settings back.' ];
    }

    public static function restore( $a ) {
        $prev = get_option( self::PREV );
        if ( ! is_array( $prev ) ) return new WP_Error( 'no_prev', 'There is no earlier version to go back to.' );
        $now = Marketur_App_Kit::get();
        $prev['hub_key'] = $now['hub_key'] ?? '';
        $prev['updated'] = time();
        update_option( MAK_OPT, $prev );
        update_option( self::PREV, $now, false );
        return [ 'ok' => true, 'note' => 'Back to the settings before the last change. Calling this again swaps back.' ];
    }

    public static function check( $a ) {
        $out = [];
        foreach ( Marketur_App_Kit::checks() as $c ) $out[] = [ 'ok' => $c['ok'], 'check' => $c['label'], 'fix' => $c['ok'] ? '' : $c['fix'] ];
        return [ 'checks' => $out, 'all_ok' => ! in_array( false, array_column( $out, 'ok' ), true ) ];
    }

    private static function own( $url ) {
        $h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $me = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $me = 0 === strpos( $me, 'www.' ) ? substr( $me, 4 ) : $me;
        return $h !== '' && ( $h === $me || substr( $h, -strlen( '.' . $me ) ) === '.' . $me );
    }

    /* ---------------------------------------------------------------- Marketur */

    public static function hub( $path, $body = null ) {
        $key = (string) Marketur_App_Kit::get( 'hub_key' );
        if ( $key === '' ) return new WP_Error( 'no_key', 'This site is not connected to Marketur yet. Paste the App Kit key from your Marketur account under Settings > App Kit.' );
        $url = trailingslashit( (string) apply_filters( 'mak_hub_url', 'https://marketur.net' ) ) . 'wp-json/mak-hub/v1/' . $path;
        $args = [ 'timeout' => 25, 'headers' => [ 'X-MAK-Key' => $key, 'X-MAK-Site' => home_url( '/' ), 'Content-Type' => 'application/json' ] ];
        if ( $body !== null ) { $args['body'] = wp_json_encode( $body ); $r = wp_remote_post( $url, $args ); }
        else $r = wp_remote_get( $url, $args );
        if ( is_wp_error( $r ) ) return new WP_Error( 'hub_down', 'Could not reach Marketur: ' . $r->get_error_message() );
        $j = json_decode( wp_remote_retrieve_body( $r ), true );
        $code = (int) wp_remote_retrieve_response_code( $r );
        if ( $code >= 400 ) return new WP_Error( $j['code'] ?? 'hub_error', $j['message'] ?? ( 'Marketur said ' . $code ) );
        return is_array( $j ) ? $j : [];
    }

    /** What Marketur needs to make the build: the store-facing settings from this site. */
    private static function payload() {
        $s = Marketur_App_Kit::get();
        $pp = (int) get_option( 'wp_page_for_privacy_policy' );
        return [
            'name'        => $s['app_name'],
            'start'       => $s['start'],
            'nav'         => $s['nav'],
            'colors'      => $s['colors'],
            'tabs'        => Marketur_App_Kit::tabs_list(),
            'onboarding'  => Marketur_App_Kit::onboarding_list(),
            'subtitle'    => $s['subtitle'],
            'description' => $s['description'],
            'keywords'    => $s['keywords'],
            'privacy_url' => $pp ? get_permalink( $pp ) : '',
            'icon_url'    => get_site_icon_url( 1024 ),
            'plugin'      => MAK_VERSION,
        ];
    }

    public static function build( $a ) {
        $kind = (string) ( $a['kind'] ?? '' );
        if ( ! in_array( $kind, [ 'ios', 'store', 'android', 'check' ], true ) ) return new WP_Error( 'bad_kind', 'kind is ios, store, android or check.' );
        if ( empty( $a['confirm'] ) ) {
            $q = self::hub( 'quota' );
            return [ 'dry_run' => true, 'kind' => $kind, 'quota' => is_wp_error( $q ) ? $q->get_error_message() : $q, 'note' => 'Nothing started. Call again with confirm=true.' ];
        }
        return self::hub( 'build', [ 'kind' => $kind, 'settings' => self::payload() ] );
    }

    public static function launch( $a ) {
        $r = self::hub( 'launch' );
        if ( is_wp_error( $r ) ) return $r;
        $r['ticked_by_owner'] = (array) get_option( 'mak_launch_flags', [] );
        $r['launch_page'] = admin_url( 'admin.php?page=mak-launch' );
        return $r;
    }

    public static function status( $a ) {
        return self::hub( 'status' );
    }
}

MAK_AI_Tools::init();
