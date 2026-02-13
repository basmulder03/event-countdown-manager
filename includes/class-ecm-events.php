<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ECM_Events {
    const POST_TYPE = 'event_countdown';

    const META_START_UTC = '_ecm_event_start_utc';
    const META_TIMEZONE  = '_ecm_event_timezone';
    const META_CTA_TEXT  = '_ecm_event_cta_text';
    const META_CTA_URL   = '_ecm_event_cta_url';

    private $language;

    public function __construct( ECM_Language $language ) {
        $this->language = $language;

        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'init', array( __CLASS__, 'register_meta' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ) );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
    }

    public static function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Events', 'event-countdown-manager' ),
                    'singular_name'      => __( 'Event', 'event-countdown-manager' ),
                    'add_new'            => __( 'Add Event', 'event-countdown-manager' ),
                    'add_new_item'       => __( 'Add New Event', 'event-countdown-manager' ),
                    'edit_item'          => __( 'Edit Event', 'event-countdown-manager' ),
                    'new_item'           => __( 'New Event', 'event-countdown-manager' ),
                    'view_item'          => __( 'View Event', 'event-countdown-manager' ),
                    'search_items'       => __( 'Search Events', 'event-countdown-manager' ),
                    'not_found'          => __( 'No events found.', 'event-countdown-manager' ),
                    'not_found_in_trash' => __( 'No events found in Trash.', 'event-countdown-manager' ),
                ),
                'public'             => true,
                'show_in_rest'       => true,
                'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
                'has_archive'        => true,
                'rewrite'            => array( 'slug' => 'events' ),
                'menu_icon'          => 'dashicons-clock',
            )
        );
    }

    public static function register_meta() {
        register_post_meta(
            self::POST_TYPE,
            self::META_START_UTC,
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback'=> function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_TIMEZONE,
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback'=> function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CTA_TEXT,
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback'=> function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_post_meta(
            self::POST_TYPE,
            self::META_CTA_URL,
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback'=> function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'ecm_event_schedule',
            __( 'Event Countdown Details', 'event-countdown-manager' ),
            array( $this, 'render_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'ecm_save_event_meta', 'ecm_event_meta_nonce' );

        $start_utc = get_post_meta( $post->ID, self::META_START_UTC, true );
        $timezone  = get_post_meta( $post->ID, self::META_TIMEZONE, true );
        $timezone  = $this->normalize_timezone( $timezone );
        $cta_text  = get_post_meta( $post->ID, self::META_CTA_TEXT, true );
        $cta_url   = get_post_meta( $post->ID, self::META_CTA_URL, true );

        $start_date = '';
        $start_time = '';
        if ( $start_utc ) {
            try {
                $date = new DateTime( $start_utc, new DateTimeZone( 'UTC' ) );
                $date->setTimezone( new DateTimeZone( $timezone ) );
                $start_date = $date->format( 'Y-m-d' );
                $start_time = $date->format( 'H:i' );
            } catch ( Exception $e ) {
                $start_date = '';
                $start_time = '';
            }
        }

        $translations = $this->language->event_translations( $post->ID );
        ?>
        <p>
            <label for="ecm_event_start_date"><strong><?php esc_html_e( 'Start date', 'event-countdown-manager' ); ?></strong></label><br>
            <input type="date" id="ecm_event_start_date" name="ecm_event_start_date" value="<?php echo esc_attr( $start_date ); ?>" required>
        </p>
        <p>
            <label for="ecm_event_start_time"><strong><?php esc_html_e( 'Start time', 'event-countdown-manager' ); ?></strong></label><br>
            <input type="time" id="ecm_event_start_time" name="ecm_event_start_time" value="<?php echo esc_attr( $start_time ); ?>" step="60" required>
        </p>
        <p>
            <label for="ecm_event_timezone"><strong><?php esc_html_e( 'Timezone', 'event-countdown-manager' ); ?></strong></label><br>
            <select id="ecm_event_timezone" name="ecm_event_timezone" required>
                <?php echo wp_timezone_choice( $timezone ); ?>
            </select>
        </p>
        <p>
            <label for="ecm_event_cta_text"><strong><?php esc_html_e( 'Call-to-action label', 'event-countdown-manager' ); ?></strong></label><br>
            <input type="text" id="ecm_event_cta_text" name="ecm_event_cta_text" value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text">
        </p>
        <p>
            <label for="ecm_event_cta_url"><strong><?php esc_html_e( 'Call-to-action URL', 'event-countdown-manager' ); ?></strong></label><br>
            <input type="url" id="ecm_event_cta_url" name="ecm_event_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" class="regular-text">
        </p>
        <p>
            <strong><?php esc_html_e( 'Detected translations', 'event-countdown-manager' ); ?>:</strong>
            <?php
            $labels = array();
            foreach ( $translations as $lang => $event_id ) {
                $labels[] = esc_html( $lang . ' (#' . (int) $event_id . ')' );
            }
            echo ! empty( $labels ) ? implode( ', ', $labels ) : esc_html__( 'None', 'event-countdown-manager' );
            ?>
        </p>
        <?php
    }

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['ecm_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ecm_event_meta_nonce'] ) ), 'ecm_save_event_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $timezone = isset( $_POST['ecm_event_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['ecm_event_timezone'] ) ) : '';
        $timezone = $this->normalize_timezone( $timezone );

        $start_local = '';
        if ( ! empty( $_POST['ecm_event_start_date'] ) && ! empty( $_POST['ecm_event_start_time'] ) ) {
            $start_date  = sanitize_text_field( wp_unslash( $_POST['ecm_event_start_date'] ) );
            $start_time  = sanitize_text_field( wp_unslash( $_POST['ecm_event_start_time'] ) );
            $start_local = $start_date . ' ' . $start_time;
        } elseif ( ! empty( $_POST['ecm_event_start'] ) ) {
            $start_local = sanitize_text_field( wp_unslash( $_POST['ecm_event_start'] ) );
        }

        if ( '' !== $start_local ) {
            try {
                $date = new DateTime( $start_local, new DateTimeZone( $timezone ) );
                $date->setTimezone( new DateTimeZone( 'UTC' ) );
                update_post_meta( $post_id, self::META_START_UTC, $date->format( 'Y-m-d H:i:s' ) );
                update_post_meta( $post_id, self::META_TIMEZONE, $timezone );
            } catch ( Exception $e ) {
                // Keep previous value when parsing fails.
            }
        }

        $cta_text = isset( $_POST['ecm_event_cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['ecm_event_cta_text'] ) ) : '';
        $cta_url  = isset( $_POST['ecm_event_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['ecm_event_cta_url'] ) ) : '';

        update_post_meta( $post_id, self::META_CTA_TEXT, $cta_text );
        update_post_meta( $post_id, self::META_CTA_URL, $cta_url );
    }

    public function columns( $columns ) {
        $columns['ecm_start']    = __( 'Start (UTC)', 'event-countdown-manager' );
        $columns['ecm_language'] = __( 'Language', 'event-countdown-manager' );
        return $columns;
    }

    public function render_column( $column, $post_id ) {
        if ( 'ecm_start' === $column ) {
            echo esc_html( (string) get_post_meta( $post_id, self::META_START_UTC, true ) );
        }

        if ( 'ecm_language' === $column ) {
            echo esc_html( $this->language->post_language( $post_id ) );
        }
    }

    public static function event_start_timestamp( $event_id ) {
        $start_utc = get_post_meta( $event_id, self::META_START_UTC, true );
        if ( ! $start_utc ) {
            return null;
        }

        $timestamp = strtotime( $start_utc . ' UTC' );
        return $timestamp ? (int) $timestamp : null;
    }

    private function normalize_timezone( $timezone ) {
        $timezone = is_string( $timezone ) ? trim( $timezone ) : '';
        if ( '' === $timezone ) {
            $timezone = wp_timezone_string();
        }

        $valid_timezones = timezone_identifiers_list();
        if ( in_array( $timezone, $valid_timezones, true ) ) {
            return $timezone;
        }

        return 'UTC';
    }
}
