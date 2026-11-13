<?php
/**
 * The App Kit admin look (App Kit 1.7.0, 79 icons 1.8.0).
 *
 * One clean style for every App Kit screen (Your app, Assistant, Launch, Starter Kit), the
 * tab icons (the same shapes the app draws, so what you pick is what you get), the tab and
 * shortcut editor with an icon picker, and the phone preview on the Your app page.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Admin_UI {

    const PAGES = [ 'marketur-app-kit', 'mak-assistant', 'mak-launch', 'mak-starter' ];

    public static function init() {
        add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
        add_action( 'admin_head', [ __CLASS__, 'css' ] );
    }

    private static function ours() {
        return is_admin() && isset( $_GET['page'] ) && in_array( sanitize_key( $_GET['page'] ), self::PAGES, true );
    }

    public static function body_class( $c ) {
        return self::ours() ? $c . ' mak-ui' : $c;
    }

    /** name => [ label, path ]. The same shapes the Android app draws (the iPhone app shows the matching system icon). */
    public static function icons() {
        return [
            'home' => [ 'Home', 'M10,20v-6h4v6h5v-8h3L12,3 2,12h3v8z' ],
            'shop' => [ 'Shop', 'M18,6h-2c0,-2.21 -1.79,-4 -4,-4S8,3.79 8,6H6c-1.1,0 -2,0.9 -2,2v12c0,1.1 0.9,2 2,2h12c1.1,0 2,-0.9 2,-2V8C20,6.9 19.1,6 18,6zM12,4c1.1,0 2,0.9 2,2h-4C10,4.9 10.9,4 12,4zM18,20H6V8h2v2c0,0.55 0.45,1 1,1s1,-0.45 1,-1V8h4v2c0,0.55 0.45,1 1,1s1,-0.45 1,-1V8h2V20z' ],
            'cart' => [ 'Cart', 'M7,18c-1.1,0 -1.99,0.9 -1.99,2S5.9,22 7,22s2,-0.9 2,-2 -0.9,-2 -2,-2zM1,2v2h2l3.6,7.59 -1.35,2.45c-0.16,0.28 -0.25,0.61 -0.25,0.96 0,1.1 0.9,2 2,2h12v-2L7.42,15c-0.14,0 -0.25,-0.11 -0.25,-0.25l0.03,-0.12 0.9,-1.63h7.45c0.75,0 1.41,-0.41 1.75,-1.03l3.58,-6.49c0.08,-0.14 0.12,-0.31 0.12,-0.48 0,-0.55 -0.45,-1 -1,-1H5.21l-0.94,-2H1zM17,18c-1.1,0 -1.99,0.9 -1.99,2s0.89,2 1.99,2 2,-0.9 2,-2 -0.9,-2 -2,-2z' ],
            'account' => [ 'Account', 'M12,12c2.21,0 4,-1.79 4,-4s-1.79,-4 -4,-4 -4,1.79 -4,4 1.79,4 4,4zM12,14c-2.67,0 -8,1.34 -8,4v2h16v-2c0,-2.66 -5.33,-4 -8,-4z' ],
            'search' => [ 'Search', 'M15.5,14h-0.79l-0.28,-0.27C15.41,12.59 16,11.11 16,9.5 16,5.91 13.09,3 9.5,3S3,5.91 3,9.5 5.91,16 9.5,16c1.61,0 3.09,-0.59 4.23,-1.57l0.27,0.28v0.79l5,4.99L20.49,19l-4.99,-5zM9.5,14C7.01,14 5,11.99 5,9.5S7.01,5 9.5,5 14,7.01 14,9.5 11.99,14 9.5,14z' ],
            'news' => [ 'News', 'M19,3H5C3.9,3 3,3.9 3,5v14c0,1.1 0.9,2 2,2h14c1.1,0 2,-0.9 2,-2V5C21,3.9 20.1,3 19,3zM14,17H7v-2h7V17zM17,13H7v-2h10V13zM17,9H7V7h10V9z' ],
            'chat' => [ 'Chat', 'M21,6h-2v9H6v2c0,0.55 0.45,1 1,1h11l4,4V7c0,-0.55 -0.45,-1 -1,-1zM17,12V3c0,-0.55 -0.45,-1 -1,-1H3c-0.55,0 -1,0.45 -1,1v14l4,-4h10c0.55,0 1,-0.45 1,-1z' ],
            'community' => [ 'Community', 'M16,11c1.66,0 2.99,-1.34 2.99,-3S17.66,5 16,5c-1.66,0 -3,1.34 -3,3s1.34,3 3,3zM8,11c1.66,0 2.99,-1.34 2.99,-3S9.66,5 8,5C6.34,5 5,6.34 5,8s1.34,3 3,3zM8,13c-2.33,0 -7,1.17 -7,3.5V19h14v-2.5c0,-2.33 -4.67,-3.5 -7,-3.5zM16,13c-0.29,0 -0.62,0.02 -0.97,0.05 1.16,0.84 1.97,1.97 1.97,3.45V19h6v-2.5c0,-2.33 -4.67,-3.5 -7,-3.5z' ],
            'bell' => [ 'Alerts', 'M12,22c1.1,0 2,-0.9 2,-2h-4c0,1.1 0.89,2 2,2zM18,16v-5c0,-3.07 -1.64,-5.64 -4.5,-6.32V4c0,-0.83 -0.67,-1.5 -1.5,-1.5s-1.5,0.67 -1.5,1.5v0.68C7.63,5.36 6,7.92 6,11v5l-2,2v1h16v-1l-2,-2z' ],
            'heart' => [ 'Saved', 'M12,21.35l-1.45,-1.32C5.4,15.36 2,12.28 2,8.5 2,5.42 4.42,3 7.5,3c1.74,0 3.41,0.81 4.5,2.09C13.09,3.81 14.76,3 16.5,3 19.58,3 22,5.42 22,8.5c0,3.78 -3.4,6.86 -8.55,11.54L12,21.35z' ],
            'star' => [ 'Star', 'M12,17.27L18.18,21l-1.64,-7.03L22,9.24l-7.19,-0.61L12,2 9.19,8.63 2,9.24l5.46,4.73L5.82,21z' ],
            'map' => [ 'Map', 'M12,2C8.13,2 5,5.13 5,9c0,5.25 7,13 7,13s7,-7.75 7,-13c0,-3.87 -3.13,-7 -7,-7zM12,11.5c-1.38,0 -2.5,-1.12 -2.5,-2.5s1.12,-2.5 2.5,-2.5 2.5,1.12 2.5,2.5 -1.12,2.5 -2.5,2.5z' ],
            'phone' => [ 'Call', 'M6.62,10.79c1.44,2.83 3.76,5.14 6.59,6.59l2.2,-2.2c0.27,-0.27 0.67,-0.36 1.02,-0.24 1.12,0.37 2.33,0.57 3.57,0.57 0.55,0 1,0.45 1,1V20c0,0.55 -0.45,1 -1,1 -9.39,0 -17,-7.61 -17,-17 0,-0.55 0.45,-1 1,-1h3.5c0.55,0 1,0.45 1,1 0,1.25 0.2,2.45 0.57,3.57 0.11,0.35 0.03,0.74 -0.25,1.02l-2.2,2.2z' ],
            'calendar' => [ 'Calendar', 'M17,12h-5v5h5v-5zM16,1v2H8V1H6v2H5c-1.11,0 -1.99,0.9 -1.99,2L3,19c0,1.1 0.89,2 2,2h14c1.1,0 2,-0.9 2,-2V5c0,-1.1 -0.9,-2 -2,-2h-1V1h-2zM19,19H5V8h14v11z' ],
            'play' => [ 'Play', 'M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM10,16.5v-9l6,4.5 -6,4.5z' ],
            'reels' => [ 'Videos', 'M20,4H4C2.9,4 2,4.9 2,6v12c0,1.1 0.9,2 2,2h16c1.1,0 2,-0.9 2,-2V6C22,4.9 21.1,4 20,4zM9.5,16.5v-9l7,4.5L9.5,16.5z' ],
            'grid' => [ 'Grid', 'M4,8h4V4H4v4zM10,20h4v-4h-4v4zM4,20h4v-4H4v4zM4,14h4v-4H4v4zM10,14h4v-4h-4v4zM16,4v4h4V4h-4zM10,8h4V4h-4v4zM16,14h4v-4h-4v4zM16,20h4v-4h-4v4z' ],
            'info' => [ 'Info', 'M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM13,17h-2v-6h2v6zM13,9h-2V7h2v2z' ],
            'menu' => [ 'Menu', 'M3,18h18v-2H3v2zM3,13h18v-2H3v2zM3,6v2h18V6H3z' ],
            'next' => [ 'Explore', 'M12,10.9c-0.61,0 -1.1,0.49 -1.1,1.1s0.49,1.1 1.1,1.1c0.61,0 1.1,-0.49 1.1,-1.1s-0.49,-1.1 -1.1,-1.1zM12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM14.19,14.19L6,18l3.81,-8.19L18,6l-3.81,8.19z' ],
            'share' => [ 'Share', 'M16,5l-1.42,1.42 -1.59,-1.59V16h-1.98V4.83L9.42,6.42 8,5l4,-4 4,4zM20,10v11c0,1.1 -0.9,2 -2,2H6c-1.11,0 -2,-0.9 -2,-2V10c0,-1.11 0.89,-2 2,-2h3v2H6v11h12V10h-3V8h3c1.1,0 2,0.89 2,2z' ],
            'gift' => [ 'Gifts', 'M9.06,1.93C7.17,1.92 5.33,3.74 6.17,6H3A2,2 0 0,0 1,8V10A1,1 0 0,0 2,11H11V8H13V11H22A1,1 0 0,0 23,10V8A2,2 0 0,0 21,6H17.83C19,2.73 14.6,0.42 12.57,3.24L12,4L11.43,3.22C10.8,2.33 9.93,1.94 9.06,1.93M9,4C9.89,4 10.34,5.08 9.71,5.71C9.08,6.34 8,5.89 8,5A1,1 0 0,1 9,4M15,4C15.89,4 16.34,5.08 15.71,5.71C15.08,6.34 14,5.89 14,5A1,1 0 0,1 15,4M2,12V20A2,2 0 0,0 4,22H20A2,2 0 0,0 22,20V12H13V20H11V12H2Z' ],
            'tag' => [ 'Deals', 'M5.5,7A1.5,1.5 0 0,1 4,5.5A1.5,1.5 0 0,1 5.5,4A1.5,1.5 0 0,1 7,5.5A1.5,1.5 0 0,1 5.5,7M21.41,11.58L12.41,2.58C12.05,2.22 11.55,2 11,2H4C2.89,2 2,2.89 2,4V11C2,11.55 2.22,12.05 2.59,12.41L11.58,21.41C11.95,21.77 12.45,22 13,22C13.55,22 14.05,21.77 14.41,21.41L21.41,14.41C21.78,14.05 22,13.55 22,13C22,12.44 21.77,11.94 21.41,11.58Z' ],
            'receipt' => [ 'Orders', 'M3,22L4.5,20.5L6,22L7.5,20.5L9,22L10.5,20.5L12,22L13.5,20.5L15,22L16.5,20.5L18,22L19.5,20.5L21,22V2L19.5,3.5L18,2L16.5,3.5L15,2L13.5,3.5L12,2L10.5,3.5L9,2L7.5,3.5L6,2L4.5,3.5L3,2M18,9H6V7H18M18,13H6V11H18M18,17H6V15H18V17Z' ],
            'card' => [ 'Pay', 'M20 4H4A2 2 0 0 0 2 6V18A2 2 0 0 0 4 20H20A2 2 0 0 0 22 18V6A2 2 0 0 0 20 4M20 11H4V8H20Z' ],
            'wallet' => [ 'Wallet', 'M21,18V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5A2,2 0 0,1 5,3H19A2,2 0 0,1 21,5V6H12C10.89,6 10,6.9 10,8V16A2,2 0 0,0 12,18M12,16H22V8H12M16,13.5A1.5,1.5 0 0,1 14.5,12A1.5,1.5 0 0,1 16,10.5A1.5,1.5 0 0,1 17.5,12A1.5,1.5 0 0,1 16,13.5Z' ],
            'store' => [ 'Store', 'M5.06 3C4.63 3 4.22 3.14 3.84 3.42C3.46 3.7 3.24 4.06 3.14 4.5L2.11 8.91C1.86 10 2.06 10.92 2.69 11.73C2.81 11.85 2.93 11.97 3.04 12.07C3.63 12.64 4.28 13 5.22 13C6.16 13 6.91 12.59 7.47 12.05C8.1 12.67 8.86 13 9.8 13C10.64 13 11.44 12.63 12 12.07C12.68 12.7 13.45 13 14.3 13C15.17 13 15.91 12.67 16.54 12.05C17.11 12.62 17.86 13 18.81 13C19.76 13 20.43 12.65 21 12.06C21.09 11.97 21.18 11.87 21.28 11.77C21.94 10.95 22.14 10 21.89 8.91L20.86 4.5C20.73 4.06 20.5 3.7 20.13 3.42C19.77 3.14 19.38 3 18.94 3M18.89 4.97L19.97 9.38C20.06 9.81 19.97 10.2 19.69 10.55C19.44 10.86 19.13 11 18.75 11C18.44 11 18.17 10.9 17.95 10.66C17.73 10.43 17.61 10.16 17.58 9.84L16.97 5M5.06 5H7.03L6.42 9.84C6.3 10.63 5.91 11 5.25 11C4.84 11 4.53 10.86 4.31 10.55C4.03 10.2 3.94 9.81 4.03 9.38M9.05 5H11V9.7C11 10.05 10.89 10.35 10.64 10.62C10.39 10.88 10.08 11 9.7 11C9.36 11 9.07 10.88 8.84 10.59C8.61 10.3 8.5 10 8.5 9.66V9.5M13 5H14.95L15.5 9.5C15.58 9.92 15.5 10.27 15.21 10.57C14.95 10.87 14.61 11 14.2 11C13.89 11 13.61 10.88 13.36 10.62C13.11 10.35 13 10.05 13 9.7M3 14.03V19C3 20.11 3.89 21 5 21C9.67 21 14.33 21 19 21C20.1 21 21 20.11 21 19V14.05C20.45 14.63 19.75 14.96 19 15C18 15.03 17.25 14.74 16.54 14.05C15.94 14.65 15.14 15 14.3 15C13.4 15 12.6 14.64 12 14.07C11.43 14.64 10.65 15 9.78 15C8.87 15 8.07 14.65 7.47 14.05C6.89 14.64 6.1 15 5.23 15C4.33 15 3.66 14.65 3 14.03Z' ],
            'truck' => [ 'Shipping', 'M3,4A2,2 0 0,0 1,6V17H3A3,3 0 0,0 6,20A3,3 0 0,0 9,17H15A3,3 0 0,0 18,20A3,3 0 0,0 21,17H23V12L20,8H17V4M10,6L14,10L10,14V11H4V9H10M17,9.5H19.5L21.47,12H17M6,15.5A1.5,1.5 0 0,1 7.5,17A1.5,1.5 0 0,1 6,18.5A1.5,1.5 0 0,1 4.5,17A1.5,1.5 0 0,1 6,15.5M18,15.5A1.5,1.5 0 0,1 19.5,17A1.5,1.5 0 0,1 18,18.5A1.5,1.5 0 0,1 16.5,17A1.5,1.5 0 0,1 18,15.5Z' ],
            'ticket' => [ 'Tickets', 'M15.58,16.8L12,14.5L8.42,16.8L9.5,12.68L6.21,10L10.46,9.74L12,5.8L13.54,9.74L17.79,10L14.5,12.68M20,12C20,10.89 20.9,10 22,10V6C22,4.89 21.1,4 20,4H4A2,2 0 0,0 2,6V10C3.11,10 4,10.9 4,12A2,2 0 0,1 2,14V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V14A2,2 0 0,1 20,12Z' ],
            'bookmark' => [ 'Bookmarks', 'M17,3H7A2,2 0 0,0 5,5V21L12,18L19,21V5C19,3.89 18.1,3 17,3Z' ],
            'book' => [ 'Book', 'M12 21.5C10.65 20.65 8.2 20 6.5 20C4.85 20 3.15 20.3 1.75 21.05C1.65 21.1 1.6 21.1 1.5 21.1C1.25 21.1 1 20.85 1 20.6V6C1.6 5.55 2.25 5.25 3 5C4.11 4.65 5.33 4.5 6.5 4.5C8.45 4.5 10.55 4.9 12 6C13.45 4.9 15.55 4.5 17.5 4.5C18.67 4.5 19.89 4.65 21 5C21.75 5.25 22.4 5.55 23 6V20.6C23 20.85 22.75 21.1 22.5 21.1C22.4 21.1 22.35 21.1 22.25 21.05C20.85 20.3 19.15 20 17.5 20C15.8 20 13.35 20.65 12 21.5M12 8V19.5C13.35 18.65 15.8 18 17.5 18C18.7 18 19.9 18.15 21 18.5V7C19.9 6.65 18.7 6.5 17.5 6.5C15.8 6.5 13.35 7.15 12 8M13 11.5C14.11 10.82 15.6 10.5 17.5 10.5C18.41 10.5 19.26 10.59 20 10.78V9.23C19.13 9.08 18.29 9 17.5 9C15.73 9 14.23 9.28 13 9.84V11.5M17.5 11.67C15.79 11.67 14.29 11.93 13 12.46V14.15C14.11 13.5 15.6 13.16 17.5 13.16C18.54 13.16 19.38 13.24 20 13.4V11.9C19.13 11.74 18.29 11.67 17.5 11.67M20 14.57C19.13 14.41 18.29 14.33 17.5 14.33C15.67 14.33 14.17 14.6 13 15.13V16.82C14.11 16.16 15.6 15.83 17.5 15.83C18.54 15.83 19.38 15.91 20 16.07V14.57Z' ],
            'school' => [ 'Learn', 'M12,3L1,9L12,15L21,10.09V17H23V9M5,13.18V17.18L12,21L19,17.18V13.18L12,17L5,13.18Z' ],
            'document' => [ 'Docs', 'M13,9H18.5L13,3.5V9M6,2H14L20,8V20A2,2 0 0,1 18,22H6C4.89,22 4,21.1 4,20V4C4,2.89 4.89,2 6,2M15,18V16H6V18H15M18,14V12H6V14H18Z' ],
            'mail' => [ 'Email', 'M20,8L12,13L4,8V6L12,11L20,6M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z' ],
            'megaphone' => [ 'Announce', 'M12,8H4A2,2 0 0,0 2,10V14A2,2 0 0,0 4,16H5V20A1,1 0 0,0 6,21H8A1,1 0 0,0 9,20V16H12L17,20V4L12,8M21.5,12C21.5,13.71 20.54,15.26 19,16V8C20.53,8.75 21.5,10.3 21.5,12Z' ],
            'send' => [ 'Send', 'M2,21L23,12L2,3V10L17,12L2,14V21Z' ],
            'link' => [ 'Links', 'M3.9,12C3.9,10.29 5.29,8.9 7,8.9H11V7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 3.9,12M8,13H16V11H8V13M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.71 18.71,15.1 17,15.1H13V17H17A5,5 0 0,0 22,12A5,5 0 0,0 17,7Z' ],
            'camera' => [ 'Camera', 'M4,4H7L9,2H15L17,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7M12,9A3,3 0 0,1 15,12A3,3 0 0,1 12,15A3,3 0 0,1 9,12A3,3 0 0,1 12,9Z' ],
            'photo' => [ 'Photos', 'M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z' ],
            'music' => [ 'Music', 'M21,3V15.5A3.5,3.5 0 0,1 17.5,19A3.5,3.5 0 0,1 14,15.5A3.5,3.5 0 0,1 17.5,12C18.04,12 18.55,12.12 19,12.34V6.47L9,8.6V17.5A3.5,3.5 0 0,1 5.5,21A3.5,3.5 0 0,1 2,17.5A3.5,3.5 0 0,1 5.5,14C6.04,14 6.55,14.12 7,14.34V6L21,3Z' ],
            'mic' => [ 'Podcast', 'M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z' ],
            'video' => [ 'Video', 'M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z' ],
            'tv' => [ 'Watch', 'M21,3H3C1.89,3 1,3.89 1,5V17A2,2 0 0,0 3,19H8V21H16V19H21A2,2 0 0,0 23,17V5C23,3.89 22.1,3 21,3M21,17H3V5H21M16,11L9,15V7' ],
            'game' => [ 'Games', 'M7,6H17A6,6 0 0,1 23,12A6,6 0 0,1 17,18C15.22,18 13.63,17.23 12.53,16H11.47C10.37,17.23 8.78,18 7,18A6,6 0 0,1 1,12A6,6 0 0,1 7,6M6,9V11H4V13H6V15H8V13H10V11H8V9H6M15.5,12A1.5,1.5 0 0,0 14,13.5A1.5,1.5 0 0,0 15.5,15A1.5,1.5 0 0,0 17,13.5A1.5,1.5 0 0,0 15.5,12M18.5,9A1.5,1.5 0 0,0 17,10.5A1.5,1.5 0 0,0 18.5,12A1.5,1.5 0 0,0 20,10.5A1.5,1.5 0 0,0 18.5,9Z' ],
            'live' => [ 'Live', 'M12 10C10.9 10 10 10.9 10 12S10.9 14 12 14 14 13.1 14 12 13.1 10 12 10M18 12C18 8.7 15.3 6 12 6S6 8.7 6 12C6 14.2 7.2 16.1 9 17.2L10 15.5C8.8 14.8 8 13.5 8 12.1C8 9.9 9.8 8.1 12 8.1S16 9.9 16 12.1C16 13.6 15.2 14.9 14 15.5L15 17.2C16.8 16.2 18 14.2 18 12M12 2C6.5 2 2 6.5 2 12C2 15.7 4 18.9 7 20.6L8 18.9C5.6 17.5 4 14.9 4 12C4 7.6 7.6 4 12 4S20 7.6 20 12C20 15 18.4 17.5 16 18.9L17 20.6C20 18.9 22 15.7 22 12C22 6.5 17.5 2 12 2Z' ],
            'pin' => [ 'Places', 'M12,2C15.31,2 18,4.66 18,7.95C18,12.41 12,19 12,19C12,19 6,12.41 6,7.95C6,4.66 8.69,2 12,2M12,6A2,2 0 0,0 10,8A2,2 0 0,0 12,10A2,2 0 0,0 14,8A2,2 0 0,0 12,6M20,19C20,21.21 16.42,23 12,23C7.58,23 4,21.21 4,19C4,17.71 5.22,16.56 7.11,15.83L7.75,16.74C6.67,17.19 6,17.81 6,18.5C6,19.88 8.69,21 12,21C15.31,21 18,19.88 18,18.5C18,17.81 17.33,17.19 16.25,16.74L16.89,15.83C18.78,16.56 20,17.71 20,19Z' ],
            'directions' => [ 'Directions', 'M14,14.5V12H10V15H8V11A1,1 0 0,1 9,10H14V7.5L17.5,11M21.71,11.29L12.71,2.29H12.7C12.31,1.9 11.68,1.9 11.29,2.29L2.29,11.29C1.9,11.68 1.9,12.32 2.29,12.71L11.29,21.71C11.68,22.09 12.31,22.1 12.71,21.71L21.71,12.71C22.1,12.32 22.1,11.68 21.71,11.29Z' ],
            'car' => [ 'Ride', 'M5,11L6.5,6.5H17.5L19,11M17.5,16A1.5,1.5 0 0,1 16,14.5A1.5,1.5 0 0,1 17.5,13A1.5,1.5 0 0,1 19,14.5A1.5,1.5 0 0,1 17.5,16M6.5,16A1.5,1.5 0 0,1 5,14.5A1.5,1.5 0 0,1 6.5,13A1.5,1.5 0 0,1 8,14.5A1.5,1.5 0 0,1 6.5,16M18.92,6C18.72,5.42 18.16,5 17.5,5H6.5C5.84,5 5.28,5.42 5.08,6L3,12V20A1,1 0 0,0 4,21H5A1,1 0 0,0 6,20V19H18V20A1,1 0 0,0 19,21H20A1,1 0 0,0 21,20V12L18.92,6Z' ],
            'plane' => [ 'Travel', 'M20.56 3.91C21.15 4.5 21.15 5.45 20.56 6.03L16.67 9.92L18.79 19.11L17.38 20.53L13.5 13.1L9.6 17L9.96 19.47L8.89 20.53L7.13 17.35L3.94 15.58L5 14.5L7.5 14.87L11.37 11L3.94 7.09L5.36 5.68L14.55 7.8L18.44 3.91C19 3.33 20 3.33 20.56 3.91Z' ],
            'bed' => [ 'Stay', 'M19,7H11V14H3V5H1V20H3V17H21V20H23V11A4,4 0 0,0 19,7M7,13A3,3 0 0,0 10,10A3,3 0 0,0 7,7A3,3 0 0,0 4,10A3,3 0 0,0 7,13Z' ],
            'food' => [ 'Food', 'M11,9H9V2H7V9H5V2H3V9C3,11.12 4.66,12.84 6.75,12.97V22H9.25V12.97C11.34,12.84 13,11.12 13,9V2H11V9M16,6V14H18.5V22H21V2C18.24,2 16,4.24 16,6Z' ],
            'coffee' => [ 'Cafe', 'M2,21H20V19H2M20,8H18V5H20M20,3H4V13A4,4 0 0,0 8,17H14A4,4 0 0,0 18,13V10H20A2,2 0 0,0 22,8V5C22,3.89 21.1,3 20,3Z' ],
            'cake' => [ 'Bakery', 'M12,6C13.11,6 14,5.1 14,4C14,3.62 13.9,3.27 13.71,2.97L12,0L10.29,2.97C10.1,3.27 10,3.62 10,4A2,2 0 0,0 12,6M16.6,16L15.53,14.92L14.45,16C13.15,17.29 10.87,17.3 9.56,16L8.5,14.92L7.4,16C6.75,16.64 5.88,17 4.96,17C4.23,17 3.56,16.77 3,16.39V21A1,1 0 0,0 4,22H20A1,1 0 0,0 21,21V16.39C20.44,16.77 19.77,17 19.04,17C18.12,17 17.25,16.64 16.6,16M18,9H13V7H11V9H6A3,3 0 0,0 3,12V13.54C3,14.62 3.88,15.5 4.96,15.5C5.5,15.5 6,15.3 6.34,14.93L8.5,12.8L10.61,14.93C11.35,15.67 12.64,15.67 13.38,14.93L15.5,12.8L17.65,14.93C18,15.3 18.5,15.5 19.03,15.5C20.11,15.5 21,14.62 21,13.54V12A3,3 0 0,0 18,9Z' ],
            'fitness' => [ 'Fitness', 'M20.57,14.86L22,13.43L20.57,12L17,15.57L8.43,7L12,3.43L10.57,2L9.14,3.43L7.71,2L5.57,4.14L4.14,2.71L2.71,4.14L4.14,5.57L2,7.71L3.43,9.14L2,10.57L3.43,12L7,8.43L15.57,17L12,20.57L13.43,22L14.86,20.57L16.29,22L18.43,19.86L19.86,21.29L21.29,19.86L19.86,18.43L22,16.29L20.57,14.86Z' ],
            'health' => [ 'Health', 'M10,3L8,5V7H5C3.85,7 3.12,8 3,9L2,19C1.88,20 2.54,21 4,21H20C21.46,21 22.12,20 22,19L21,9C20.88,8 20.06,7 19,7H16V5L14,3H10M10,5H14V7H10V5M11,10H13V13H16V15H13V18H11V15H8V13H11V10Z' ],
            'scissors' => [ 'Salon', 'M19,3L13,9L15,11L22,4V3M12,12.5A0.5,0.5 0 0,1 11.5,12A0.5,0.5 0 0,1 12,11.5A0.5,0.5 0 0,1 12.5,12A0.5,0.5 0 0,1 12,12.5M6,20A2,2 0 0,1 4,18C4,16.89 4.9,16 6,16A2,2 0 0,1 8,18C8,19.11 7.1,20 6,20M6,8A2,2 0 0,1 4,6C4,4.89 4.9,4 6,4A2,2 0 0,1 8,6C8,7.11 7.1,8 6,8M9.64,7.64C9.87,7.14 10,6.59 10,6A4,4 0 0,0 6,2A4,4 0 0,0 2,6A4,4 0 0,0 6,10C6.59,10 7.14,9.87 7.64,9.64L10,12L7.64,14.36C7.14,14.13 6.59,14 6,14A4,4 0 0,0 2,18A4,4 0 0,0 6,22A4,4 0 0,0 10,18C10,17.41 9.87,16.86 9.64,16.36L12,14L19,21H22V20L9.64,7.64Z' ],
            'paw' => [ 'Pets', 'M8.35,3C9.53,2.83 10.78,4.12 11.14,5.9C11.5,7.67 10.85,9.25 9.67,9.43C8.5,9.61 7.24,8.32 6.87,6.54C6.5,4.77 7.17,3.19 8.35,3M15.5,3C16.69,3.19 17.35,4.77 17,6.54C16.62,8.32 15.37,9.61 14.19,9.43C13,9.25 12.35,7.67 12.72,5.9C13.08,4.12 14.33,2.83 15.5,3M3,7.6C4.14,7.11 5.69,8 6.5,9.55C7.26,11.13 7,12.79 5.87,13.28C4.74,13.77 3.2,12.89 2.41,11.32C1.62,9.75 1.9,8.08 3,7.6M21,7.6C22.1,8.08 22.38,9.75 21.59,11.32C20.8,12.89 19.26,13.77 18.13,13.28C17,12.79 16.74,11.13 17.5,9.55C18.31,8 19.86,7.11 21,7.6M19.33,18.38C19.37,19.32 18.65,20.36 17.79,20.75C16,21.57 13.88,19.87 11.89,19.87C9.9,19.87 7.76,21.64 6,20.75C5,20.26 4.31,18.96 4.44,17.88C4.62,16.39 6.41,15.59 7.47,14.5C8.88,13.09 9.88,10.44 11.89,10.44C13.89,10.44 14.95,13.05 16.3,14.5C17.41,15.72 19.26,16.75 19.33,18.38Z' ],
            'leaf' => [ 'Nature', 'M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z' ],
            'faith' => [ 'Faith', 'M18 12.22V9L13 6.5V5H15V3H13V1H11V3H9V5H11V6.5L6 9V12.22L2 14V22H10V19C10 17.9 10.9 17 12 17S14 17.9 14 19V22H22V14L18 12.22M12 13.5C11.17 13.5 10.5 12.83 10.5 12S11.17 10.5 12 10.5 13.5 11.17 13.5 12 12.83 13.5 12 13.5Z' ],
            'wrench' => [ 'Services', 'M22.7,19L13.6,9.9C14.5,7.6 14,4.9 12.1,3C10.1,1 7.1,0.6 4.7,1.7L9,6L6,9L1.6,4.7C0.4,7.1 0.9,10.1 2.9,12.1C4.8,14 7.5,14.5 9.8,13.6L18.9,22.7C19.3,23.1 19.9,23.1 20.3,22.7L22.6,20.4C23.1,20 23.1,19.3 22.7,19Z' ],
            'brush' => [ 'Design', 'M20.71,4.63L19.37,3.29C19,2.9 18.35,2.9 17.96,3.29L9,12.25L11.75,15L20.71,6.04C21.1,5.65 21.1,5 20.71,4.63M7,14A3,3 0 0,0 4,17C4,18.31 2.84,19 2,19C2.92,20.22 4.5,21 6,21A4,4 0 0,0 10,17A3,3 0 0,0 7,14Z' ],
            'briefcase' => [ 'Work', 'M10,2H14A2,2 0 0,1 16,4V6H20A2,2 0 0,1 22,8V19A2,2 0 0,1 20,21H4C2.89,21 2,20.1 2,19V8C2,6.89 2.89,6 4,6H8V4C8,2.89 8.89,2 10,2M14,6V4H10V6H14Z' ],
            'chart' => [ 'Stats', 'M22,21H2V3H4V19H6V10H10V19H12V6H16V19H18V14H22V21Z' ],
            'trend' => [ 'Trending', 'M16,6L18.29,8.29L13.41,13.17L9.41,9.17L2,16.59L3.41,18L9.41,12L13.41,16L19.71,9.71L22,12V6H16Z' ],
            'code' => [ 'Code', 'M14.6,16.6L19.2,12L14.6,7.4L16,6L22,12L16,18L14.6,16.6M9.4,16.6L4.8,12L9.4,7.4L8,6L2,12L8,18L9.4,16.6Z' ],
            'clock' => [ 'Hours', 'M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M16.2,16.2L11,13V7H12.5V12.2L17,14.9L16.2,16.2Z' ],
            'trophy' => [ 'Rewards', 'M18 2C17.1 2 16 3 16 4H8C8 3 6.9 2 6 2H2V11C2 12 3 13 4 13H6.2C6.6 15 7.9 16.7 11 17V19.08C8 19.54 8 22 8 22H16C16 22 16 19.54 13 19.08V17C16.1 16.7 17.4 15 17.8 13H20C21 13 22 12 22 11V2H18M6 11H4V4H6V11M20 11H18V4H20V11Z' ],
            'fire' => [ 'Hot', 'M17.66 11.2C17.43 10.9 17.15 10.64 16.89 10.38C16.22 9.78 15.46 9.35 14.82 8.72C13.33 7.26 13 4.85 13.95 3C13 3.23 12.17 3.75 11.46 4.32C8.87 6.4 7.85 10.07 9.07 13.22C9.11 13.32 9.15 13.42 9.15 13.55C9.15 13.77 9 13.97 8.8 14.05C8.57 14.15 8.33 14.09 8.14 13.93C8.08 13.88 8.04 13.83 8 13.76C6.87 12.33 6.69 10.28 7.45 8.64C5.78 10 4.87 12.3 5 14.47C5.06 14.97 5.12 15.47 5.29 15.97C5.43 16.57 5.7 17.17 6 17.7C7.08 19.43 8.95 20.67 10.96 20.92C13.1 21.19 15.39 20.8 17.03 19.32C18.86 17.66 19.5 15 18.56 12.72L18.43 12.46C18.22 12 17.66 11.2 17.66 11.2M14.5 17.5C14.22 17.74 13.76 18 13.4 18.1C12.28 18.5 11.16 17.94 10.5 17.28C11.69 17 12.4 16.12 12.61 15.23C12.78 14.43 12.46 13.77 12.33 13C12.21 12.26 12.23 11.63 12.5 10.94C12.69 11.32 12.89 11.7 13.13 12C13.9 13 15.11 13.44 15.37 14.8C15.41 14.94 15.43 15.08 15.43 15.23C15.46 16.05 15.1 16.95 14.5 17.5H14.5Z' ],
            'bolt' => [ 'Fast', 'M11 15H6L13 1V9H18L11 23V15Z' ],
            'sparkles' => [ 'New', 'M19,1L17.74,3.75L15,5L17.74,6.26L19,9L20.25,6.26L23,5L20.25,3.75M9,4L6.5,9.5L1,12L6.5,14.5L9,20L11.5,14.5L17,12L11.5,9.5M19,15L17.74,17.74L15,19L17.74,20.25L19,23L20.25,20.25L23,19L20.25,17.74' ],
            'globe' => [ 'World', 'M17.9,17.39C17.64,16.59 16.89,16 16,16H15V13A1,1 0 0,0 14,12H8V10H10A1,1 0 0,0 11,9V7H13A2,2 0 0,0 15,5V4.59C17.93,5.77 20,8.64 20,12C20,14.08 19.2,15.97 17.9,17.39M11,19.93C7.05,19.44 4,16.08 4,12C4,11.38 4.08,10.78 4.21,10.21L9,15V16A2,2 0 0,0 11,18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z' ],
            'plus' => [ 'Post', 'M17,13H13V17H11V13H7V11H11V7H13V11H17M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z' ],
            'download' => [ 'Downloads', 'M5,20H19V18H5M19,9H15V3H9V9H5L12,16L19,9Z' ],
            'lock' => [ 'Members', 'M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z' ],
            'shield' => [ 'Secure', 'M10,17L6,13L7.41,11.59L10,14.17L16.59,7.58L18,9M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1Z' ],
            'settings' => [ 'Settings', 'M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.21,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.21,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.67 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z' ],
            'help' => [ 'Help', 'M15.07,11.25L14.17,12.17C13.45,12.89 13,13.5 13,15H11V14.5C11,13.39 11.45,12.39 12.17,11.67L13.41,10.41C13.78,10.05 14,9.55 14,9C14,7.89 13.1,7 12,7A2,2 0 0,0 10,9H8A4,4 0 0,1 12,5A4,4 0 0,1 16,9C16,9.88 15.64,10.67 15.07,11.25M13,19H11V17H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12C22,6.47 17.5,2 12,2Z' ],
            'qr' => [ 'Scan', 'M4,4H10V10H4V4M20,4V10H14V4H20M14,15H16V13H14V11H16V13H18V11H20V13H18V15H20V18H18V20H16V18H13V20H11V16H14V15M16,15V18H18V15H16M4,20V14H10V20H4M6,6V8H8V6H6M16,6V8H18V6H16M6,16V18H8V16H6M4,11H6V13H4V11M9,11H13V15H11V13H9V11M11,6H13V10H11V6M2,2V6H0V2A2,2 0 0,1 2,0H6V2H2M22,0A2,2 0 0,1 24,2V6H22V2H18V0H22M2,18V22H6V24H2A2,2 0 0,1 0,22V18H2M22,22V18H24V22A2,2 0 0,1 22,24H18V22H22Z' ],
            'list' => [ 'List', 'M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z' ],
        ];
    }

    public static function svg( $name, $size = 22 ) {
        $i = self::icons();
        $d = $i[ $name ][1] ?? $i['star'][1];
        return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="currentColor" d="' . esc_attr( $d ) . '"/></svg>';
    }

    public static function sparkle( $size = 16 ) {
        return '<svg class="mak-sp" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="currentColor" d="M11 2l1.9 5.6L18.5 9.5l-5.6 1.9L11 17l-1.9-5.6L3.5 9.5l5.6-1.9L11 2zm7.5 11l.95 2.55L22 16.5l-2.55.95L18.5 20l-.95-2.55L15 16.5l2.55-.95L18.5 13z"/></svg>';
    }

    /** Pages people can point a tab or shortcut at: path => title. */
    public static function paths() {
        $out = [ '/' => 'Home page' ];
        foreach ( get_pages( [ 'post_status' => 'publish', 'number' => 200, 'sort_column' => 'menu_order,post_title' ] ) as $p ) {
            $path = (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH );
            if ( $path !== '' && ! isset( $out[ $path ] ) ) $out[ $path ] = $p->post_title;
        }
        $blog = (int) get_option( 'page_for_posts' );
        if ( $blog ) $out[ (string) wp_parse_url( get_permalink( $blog ), PHP_URL_PATH ) ] = 'Blog';
        return $out;
    }

    public static function css() {
        if ( ! self::ours() ) return;
        ?>
<style id="mak-ui">
body.mak-ui #wpcontent{background:#f5f6fa}
body.mak-ui .wrap{--mk-ink:#101828;--mk-soft:#475467;--mk-line:#e4e7ec;--mk-blue:#2563eb;--mk-violet:#7c3aed;font-size:14px;color:var(--mk-ink)}
body.mak-ui .wrap h1{font-size:26px;font-weight:700;letter-spacing:-.01em}
body.mak-ui .wrap .button{display:inline-flex;align-items:center;gap:7px;min-height:38px;padding:0 15px;border-radius:10px;border:1px solid #d0d5dd;background:#fff;color:#344054;font-weight:600;font-size:13.5px;line-height:1;box-shadow:0 1px 2px rgba(16,24,40,.05);transition:background .15s,border-color .15s,box-shadow .15s,transform .05s;vertical-align:middle;text-decoration:none}
body.mak-ui .wrap .button:hover{background:#f9fafb;border-color:#98a2b3;color:#101828}
body.mak-ui .wrap .button:active{transform:translateY(1px)}
body.mak-ui .wrap .button:focus{outline:0;box-shadow:0 0 0 4px rgba(37,99,235,.18)}
body.mak-ui .wrap .button-primary{background:var(--mk-blue);border-color:var(--mk-blue);color:#fff}
body.mak-ui .wrap .button-primary:hover{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
body.mak-ui .wrap .button-hero{min-height:46px;padding:0 22px;font-size:15px;border-radius:12px}
body.mak-ui .wrap .button[disabled],body.mak-ui .wrap .button:disabled{opacity:.55;cursor:default}
body.mak-ui .wrap .button .dashicons{margin:0;line-height:1}
body.mak-ui .wrap .mak-auto{background:#f5f3ff;border-color:#ddd6fe;color:#6d28d9}
body.mak-ui .wrap .mak-auto:hover{background:#ede9fe;border-color:#c4b5fd;color:#5b21b6}
body.mak-ui .wrap .mak-auto.big{background:linear-gradient(135deg,var(--mk-violet),var(--mk-blue));border:0;color:#fff;min-height:48px;padding:0 24px;font-size:15px;border-radius:12px;box-shadow:0 6px 18px rgba(79,70,229,.28)}
body.mak-ui .wrap .mak-auto.big:hover{filter:brightness(1.07);color:#fff}
body.mak-ui .wrap .button.is-busy .mak-sp{animation:mak-spin 1s linear infinite}
@keyframes mak-spin{to{transform:rotate(360deg)}}
body.mak-ui .wrap input[type=text],body.mak-ui .wrap input[type=email],body.mak-ui .wrap input[type=url],body.mak-ui .wrap input[type=password],body.mak-ui .wrap input[type=number],body.mak-ui .wrap select,body.mak-ui .wrap textarea{border:1px solid #d0d5dd;border-radius:9px;padding:8px 11px;min-height:40px;box-shadow:0 1px 2px rgba(16,24,40,.04);font-size:14px}
body.mak-ui .wrap select{padding-right:30px}
body.mak-ui .wrap input:focus,body.mak-ui .wrap select:focus,body.mak-ui .wrap textarea:focus{border-color:#93b4f7;box-shadow:0 0 0 4px rgba(37,99,235,.12);outline:0}
body.mak-ui .wrap .notice{border-radius:12px;border-left-width:4px;box-shadow:0 1px 2px rgba(16,24,40,.05)}
body.mak-ui .wrap .description{color:var(--mk-soft)}
</style>
        <?php
    }

    /** The tab / shortcut editor and the phone preview script. Printed once on the Your app page. */
    public static function editor_js() {
        $icons = [];
        foreach ( self::icons() as $k => $v ) $icons[ $k ] = [ $v[0], $v[1] ];
        ?>
<datalist id="mak-paths"><?php foreach ( self::paths() as $p => $t ) echo '<option value="' . esc_attr( $p ) . '">' . esc_html( $t ) . '</option>'; ?></datalist>
<div id="mak-pick" class="mak-pick" hidden><div class="mak-pick-h">Pick an icon <input type="search" class="mak-pick-q" placeholder="Search icons"></div><div class="mak-pick-g"></div></div>
<script>
(function(){
  var ICONS=<?php echo wp_json_encode( $icons ); ?>;
  function svg(n,s){var i=ICONS[n]||ICONS.star;s=s||20;return '<svg viewBox="0 0 24 24" width="'+s+'" height="'+s+'" aria-hidden="true"><path fill="currentColor" d="'+i[1]+'"/></svg>';}
  window.makSvg=svg;
  var pick=document.getElementById('mak-pick');document.body.appendChild(pick);var grid=pick.querySelector('.mak-pick-g'),pickFor=null;
  Object.keys(ICONS).forEach(function(k){var b=document.createElement('button');b.type='button';b.dataset.icon=k;b.innerHTML=svg(k,22)+'<span>'+ICONS[k][0]+'</span>';grid.appendChild(b);});
  grid.addEventListener('click',function(e){var b=e.target.closest('button');if(!b||!pickFor)return;pickFor(b.dataset.icon);close();});
  var q=pick.querySelector('.mak-pick-q');q.addEventListener('input',function(){var t=q.value.trim().toLowerCase();grid.querySelectorAll('button').forEach(function(b){b.style.display=!t||b.dataset.icon.indexOf(t)>-1||b.textContent.toLowerCase().indexOf(t)>-1?'':'none';});});
  function close(){pick.hidden=true;pickFor=null;q.value='';grid.querySelectorAll('button').forEach(function(b){b.style.display='';});}
  document.addEventListener('click',function(e){if(!pick.hidden&&!pick.contains(e.target)&&!e.target.closest('.mak-ib'))close();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
  function openPick(btn,cb){var r=btn.getBoundingClientRect();pick.hidden=false;var h=pick.offsetHeight,w=pick.offsetWidth,vw=document.documentElement.clientWidth;var top=r.bottom+6;if(top+h>window.innerHeight&&r.top-h-6>0)top=r.top-h-6;pick.style.top=(window.scrollY+top)+'px';pick.style.left=(window.scrollX+Math.max(8,Math.min(r.left,vw-w-8)))+'px';pickFor=cb;setTimeout(function(){q.focus();},30);}

  document.querySelectorAll('.mak-rows').forEach(function(box){
    var ta=document.getElementById(box.dataset.target),max=+box.dataset.max||5,noun=box.dataset.noun||'tab',rows=[];
    function parse(){rows=ta.value.split('\n').map(function(l){return l.trim();}).filter(Boolean).map(function(l){var p=l.split('|').map(function(x){return x.trim();});return {label:p[0]||'',path:p[1]||'/',icon:p[2]||'star',extra:p.slice(3).join('|')};});}
    function save(){ta.value=rows.filter(function(r){return r.label!==''||r.path!=='';}).map(function(r){return [r.label,r.path||'/',r.icon||'star'].concat(r.extra?[r.extra]:[]).join('|');}).join('\n');ta.dispatchEvent(new CustomEvent('mak:change',{bubbles:true}));}
    function draw(){
      box.innerHTML='';
      rows.forEach(function(r,i){
        var el=document.createElement('div');el.className='mak-row';
        el.innerHTML='<button type="button" class="mak-ib" title="Change the icon">'+svg(r.icon,22)+'</button>'
          +'<input type="text" class="mak-l" placeholder="Label" maxlength="20">'
          +'<input type="text" class="mak-p" placeholder="/page-address/ or a full link" list="mak-paths">'
          +'<span class="mak-mv"><button type="button" class="mak-up" title="Move up">&#8593;</button><button type="button" class="mak-dn" title="Move down">&#8595;</button></span>'
          +'<button type="button" class="mak-x" title="Remove">&times;</button>';
        el.querySelector('.mak-l').value=r.label;el.querySelector('.mak-p').value=r.path;
        el.querySelector('.mak-l').addEventListener('input',function(e){r.label=e.target.value;save();});
        el.querySelector('.mak-p').addEventListener('input',function(e){r.path=e.target.value;save();});
        el.querySelector('.mak-ib').addEventListener('click',function(e){var b=e.currentTarget;openPick(b,function(n){r.icon=n;b.innerHTML=svg(n,22);save();});});
        el.querySelector('.mak-up').addEventListener('click',function(){if(i>0){rows.splice(i-1,0,rows.splice(i,1)[0]);save();draw();}});
        el.querySelector('.mak-dn').addEventListener('click',function(){if(i<rows.length-1){rows.splice(i+1,0,rows.splice(i,1)[0]);save();draw();}});
        el.querySelector('.mak-x').addEventListener('click',function(){rows.splice(i,1);save();draw();});
        box.appendChild(el);
      });
      if(rows.length<max){var a=document.createElement('button');a.type='button';a.className='button mak-add';a.textContent='+ Add a '+noun;a.addEventListener('click',function(){rows.push({label:'',path:'',icon:'star',extra:''});draw();var ls=box.querySelectorAll('.mak-l');ls[ls.length-1].focus();});box.appendChild(a);}
      else{var n=document.createElement('p');n.className='description';n.textContent='That is the most a '+noun+' bar can hold ('+max+').';box.appendChild(n);}
    }
    ta.addEventListener('input',function(){parse();draw();ta.dispatchEvent(new CustomEvent('mak:change',{bubbles:true}));});
    parse();draw();
  });

  /* Phone preview */
  var ph=document.querySelector('.mak-phone');if(!ph)return;
  var bar=ph.querySelector('.mak-pbar'),top=ph.querySelector('.mak-ptop'),form=document.getElementById('mak-form');
  function col(k){var i=form.querySelector('input[name="mak[colors]['+k+']"]');return i?i.value:'#000';}
  function paint(){
    var tabs=(document.getElementById('mak_tabs').value||'').split('\n').map(function(l){return l.trim();}).filter(Boolean).slice(0,5).map(function(l){var p=l.split('|');return {label:(p[0]||'').trim(),icon:(p[2]||'home').trim()};});
    var nav=(form.querySelector('[name="mak[nav]"]')||{}).value||'floating',look=form.querySelector('[name="mak[look]"]:checked');
    if(look&&look.value==='pwa')nav='none';
    ph.dataset.nav=nav;top.style.background=col('top');
    bar.style.background=col('bar');
    bar.innerHTML=tabs.map(function(t,i){return '<span style="color:'+(i===0?col('active'):col('inactive'))+'">'+svg(t.icon,20)+'<b>'+t.label.replace(/[<>&]/g,'')+'</b></span>';}).join('');
    var n=ph.querySelector('.mak-pname');if(n)n.textContent=(form.querySelector('[name="mak[app_name]"]')||{}).value||'';
  }
  form.addEventListener('input',paint);form.addEventListener('change',paint);document.addEventListener('mak:change',paint);paint();
})();
</script>
        <?php
    }
}

MAK_Admin_UI::init();
