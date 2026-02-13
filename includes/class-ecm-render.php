<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ECM_Render {
    private $language;

    public function __construct( ECM_Language $language ) {
        $this->language = $language;

        add_action( 'init', array( $this, 'register_assets' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_theme_variables' ) );
        add_action( 'admin_post_ecm_resync_mirrors', array( $this, 'handle_resync' ) );
        add_action( 'admin_notices', array( $this, 'resync_notice' ) );
    }

    public function register_assets() {
        wp_register_style( 'ecm-frontend', ECM_PLUGIN_URL . 'assets/css/frontend.css', array(), ECM_VERSION );
        wp_register_script( 'ecm-countdown', ECM_PLUGIN_URL . 'assets/js/countdown.js', array(), ECM_VERSION, true );
        wp_register_script( 'ecm-blocks', ECM_PLUGIN_URL . 'assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ), ECM_VERSION, true );
    }

    public function register_shortcodes() {
        add_shortcode( 'ecm_event_countdown', array( $this, 'render_event_shortcode' ) );
        add_shortcode( 'ecm_upcoming_events', array( $this, 'render_list_shortcode' ) );
    }

    public function register_blocks() {
        register_block_type(
            'ecm/event-countdown',
            array(
                'api_version'     => 2,
                'editor_script'   => 'ecm-blocks',
                'render_callback' => array( $this, 'render_event_block' ),
                'attributes'      => array(
                    'eventId' => array( 'type' => 'number', 'default' => 0 ),
                ),
            )
        );

        register_block_type(
            'ecm/upcoming-events',
            array(
                'api_version'     => 2,
                'editor_script'   => 'ecm-blocks',
                'render_callback' => array( $this, 'render_list_block' ),
                'attributes'      => array(
                    'limit' => array( 'type' => 'number', 'default' => 5 ),
                ),
            )
        );
    }

    public function enqueue_theme_variables() {
        $settings = ECM_Settings::get();
        $css      = sprintf(
            ':root{--ecm-primary:%1$s;--ecm-accent:%2$s;--ecm-bg:%3$s;--ecm-text:%4$s;--ecm-radius:%5$spx;--ecm-space-scale:%6$s;}',
            esc_attr( $settings['primary_color'] ),
            esc_attr( $settings['accent_color'] ),
            esc_attr( $settings['background_color'] ),
            esc_attr( $settings['text_color'] ),
            esc_attr( $settings['card_radius'] ),
            esc_attr( $settings['spacing_scale'] )
        );

        wp_register_style( 'ecm-theme-vars', false, array(), ECM_VERSION );
        wp_enqueue_style( 'ecm-theme-vars' );
        wp_add_inline_style( 'ecm-theme-vars', $css );
    }

    public function render_event_block( $attributes ) {
        $event_id = isset( $attributes['eventId'] ) ? (int) $attributes['eventId'] : 0;
        return $this->render_single_event( $event_id );
    }

    public function render_list_block( $attributes ) {
        $limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 5;
        return $this->render_event_list( $limit );
    }

    public function render_event_shortcode( $atts ) {
        $atts    = shortcode_atts( array( 'event_id' => 0 ), $atts, 'ecm_event_countdown' );
        $event_id = (int) $atts['event_id'];

        if ( ! $event_id ) {
            $current_id = get_the_ID();
            if ( $current_id && ECM_Events::POST_TYPE === get_post_type( $current_id ) ) {
                $event_id = (int) $current_id;
            }
        }

        return $this->render_single_event( $event_id );
    }

    public function render_list_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'limit' => 5 ), $atts, 'ecm_upcoming_events' );
        return $this->render_event_list( (int) $atts['limit'] );
    }

    private function render_single_event( $event_id ) {
        $event_id = (int) $event_id;
        if ( ! $event_id ) {
            return '';
        }

        $event = get_post( $event_id );
        if ( ! $event || ECM_Events::POST_TYPE !== $event->post_type ) {
            return '';
        }

        $timestamp = ECM_Events::event_start_timestamp( $event_id );
        if ( ! $timestamp ) {
            return '';
        }

        wp_enqueue_style( 'ecm-frontend' );
        wp_enqueue_script( 'ecm-countdown' );

        $cta_text = get_post_meta( $event_id, ECM_Events::META_CTA_TEXT, true );
        $cta_url  = get_post_meta( $event_id, ECM_Events::META_CTA_URL, true );

        ob_start();
        ?>
        <article class="ecm-countdown-card" data-ecm-countdown="<?php echo esc_attr( gmdate( 'c', $timestamp ) ); ?>">
            <h3 class="ecm-title"><?php echo esc_html( get_the_title( $event_id ) ); ?></h3>
            <div class="ecm-description"><?php echo wp_kses_post( wpautop( $event->post_excerpt ? $event->post_excerpt : wp_trim_words( $event->post_content, 24 ) ) ); ?></div>
            <div class="ecm-timer" aria-live="polite">
                <span data-ecm-days>00</span><small><?php esc_html_e( 'Days', 'event-countdown-manager' ); ?></small>
                <span data-ecm-hours>00</span><small><?php esc_html_e( 'Hours', 'event-countdown-manager' ); ?></small>
                <span data-ecm-minutes>00</span><small><?php esc_html_e( 'Minutes', 'event-countdown-manager' ); ?></small>
                <span data-ecm-seconds>00</span><small><?php esc_html_e( 'Seconds', 'event-countdown-manager' ); ?></small>
            </div>
            <?php if ( $cta_text && $cta_url ) : ?>
                <p><a class="ecm-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a></p>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_list( $limit ) {
        $limit = max( 1, min( 20, (int) $limit ) );

        $events = get_posts(
            array(
                'post_type'      => ECM_Events::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => 'meta_value',
                'meta_key'       => ECM_Events::META_START_UTC,
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => ECM_Events::META_START_UTC,
                        'value'   => gmdate( 'Y-m-d H:i:s' ),
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
            )
        );

        if ( empty( $events ) ) {
            return '<p>' . esc_html__( 'No upcoming events yet.', 'event-countdown-manager' ) . '</p>';
        }

        $out = '<div class="ecm-list">';
        foreach ( $events as $event ) {
            $out .= $this->render_single_event( $event->ID );
        }
        $out .= '</div>';

        return $out;
    }

    public static function ensure_events_page( $force = false ) {
        $settings = ECM_Settings::get();

        if ( empty( $settings['auto_create_page'] ) && ! $force ) {
            return;
        }

        $page_id  = (int) $settings['events_page_id'];
        $existing = $page_id ? get_post( $page_id ) : null;
        $content  = '<!-- wp:ecm/upcoming-events {"limit":10} /-->';

        if ( $existing && 'page' === $existing->post_type ) {
            wp_update_post(
                array(
                    'ID'           => $existing->ID,
                    'post_content' => $content,
                )
            );
            return;
        }

        $page_id = wp_insert_post(
            array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => __( 'Events', 'event-countdown-manager' ),
                'post_content' => $content,
            )
        );

        if ( ! $page_id || is_wp_error( $page_id ) ) {
            return;
        }

        $settings['events_page_id'] = (int) $page_id;
        update_option( ECM_Settings::OPTION_KEY, $settings );
    }

    public function handle_resync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'event-countdown-manager' ) );
        }

        check_admin_referer( 'ecm_resync_mirrors' );

        $events = get_posts(
            array(
                'post_type'      => ECM_Events::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );

        foreach ( $events as $event_id ) {
            do_action( 'save_post_' . ECM_Events::POST_TYPE, (int) $event_id, get_post( $event_id ), true );
        }

        wp_safe_redirect( admin_url( 'edit.php?post_type=' . ECM_Events::POST_TYPE . '&ecm_resync=1' ) );
        exit;
    }

    public function resync_notice() {
        if ( ! is_admin() || ! isset( $_GET['ecm_resync'] ) || '1' !== $_GET['ecm_resync'] ) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mirror posts resynced.', 'event-countdown-manager' ) . '</p></div>';
    }
}
