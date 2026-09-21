<?php
if (!defined('ABSPATH')) { exit; }

class EPF_Post_Type {
    public static function init() { add_action('init', array(__CLASS__, 'register')); }

    public static function register() {
        $labels = array(
            'name' => __('PDF Flipbooks', 'elimo-pdf-flipbook'),
            'singular_name' => __('PDF Flipbook', 'elimo-pdf-flipbook'),
            'add_new' => __('Add New', 'elimo-pdf-flipbook'),
            'add_new_item' => __('Add New PDF Flipbook', 'elimo-pdf-flipbook'),
            'edit_item' => __('Edit PDF Flipbook', 'elimo-pdf-flipbook'),
            'new_item' => __('New PDF Flipbook', 'elimo-pdf-flipbook'),
            'view_item' => __('View Online', 'elimo-pdf-flipbook'),
            'search_items' => __('Search PDF Flipbooks', 'elimo-pdf-flipbook'),
            'not_found' => __('No PDF flipbooks found.', 'elimo-pdf-flipbook'),
            'menu_name' => __('PDF Flipbooks', 'elimo-pdf-flipbook'),
        );
        register_post_type('epf_book', array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => false,
            'rewrite' => array('slug'=>'catalog'),
            'menu_icon' => 'dashicons-book-alt',
            'supports' => array('title','excerpt','thumbnail'),
        ));
    }
}
