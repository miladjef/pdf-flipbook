<?php
/**
 * Plugin Name: ELIMO PDF Flipbook
 * Plugin URI: https://elimo.ch/
 * Description: Responsive online PDF flipbook and viewer for WordPress with shortcodes, thumbnails, zoom, fullscreen, swipe navigation and shareable standalone catalog pages.
 * Version: 1.0.2
 * Author: Milad Jafari Gavzan
 * Text Domain: elimo-pdf-flipbook
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('EPF_VERSION', '1.0.2');
define('EPF_FILE', __FILE__);
define('EPF_DIR', plugin_dir_path(__FILE__));
define('EPF_URL', plugin_dir_url(__FILE__));

require_once EPF_DIR . 'includes/class-epf-post-type.php';
require_once EPF_DIR . 'includes/class-epf-admin.php';
require_once EPF_DIR . 'includes/class-epf-frontend.php';

function epf_defaults() {
    return array(
        'pdfjs_url' => EPF_URL . 'assets/vendor/pdf.min.js',
        'worker_url' => EPF_URL . 'assets/vendor/pdf.worker.min.js',
        'default_height' => 720,
        'default_background' => '#101113',
        'default_accent' => '#c5a467',
        'default_download' => 1,
        'default_thumbs' => 1,
        'default_share' => 1,
    );
}

function epf_options() {
    return wp_parse_args(get_option('epf_options', array()), epf_defaults());
}

register_activation_hook(__FILE__, function(){
    if (!get_option('epf_options')) add_option('epf_options', epf_defaults(), '', false);
    EPF_Post_Type::register();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });

add_action('plugins_loaded', function(){
    $saved = get_option('epf_options', array());
    $needs_update = get_option('epf_plugin_version') !== EPF_VERSION;
    if ($needs_update) {
        if (!is_array($saved)) $saved = array();
        $saved['pdfjs_url'] = EPF_URL . 'assets/vendor/pdf.min.js';
        $saved['worker_url'] = EPF_URL . 'assets/vendor/pdf.worker.min.js';
        update_option('epf_options', wp_parse_args($saved, epf_defaults()), false);
        update_option('epf_plugin_version', EPF_VERSION, false);
    }
    EPF_Post_Type::init();
    EPF_Admin::init();
    EPF_Frontend::init();
});
