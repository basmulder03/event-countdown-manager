<?php

class ECM_Sync_Test extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();

        update_option(
            ECM_Settings::OPTION_KEY,
            array_merge(
                ECM_Settings::defaults(),
                array(
                    'sync_enabled' => 1,
                )
            )
        );
    }

    public function test_creates_mirror_post_on_event_save() {
        $event_id = self::factory()->post->create(
            array(
                'post_type'    => ECM_Events::POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => 'PHPUnit Event One',
                'post_content' => 'PHPUnit Event Body',
                'post_excerpt' => 'PHPUnit Event Excerpt',
            )
        );

        update_post_meta( $event_id, ECM_Events::META_START_UTC, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
        update_post_meta( $event_id, ECM_Events::META_TIMEZONE, 'UTC' );

        wp_update_post( array( 'ID' => $event_id ) );

        $mirrors = get_posts(
            array(
                'post_type'      => 'post',
                'posts_per_page' => -1,
                'meta_key'       => ECM_Sync::META_SOURCE_EVENT,
                'meta_value'     => $event_id,
                'fields'         => 'ids',
            )
        );

        $this->assertNotEmpty( $mirrors );
        $this->assertSame( 'PHPUnit Event One', get_the_title( (int) $mirrors[0] ) );
    }

    public function test_mirror_post_stays_read_only_on_update() {
        $event_id = self::factory()->post->create(
            array(
                'post_type'    => ECM_Events::POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => 'Readonly Guard Event',
                'post_content' => 'Readonly Guard Body',
            )
        );

        update_post_meta( $event_id, ECM_Events::META_START_UTC, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
        update_post_meta( $event_id, ECM_Events::META_TIMEZONE, 'UTC' );
        wp_update_post( array( 'ID' => $event_id ) );

        $mirror_ids = get_posts(
            array(
                'post_type'      => 'post',
                'posts_per_page' => 1,
                'meta_key'       => ECM_Sync::META_SOURCE_EVENT,
                'meta_value'     => $event_id,
                'fields'         => 'ids',
            )
        );

        $this->assertNotEmpty( $mirror_ids );

        $mirror_id = (int) $mirror_ids[0];
        $before    = get_post( $mirror_id );

        wp_update_post(
            array(
                'ID'         => $mirror_id,
                'post_title' => 'Manual Override Attempt',
            )
        );

        $after = get_post( $mirror_id );

        $this->assertInstanceOf( 'WP_Post', $before );
        $this->assertInstanceOf( 'WP_Post', $after );
        $this->assertSame( $before->post_title, $after->post_title );
    }
}
