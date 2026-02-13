<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ECM_Sync {
    const META_SOURCE_EVENT = '_ecm_source_event_id';
    const META_MIRROR_LANG  = '_ecm_mirror_language';
    const META_IS_MIRROR    = '_ecm_is_mirror';

    private static $syncing = false;

    private $language;

    public function __construct( ECM_Language $language ) {
        $this->language = $language;

        add_action( 'save_post_' . ECM_Events::POST_TYPE, array( $this, 'sync_from_event' ), 30, 3 );
        add_action( 'save_post_post', array( $this, 'restore_mirror_on_save' ), 30, 3 );
        add_filter( 'wp_insert_post_data', array( $this, 'guard_mirror_post_data' ), 20, 2 );
        add_action( 'admin_notices', array( $this, 'mirror_notice' ) );
        add_action( 'admin_head-post.php', array( $this, 'disable_mirror_editor_ui' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'disable_mirror_block_editor_ui' ) );
    }

    public function sync_from_event( $post_id, $post, $update ) {
        if ( self::$syncing ) {
            return;
        }

        if ( ! ( $post instanceof WP_Post ) ) {
            return;
        }

        if ( 'auto-draft' === $post->post_status ) {
            return;
        }

        $settings = ECM_Settings::get();
        if ( empty( $settings['sync_enabled'] ) ) {
            return;
        }

        $translations     = $this->language->event_translations( $post_id );
        $default_language = $this->language->default_language();

        if ( isset( $translations[ $default_language ] ) ) {
            $default_event_id = (int) $translations[ $default_language ];
            unset( $translations[ $default_language ] );
            $translations = array_merge( array( $default_language => $default_event_id ), $translations );
        }

        self::$syncing = true;

        try {
            foreach ( $translations as $lang => $translated_event_id ) {
                $lang      = (string) $lang;
                $mirror_id = $this->upsert_mirror_post( (int) $translated_event_id, $lang );
                if ( ! $mirror_id ) {
                    continue;
                }
            }
        } finally {
            self::$syncing = false;
        }
    }

    private function upsert_mirror_post( $event_id, $language ) {
        $event = get_post( $event_id );
        if ( ! $event || ECM_Events::POST_TYPE !== $event->post_type ) {
            return 0;
        }

        $existing = get_posts(
            array(
                'post_type'      => 'post',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => self::META_SOURCE_EVENT,
                        'value' => $event_id,
                    ),
                    array(
                        'key'   => self::META_MIRROR_LANG,
                        'value' => $language,
                    ),
                ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        $mirror_id = ! empty( $existing ) ? (int) $existing[0] : 0;

        $content_parts   = array();
        $event_content   = trim( (string) $event->post_content );
        $countdown_block = sprintf( '[ecm_event_countdown event_id="%d"]', (int) $event_id );

        if ( '' !== $event_content ) {
            $content_parts[] = $event_content;
        }
        $content_parts[] = $countdown_block;

        $postarr = array(
            'post_type'    => 'post',
            'post_status'  => 'publish' === $event->post_status ? 'publish' : 'draft',
            'post_title'   => $event->post_title,
            'post_excerpt' => $event->post_excerpt,
            'post_content' => implode( "\n\n", $content_parts ),
        );

        if ( $mirror_id ) {
            $postarr['ID'] = $mirror_id;
            wp_update_post( wp_slash( $postarr ) );
        } else {
            $mirror_id = wp_insert_post( wp_slash( $postarr ) );
        }

        if ( ! $mirror_id || is_wp_error( $mirror_id ) ) {
            return 0;
        }

        update_post_meta( $mirror_id, self::META_SOURCE_EVENT, $event_id );
        update_post_meta( $mirror_id, self::META_MIRROR_LANG, $language );
        update_post_meta( $mirror_id, self::META_IS_MIRROR, 1 );

        if ( has_post_thumbnail( $event_id ) ) {
            set_post_thumbnail( $mirror_id, get_post_thumbnail_id( $event_id ) );
        } else {
            delete_post_thumbnail( $mirror_id );
        }

        return (int) $mirror_id;
    }

    public function guard_mirror_post_data( $data, $postarr ) {
        if ( self::$syncing ) {
            return $data;
        }

        $post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        if ( ! $post_id || ! $this->is_mirror_post( $post_id ) ) {
            return $data;
        }

        $existing = get_post( $post_id );
        if ( ! $existing || 'post' !== $existing->post_type ) {
            return $data;
        }

        $data['post_title']   = $existing->post_title;
        $data['post_content'] = $existing->post_content;
        $data['post_excerpt'] = $existing->post_excerpt;

        return $data;
    }

    public function restore_mirror_on_save( $post_id, $post, $update ) {
        if ( self::$syncing || ! $update ) {
            return;
        }

        $source_event_id = (int) get_post_meta( $post_id, self::META_SOURCE_EVENT, true );
        if ( ! $source_event_id ) {
            return;
        }

        self::$syncing = true;
        $lang          = (string) get_post_meta( $post_id, self::META_MIRROR_LANG, true );
        $this->upsert_mirror_post( $source_event_id, $lang ? $lang : $this->language->default_language() );
        self::$syncing = false;

        add_filter(
            'redirect_post_location',
            function ( $location ) {
                return add_query_arg( 'ecm_readonly', '1', $location );
            }
        );
    }

    public function mirror_notice() {
        if ( ! is_admin() || ! isset( $_GET['post'] ) ) {
            return;
        }

        $post_id = (int) $_GET['post'];
        if ( ! $post_id || ! $this->is_mirror_post( $post_id ) ) {
            return;
        }

        echo '<div class="notice notice-info"><p>' . esc_html__( 'This is a read-only mirrored post generated from an Event Countdown item. Edit the source event instead.', 'event-countdown-manager' ) . '</p></div>';
    }

    public function disable_mirror_editor_ui() {
        if ( ! is_admin() || ! isset( $_GET['post'] ) ) {
            return;
        }

        $post_id = (int) $_GET['post'];
        if ( ! $this->is_mirror_post( $post_id ) ) {
            return;
        }

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var title = document.getElementById('title');
            if (title) {
                title.setAttribute('readonly', 'readonly');
            }
            var content = document.getElementById('content');
            if (content) {
                content.setAttribute('readonly', 'readonly');
            }
            var publish = document.getElementById('publish');
            if (publish) {
                publish.setAttribute('disabled', 'disabled');
            }
        });
        </script>
        <?php
    }

    public function disable_mirror_block_editor_ui() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        if ( ! $this->is_mirror_post( $post_id ) ) {
            return;
        }

        wp_add_inline_script(
            'wp-edit-post',
            '(function(){if(!window.wp||!wp.data){return;}var lock="ecm-mirror-lock";wp.domReady(function(){var editor=wp.data.dispatch("core/editor");if(editor&&editor.lockPostSaving){editor.lockPostSaving(lock);}if(editor&&editor.lockPostAutosaving){editor.lockPostAutosaving(lock);}});})();'
        );
    }

    private function is_mirror_post( $post_id ) {
        $post_id = (int) $post_id;
        if ( ! $post_id ) {
            return false;
        }

        return (bool) get_post_meta( $post_id, self::META_SOURCE_EVENT, true );
    }
}
