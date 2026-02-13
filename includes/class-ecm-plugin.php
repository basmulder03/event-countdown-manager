<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ECM_Plugin {
    private static $instance;

    private $language;
    private $settings;
    private $events;
    private $sync;
    private $render;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        ECM_Events::register_post_type();
        flush_rewrite_rules();
        ECM_Render::ensure_events_page();
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'boot' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_style' ) );

        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'ECM_CLI_Command' ) ) {
            WP_CLI::add_command( 'ecm', 'ECM_CLI_Command' );
        }
    }

    public function enqueue_admin_style( $hook ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }

        $is_settings_screen = 'settings_page_event-countdown-manager' === $hook;
        $is_event_screen    = in_array( $screen->post_type, array( ECM_Events::POST_TYPE ), true );

        if ( ! $is_settings_screen && ! $is_event_screen ) {
            return;
        }

        wp_enqueue_style( 'ecm-admin-style', ECM_PLUGIN_URL . 'assets/css/admin.css', array(), ECM_VERSION );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'event-countdown-manager', false, dirname( plugin_basename( ECM_PLUGIN_FILE ) ) . '/languages' );
    }

    public function boot() {
        $this->language = new ECM_Language();
        $this->settings = new ECM_Settings();
        $this->events   = new ECM_Events( $this->language );
        $this->sync     = new ECM_Sync( $this->language );
        $this->render   = new ECM_Render( $this->language );
    }
}
