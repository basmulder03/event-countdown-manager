<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ECM_Language {
    public function current_language() {
        return determine_locale();
    }

    public function default_language() {
        return get_locale();
    }

    public function event_translations( $event_id ) {
        return array( $this->default_language() => (int) $event_id );
    }

    public function post_language( $post_id ) {
        return $this->default_language();
    }

    public function assign_mirror_language( $post_id, $language, $trid = null, $source_language = null ) {
        return;
    }

    public function get_mirror_trid( $post_id ) {
        return null;
    }
}
