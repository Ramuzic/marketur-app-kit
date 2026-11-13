<?php
/**
 * Starter Kit (App Kit 1.5.0).
 *
 * For people starting from scratch, or who want their site rebuilt: one button installs what
 * the assistant needs to build a site (the Astra theme, Elementor, and the Marketur plugins
 * from their plan, including Marketur MCP), switches the MCP plugin on, and makes the basic
 * pages (Home, About, Contact, Privacy Policy, Delete Your Account, App Home). Then they tell
 * the Assistant what the site is about and it builds it.
 *
 * Nothing already there is replaced: a plugin that is installed is only switched on, a page
 * that exists is left alone, and the theme only changes when the box for it is ticked.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Starter {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 21 );
        add_action( 'admin_post_mak_starter', [ __CLASS__, 'run' ] );
    }

    public static function menu() {
        add_submenu_page( 'marketur-app-kit', 'Starter Kit', 'Starter Kit', 'manage_options', 'mak-starter', [ __CLASS__, 'page' ] );
    }

    private static function upgrader_files() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/theme-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    /** Installs a plugin zip from a URL if its folder is missing, then switches it on. */
    private static function plugin( $folder, $zip_url, &$log ) {
        $all = get_plugins();
        $file = '';
        foreach ( array_keys( $all ) as $f ) if ( strpos( $f, $folder . '/' ) === 0 ) { $file = $f; break; }
        if ( ! $file ) {
            $up = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
            $ok = $up->install( $zip_url );
            if ( ! $ok || is_wp_error( $ok ) ) { $log[] = 'Could not install ' . $folder . '.'; return false; }
            wp_clean_plugins_cache();
            foreach ( array_keys( get_plugins() ) as $f ) if ( strpos( $f, $folder . '/' ) === 0 ) { $file = $f; break; }
            $log[] = 'Installed ' . $folder . '.';
        }
        if ( $file && ! is_plugin_active( $file ) ) {
            $r = activate_plugin( $file );
            $log[] = is_wp_error( $r ) ? 'Could not switch on ' . $folder . ': ' . $r->get_error_message() : 'Switched on ' . $folder . '.';
        }
        return (bool) $file;
    }

    private static function page_exists( $slug ) {
        $p = get_page_by_path( $slug );
        return $p && $p->post_status !== 'trash' ? (int) $p->ID : 0;
    }

    private static function make_page( $title, $slug, $content, &$log ) {
        $id = self::page_exists( $slug );
        if ( $id ) return $id;
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ] );
        if ( $id && ! is_wp_error( $id ) ) { $log[] = 'Made the ' . $title . ' page.'; return (int) $id; }
        return 0;
    }

    public static function run() {
        if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'manage_options' ) ) wp_die( 'You need to be a site admin who can install plugins.' );
        check_admin_referer( 'mak_starter' );
        @set_time_limit( 300 );
        self::upgrader_files();
        $log = [];
        $want = array_map( 'sanitize_key', (array) ( $_POST['parts'] ?? [] ) );

        if ( in_array( 'theme', $want, true ) ) {
            if ( ! wp_get_theme( 'astra' )->exists() ) {
                $api = themes_api( 'theme_information', [ 'slug' => 'astra', 'fields' => [ 'sections' => false ] ] );
                if ( ! is_wp_error( $api ) ) {
                    $ok = ( new Theme_Upgrader( new Automatic_Upgrader_Skin() ) )->install( $api->download_link );
                    $log[] = ( $ok && ! is_wp_error( $ok ) ) ? 'Installed the Astra theme.' : 'Could not install the Astra theme.';
                }
            }
            if ( wp_get_theme( 'astra' )->exists() && get_stylesheet() !== 'astra' ) { switch_theme( 'astra' ); $log[] = 'Switched the theme to Astra.'; }
        }
        if ( in_array( 'elementor', $want, true ) ) {
            $api = plugins_api( 'plugin_information', [ 'slug' => 'elementor', 'fields' => [ 'sections' => false ] ] );
            if ( ! is_wp_error( $api ) ) self::plugin( 'elementor', $api->download_link, $log );
        }
        if ( in_array( 'marketur', $want, true ) ) {
            $r = MAK_AI_Tools::hub( 'starter' );
            if ( is_wp_error( $r ) ) $log[] = 'Marketur plugins: ' . $r->get_error_message();
            else foreach ( (array) ( $r['plugins'] ?? [] ) as $p ) self::plugin( sanitize_key( $p['folder'] ), esc_url_raw( $p['url'] ), $log );
            // The assistant builds the site through Marketur MCP: switch it on with everything ticked.
            if ( class_exists( 'MMCP_FullAccess' ) ) { MMCP_FullAccess::apply(); $log[] = 'Switched on Marketur MCP with full access for the Assistant (you can untick things under Settings > Marketur MCP).'; }
        }
        if ( in_array( 'pages', $want, true ) ) {
            $name = get_bloginfo( 'name' );
            $home = self::make_page( 'Home', 'home', '<!-- wp:paragraph --><p>Welcome to ' . esc_html( $name ) . '. Ask the Assistant to build this page.</p><!-- /wp:paragraph -->', $log );
            self::make_page( 'About', 'about', '<!-- wp:paragraph --><p>Ask the Assistant to write this page about your business.</p><!-- /wp:paragraph -->', $log );
            self::make_page( 'Contact', 'contact', '<!-- wp:paragraph --><p>Ask the Assistant to add your contact details here.</p><!-- /wp:paragraph -->', $log );
            $del = self::make_page( 'Delete Your Account', 'delete-account', '[mak_delete_account]', $log );
            if ( ! (int) get_option( 'wp_page_for_privacy_policy' ) || get_post_status( (int) get_option( 'wp_page_for_privacy_policy' ) ) !== 'publish' ) {
                $pp = self::make_page( 'Privacy Policy', 'privacy-policy', '<!-- wp:paragraph --><p>Ask the Assistant to write a privacy policy for your site and app.</p><!-- /wp:paragraph -->', $log );
                if ( $pp ) { update_option( 'wp_page_for_privacy_policy', $pp ); wp_update_post( [ 'ID' => $pp, 'post_status' => 'publish' ] ); }
            }
            if ( $home && get_option( 'show_on_front' ) !== 'page' ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $home ); $log[] = 'Set Home as the front page.'; }
            $s = (array) get_option( MAK_OPT, [] );
            if ( $del ) $s['delete_page'] = $del;
            update_option( MAK_OPT, $s );
            if ( class_exists( 'MAK_Home' ) ) MAK_Home::ensure_page();
        }
        set_transient( 'mak_starter_log_' . get_current_user_id(), $log ?: [ 'Nothing needed doing.' ], 600 );
        wp_safe_redirect( admin_url( 'admin.php?page=mak-starter&done=1' ) ); exit;
    }

    public static function page() {
        $log = get_transient( 'mak_starter_log_' . get_current_user_id() );
        if ( $log ) delete_transient( 'mak_starter_log_' . get_current_user_id() );
        $connected = (bool) Marketur_App_Kit::get( 'hub_key' );
        ?>
<div class="wrap">
  <h1>Starter Kit</h1>
  <p style="max-width:760px">Starting a new site, or want yours rebuilt? This installs what the Assistant needs to build it, then you tell the Assistant what the site is about. Already have a good site? Skip this and go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=mak-launch' ) ); ?>">Launch</a>.</p>
  <?php if ( $log ) echo '<div class="notice notice-success"><ul style="margin:8px 0 8px 18px;list-style:disc"><li>' . implode( '</li><li>', array_map( 'esc_html', $log ) ) . '</li></ul><p>Next: open the <a href="' . esc_url( admin_url( 'admin.php?page=mak-assistant' ) ) . '">Assistant</a> and tell it about your business, for example: <em>Build my site. I run a dog grooming shop in Charlotte, here is what we offer...</em></p></div>'; ?>
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 18px;max-width:760px">
    <input type="hidden" name="action" value="mak_starter"><?php wp_nonce_field( 'mak_starter' ); ?>
    <p><label><input type="checkbox" name="parts[]" value="theme"> <strong>Astra theme</strong> (switches your theme to Astra. Leave this off if you like your current design)</label></p>
    <p><label><input type="checkbox" name="parts[]" value="elementor" checked> <strong>Elementor</strong> page builder</label></p>
    <p><label><input type="checkbox" name="parts[]" value="marketur" checked <?php disabled( ! $connected ); ?>> <strong>Marketur plugins from your plan</strong>, including Marketur MCP, which lets the Assistant build your pages<?php if ( ! $connected ) echo ' <em>(connect to Marketur under App Kit &gt; Your app first)</em>'; ?></label></p>
    <p><label><input type="checkbox" name="parts[]" value="pages" checked> <strong>Starter pages</strong>: Home, About, Contact, Privacy Policy, Delete Your Account and the App Home screen (pages you already have are left alone)</label></p>
    <p><button class="button button-primary button-hero">Set it up</button></p>
    <p class="description">Takes a minute or two. Nothing you already have is deleted.</p>
  </form>
</div>
        <?php
    }
}

MAK_Starter::init();
