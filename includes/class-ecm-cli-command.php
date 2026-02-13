<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
    return;
}

class ECM_CLI_Command extends WP_CLI_Command {
    public function seed( $args, $assoc_args ) {
        $count = isset( $assoc_args['count'] ) ? max( 1, (int) $assoc_args['count'] ) : 3;

        $created = array();
        $base    = time() + DAY_IN_SECONDS;

        for ( $i = 1; $i <= $count; $i++ ) {
            $event_id = wp_insert_post(
                array(
                    'post_type'    => ECM_Events::POST_TYPE,
                    'post_status'  => 'publish',
                    'post_title'   => sprintf( 'Demo Event %d', $i ),
                    'post_content' => sprintf( 'Demo description for event %d.', $i ),
                    'post_excerpt' => sprintf( 'Event %d starts soon.', $i ),
                )
            );

            if ( is_wp_error( $event_id ) || ! $event_id ) {
                continue;
            }

            update_post_meta( $event_id, ECM_Events::META_START_UTC, gmdate( 'Y-m-d H:i:s', $base + ( $i * 2 * HOUR_IN_SECONDS ) ) );
            update_post_meta( $event_id, ECM_Events::META_TIMEZONE, 'Europe/Amsterdam' );
            update_post_meta( $event_id, ECM_Events::META_CTA_TEXT, 'Learn more' );
            update_post_meta( $event_id, ECM_Events::META_CTA_URL, 'https://example.com/events/' . $event_id );

            wp_update_post( array( 'ID' => $event_id ) );
            $created[] = (int) $event_id;
        }

        WP_CLI::success( sprintf( 'Seeded %d demo events: %s', count( $created ), implode( ', ', $created ) ) );
    }

    public function cleanup_demo( $args, $assoc_args ) {
        $events = get_posts(
            array(
                'post_type'      => ECM_Events::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'posts_per_page' => -1,
                's'              => 'Demo Event',
                'fields'         => 'ids',
            )
        );

        foreach ( $events as $event_id ) {
            wp_delete_post( (int) $event_id, true );
        }

        $mirrors = get_posts(
            array(
                'post_type'      => 'post',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
                'posts_per_page' => -1,
                'meta_key'       => ECM_Sync::META_IS_MIRROR,
                'meta_value'     => 1,
                'fields'         => 'ids',
            )
        );

        foreach ( $mirrors as $mirror_id ) {
            wp_delete_post( (int) $mirror_id, true );
        }

        WP_CLI::success( sprintf( 'Deleted %d demo events and %d mirrors.', count( $events ), count( $mirrors ) ) );
    }

    public function test_sync( $args, $assoc_args ) {
        $event_id = wp_insert_post(
            array(
                'post_type'    => ECM_Events::POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => 'Sync Test Event',
                'post_content' => 'Sync Test Event Body',
                'post_excerpt' => 'Sync Test Event Excerpt',
            )
        );

        if ( ! $event_id || is_wp_error( $event_id ) ) {
            WP_CLI::error( 'Failed to create test event.' );
        }

        update_post_meta( $event_id, ECM_Events::META_START_UTC, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
        update_post_meta( $event_id, ECM_Events::META_TIMEZONE, 'UTC' );
        wp_update_post( array( 'ID' => $event_id ) );

        $mirror = get_posts(
            array(
                'post_type'      => 'post',
                'post_status'    => array( 'publish', 'draft' ),
                'posts_per_page' => 1,
                'meta_key'       => ECM_Sync::META_SOURCE_EVENT,
                'meta_value'     => $event_id,
            )
        );

        if ( empty( $mirror ) ) {
            wp_delete_post( $event_id, true );
            WP_CLI::error( 'Mirror post was not created.' );
        }

        $mirror_id = (int) $mirror[0]->ID;

        $before = get_post( $mirror_id );
        wp_update_post( array( 'ID' => $mirror_id, 'post_title' => 'Manual Override Attempt' ) );
        $after = get_post( $mirror_id );

        wp_delete_post( $mirror_id, true );
        wp_delete_post( $event_id, true );

        if ( ! $before || ! $after || $before->post_title !== $after->post_title ) {
            WP_CLI::error( 'Read-only mirror guard failed.' );
        }

        WP_CLI::success( 'Sync + read-only mirror checks passed.' );
    }

}
