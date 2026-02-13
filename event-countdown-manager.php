<?php
/**
 * Plugin Name: Event Countdown Manager
 * Description: Manage multilingual events with countdown timers, mirrored posts, blocks, and simple theme customization.
 * Version: 0.1.1
 * Author: Local Dev
 * Text Domain: event-countdown-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ECM_VERSION', '0.1.1' );
define( 'ECM_PLUGIN_FILE', __FILE__ );
define( 'ECM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ECM_PLUGIN_DIR . 'includes/class-ecm-language.php';
require_once ECM_PLUGIN_DIR . 'includes/class-ecm-settings.php';
require_once ECM_PLUGIN_DIR . 'includes/class-ecm-events.php';
require_once ECM_PLUGIN_DIR . 'includes/class-ecm-sync.php';
require_once ECM_PLUGIN_DIR . 'includes/class-ecm-render.php';
require_once ECM_PLUGIN_DIR . 'includes/class-ecm-cli-command.php';
require_once ECM_PLUGIN_DIR . 'includes/class-ecm-plugin.php';

register_activation_hook( __FILE__, array( 'ECM_Plugin', 'activate' ) );

ECM_Plugin::instance();
