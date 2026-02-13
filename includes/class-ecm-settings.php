<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ECM_Settings {
    const OPTION_KEY = 'ecm_settings';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register' ) );
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_ecm_create_events_page', array( $this, 'handle_create_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_event-countdown-manager' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'ecm-admin-settings', ECM_PLUGIN_URL . 'assets/js/admin-settings.js', array( 'jquery', 'wp-color-picker' ), ECM_VERSION, true );
    }

    public static function defaults() {
        return array(
            'sync_enabled'       => 1,
            'primary_color'      => '#14532d',
            'accent_color'       => '#f59e0b',
            'background_color'   => '#f8fafc',
            'text_color'         => '#111827',
            'card_radius'        => '12',
            'spacing_scale'      => '1',
            'auto_create_page'   => 1,
            'events_page_id'     => 0,
        );
    }

    public static function get() {
        return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
    }

    public function register() {
        register_setting( 'ecm_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
    }

    public function sanitize( $input ) {
        $defaults = self::defaults();
        $output   = $defaults;

        $output['sync_enabled']     = empty( $input['sync_enabled'] ) ? 0 : 1;
        $output['auto_create_page'] = empty( $input['auto_create_page'] ) ? 0 : 1;

        $output['primary_color']    = sanitize_hex_color( $input['primary_color'] ?? $defaults['primary_color'] ) ?: $defaults['primary_color'];
        $output['accent_color']     = sanitize_hex_color( $input['accent_color'] ?? $defaults['accent_color'] ) ?: $defaults['accent_color'];
        $output['background_color'] = sanitize_hex_color( $input['background_color'] ?? $defaults['background_color'] ) ?: $defaults['background_color'];
        $output['text_color']       = sanitize_hex_color( $input['text_color'] ?? $defaults['text_color'] ) ?: $defaults['text_color'];

        $output['card_radius']      = (string) max( 0, min( 40, (int) ( $input['card_radius'] ?? $defaults['card_radius'] ) ) );
        $output['spacing_scale']    = (string) max( 1, min( 2, (float) ( $input['spacing_scale'] ?? $defaults['spacing_scale'] ) ) );
        $output['events_page_id']   = (int) ( $input['events_page_id'] ?? $defaults['events_page_id'] );

        return $output;
    }

    public function menu() {
        add_options_page(
            __( 'Event Countdown', 'event-countdown-manager' ),
            __( 'Event Countdown', 'event-countdown-manager' ),
            'manage_options',
            'event-countdown-manager',
            array( $this, 'render_page' )
        );
    }

    public function handle_create_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'event-countdown-manager' ) );
        }

        check_admin_referer( 'ecm_create_events_page' );

        ECM_Render::ensure_events_page( true );

        wp_safe_redirect( admin_url( 'options-general.php?page=event-countdown-manager&ecm_page_created=1' ) );
        exit;
    }

    public function render_page() {
        $settings = self::get();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Event Countdown Settings', 'event-countdown-manager' ); ?></h1>
            <?php if ( isset( $_GET['ecm_page_created'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Events page was created or updated.', 'event-countdown-manager' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields( 'ecm_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable post mirroring', 'event-countdown-manager' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sync_enabled]" value="1" <?php checked( (int) $settings['sync_enabled'], 1 ); ?>> <?php esc_html_e( 'Create/update read-only regular posts from events.', 'event-countdown-manager' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-create events page', 'event-countdown-manager' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_create_page]" value="1" <?php checked( (int) $settings['auto_create_page'], 1 ); ?>> <?php esc_html_e( 'Maintain a page containing the upcoming events block.', 'event-countdown-manager' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Primary color', 'event-countdown-manager' ); ?></th>
                        <td><input type="text" class="regular-text ecm-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Accent color', 'event-countdown-manager' ); ?></th>
                        <td><input type="text" class="regular-text ecm-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Background color', 'event-countdown-manager' ); ?></th>
                        <td><input type="text" class="regular-text ecm-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Text color', 'event-countdown-manager' ); ?></th>
                        <td><input type="text" class="regular-text ecm-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Card radius (px)', 'event-countdown-manager' ); ?></th>
                        <td><input type="number" min="0" max="40" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[card_radius]" value="<?php echo esc_attr( $settings['card_radius'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Spacing scale', 'event-countdown-manager' ); ?></th>
                        <td><input type="number" min="1" max="2" step="0.1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[spacing_scale]" value="<?php echo esc_attr( $settings['spacing_scale'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Events page ID', 'event-countdown-manager' ); ?></th>
                        <td>
                            <input type="number" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[events_page_id]" value="<?php echo esc_attr( $settings['events_page_id'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Managed automatically when auto-create is enabled.', 'event-countdown-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ecm_create_events_page' ); ?>
                <input type="hidden" name="action" value="ecm_create_events_page">
                <?php submit_button( __( 'Create/Update Events Page Now', 'event-countdown-manager' ), 'secondary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                <?php wp_nonce_field( 'ecm_resync_mirrors' ); ?>
                <input type="hidden" name="action" value="ecm_resync_mirrors">
                <?php submit_button( __( 'Resync All Mirror Posts', 'event-countdown-manager' ), 'secondary', 'submit', false ); ?>
            </form>
        </div>
        <?php
    }
}
