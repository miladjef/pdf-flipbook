<?php
if (!defined('ABSPATH')) { exit; }

class EPF_Admin {
    public static function init() {
        if (!is_admin()) return;
        add_action('add_meta_boxes', array(__CLASS__, 'metaboxes'));
        add_action('save_post_epf_book', array(__CLASS__, 'save_book'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_filter('manage_epf_book_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_epf_book_posts_custom_column', array(__CLASS__, 'column_content'), 10, 2);
        add_action('admin_menu', array(__CLASS__, 'settings_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function assets($hook) {
        $screen = get_current_screen();
        if (!$screen || ($screen->post_type !== 'epf_book' && $screen->id !== 'epf_book_page_epf-settings')) return;
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('epf-admin', EPF_URL.'assets/css/admin.css', array(), EPF_VERSION);
        wp_enqueue_script('epf-admin', EPF_URL.'assets/js/admin.js', array('jquery','wp-color-picker'), EPF_VERSION, true);
    }

    public static function metaboxes() {
        add_meta_box('epf_pdf_source', __('PDF Source', 'elimo-pdf-flipbook'), array(__CLASS__, 'source_box'), 'epf_book', 'normal', 'high');
        add_meta_box('epf_display', __('Viewer Settings', 'elimo-pdf-flipbook'), array(__CLASS__, 'display_box'), 'epf_book', 'normal', 'default');
        add_meta_box('epf_shortcode', __('Embed', 'elimo-pdf-flipbook'), array(__CLASS__, 'shortcode_box'), 'epf_book', 'side', 'high');
    }

    public static function source_box($post) {
        wp_nonce_field('epf_save_book', 'epf_nonce');
        $url = get_post_meta($post->ID, '_epf_pdf_url', true);
        ?>
        <div class="epf-admin-source">
            <label for="epf_pdf_url"><strong><?php esc_html_e('PDF file', 'elimo-pdf-flipbook'); ?></strong></label>
            <div class="epf-media-row">
                <input id="epf_pdf_url" name="epf_pdf_url" type="url" class="widefat" value="<?php echo esc_attr($url); ?>" placeholder="https://example.com/catalog.pdf">
                <button type="button" class="button button-primary epf-select-pdf"><?php esc_html_e('Select PDF', 'elimo-pdf-flipbook'); ?></button>
            </div>
            <p class="description"><?php esc_html_e('For best performance, upload the PDF to this WordPress Media Library. Remote PDFs need CORS access.', 'elimo-pdf-flipbook'); ?></p>
        </div>
        <?php
    }

    public static function display_box($post) {
        $o = epf_options();
        $mode = get_post_meta($post->ID, '_epf_mode', true) ?: 'flipbook';
        $height = (int)(get_post_meta($post->ID, '_epf_height', true) ?: $o['default_height']);
        $bg = get_post_meta($post->ID, '_epf_background', true) ?: $o['default_background'];
        $accent = get_post_meta($post->ID, '_epf_accent', true) ?: $o['default_accent'];
        $download = get_post_meta($post->ID, '_epf_download', true); if ($download==='') $download = $o['default_download'];
        $thumbs = get_post_meta($post->ID, '_epf_thumbs', true); if ($thumbs==='') $thumbs = $o['default_thumbs'];
        $share = get_post_meta($post->ID, '_epf_share', true); if ($share==='') $share = $o['default_share'];
        $start = max(1, (int)(get_post_meta($post->ID, '_epf_start_page', true) ?: 1));
        ?>
        <table class="form-table epf-settings-table"><tbody>
            <tr><th><label for="epf_mode"><?php esc_html_e('Viewer mode', 'elimo-pdf-flipbook'); ?></label></th><td><select id="epf_mode" name="epf_mode"><option value="flipbook" <?php selected($mode,'flipbook'); ?>><?php esc_html_e('Flipbook', 'elimo-pdf-flipbook'); ?></option><option value="single" <?php selected($mode,'single'); ?>><?php esc_html_e('Single page', 'elimo-pdf-flipbook'); ?></option></select></td></tr>
            <tr><th><label for="epf_height"><?php esc_html_e('Desktop height', 'elimo-pdf-flipbook'); ?></label></th><td><input id="epf_height" name="epf_height" type="number" min="420" max="1400" step="10" value="<?php echo esc_attr($height); ?>"> px</td></tr>
            <tr><th><label for="epf_start_page"><?php esc_html_e('Start page', 'elimo-pdf-flipbook'); ?></label></th><td><input id="epf_start_page" name="epf_start_page" type="number" min="1" value="<?php echo esc_attr($start); ?>"></td></tr>
            <tr><th><?php esc_html_e('Background', 'elimo-pdf-flipbook'); ?></th><td><input name="epf_background" class="epf-color" value="<?php echo esc_attr($bg); ?>"></td></tr>
            <tr><th><?php esc_html_e('Accent', 'elimo-pdf-flipbook'); ?></th><td><input name="epf_accent" class="epf-color" value="<?php echo esc_attr($accent); ?>"></td></tr>
            <tr><th><?php esc_html_e('Toolbar', 'elimo-pdf-flipbook'); ?></th><td>
                <label><input type="checkbox" name="epf_thumbs" value="1" <?php checked($thumbs,1); ?>> <?php esc_html_e('Thumbnails', 'elimo-pdf-flipbook'); ?></label><br>
                <label><input type="checkbox" name="epf_download" value="1" <?php checked($download,1); ?>> <?php esc_html_e('Download PDF', 'elimo-pdf-flipbook'); ?></label><br>
                <label><input type="checkbox" name="epf_share" value="1" <?php checked($share,1); ?>> <?php esc_html_e('Share', 'elimo-pdf-flipbook'); ?></label>
            </td></tr>
        </tbody></table>
        <?php
    }

    public static function shortcode_box($post) {
        $code='[elimo_pdf id="'.(int)$post->ID.'"]';
        echo '<p>'.esc_html__('Paste this shortcode into Elementor, a page, or a post.', 'elimo-pdf-flipbook').'</p>';
        echo '<input type="text" class="widefat epf-copy-code" readonly value="'.esc_attr($code).'">';
        if ($post->post_status==='publish') echo '<p><a class="button" target="_blank" href="'.esc_url(get_permalink($post)).'">'.esc_html__('Open online viewer', 'elimo-pdf-flipbook').'</a></p>';
    }

    public static function save_book($post_id) {
        if (!isset($_POST['epf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['epf_nonce'])), 'epf_save_book')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $url = isset($_POST['epf_pdf_url']) ? esc_url_raw(wp_unslash($_POST['epf_pdf_url'])) : '';
        update_post_meta($post_id, '_epf_pdf_url', $url);
        update_post_meta($post_id, '_epf_mode', in_array($_POST['epf_mode'] ?? '',array('flipbook','single'),true)?sanitize_key($_POST['epf_mode']):'flipbook');
        update_post_meta($post_id, '_epf_height', max(420,min(1400,(int)($_POST['epf_height'] ?? 720))));
        update_post_meta($post_id, '_epf_start_page', max(1,(int)($_POST['epf_start_page'] ?? 1)));
        update_post_meta($post_id, '_epf_background', sanitize_hex_color($_POST['epf_background'] ?? '') ?: '#101113');
        update_post_meta($post_id, '_epf_accent', sanitize_hex_color($_POST['epf_accent'] ?? '') ?: '#c5a467');
        update_post_meta($post_id, '_epf_thumbs', !empty($_POST['epf_thumbs'])?1:0);
        update_post_meta($post_id, '_epf_download', !empty($_POST['epf_download'])?1:0);
        update_post_meta($post_id, '_epf_share', !empty($_POST['epf_share'])?1:0);
    }

    public static function columns($cols) {
        $out=array();
        foreach($cols as $k=>$v){ $out[$k]=$v; if($k==='title'){ $out['epf_file']=__('PDF','elimo-pdf-flipbook'); $out['epf_shortcode']=__('Shortcode','elimo-pdf-flipbook'); } }
        return $out;
    }
    public static function column_content($col,$post_id){
        if($col==='epf_file'){ $url=get_post_meta($post_id,'_epf_pdf_url',true); echo $url?'<a href="'.esc_url($url).'" target="_blank">'.esc_html(wp_basename(parse_url($url,PHP_URL_PATH))).'</a>':'—'; }
        if($col==='epf_shortcode'){ echo '<code>[elimo_pdf id="'.(int)$post_id.'"]</code>'; }
    }

    public static function settings_menu(){ add_submenu_page('edit.php?post_type=epf_book', __('Settings','elimo-pdf-flipbook'), __('Settings','elimo-pdf-flipbook'), 'manage_options', 'epf-settings', array(__CLASS__,'settings_page')); }
    public static function register_settings(){ register_setting('epf_settings','epf_options',array('sanitize_callback'=>array(__CLASS__,'sanitize_options'))); }
    public static function sanitize_options($in){
        $d=epf_defaults(); $out=array();
        $out['pdfjs_url']=esc_url_raw($in['pdfjs_url']??$d['pdfjs_url']);
        $out['worker_url']=esc_url_raw($in['worker_url']??$d['worker_url']);
        $out['default_height']=max(420,min(1400,(int)($in['default_height']??720)));
        $out['default_background']=sanitize_hex_color($in['default_background']??'')?:$d['default_background'];
        $out['default_accent']=sanitize_hex_color($in['default_accent']??'')?:$d['default_accent'];
        $out['default_download']=!empty($in['default_download'])?1:0;
        $out['default_thumbs']=!empty($in['default_thumbs'])?1:0;
        $out['default_share']=!empty($in['default_share'])?1:0;
        return $out;
    }
    public static function settings_page(){ $o=epf_options(); ?>
        <div class="wrap epf-settings-page"><h1><?php esc_html_e('ELIMO PDF Flipbook Settings','elimo-pdf-flipbook'); ?></h1><form method="post" action="options.php"><?php settings_fields('epf_settings'); ?>
        <table class="form-table"><tbody>
        <tr><th>PDF.js URL</th><td><input class="large-text" type="url" name="epf_options[pdfjs_url]" value="<?php echo esc_attr($o['pdfjs_url']); ?>"><p class="description">PDF rendering library URL.</p></td></tr>
        <tr><th>PDF.js Worker URL</th><td><input class="large-text" type="url" name="epf_options[worker_url]" value="<?php echo esc_attr($o['worker_url']); ?>"></td></tr>
        <tr><th><?php esc_html_e('Default height','elimo-pdf-flipbook'); ?></th><td><input type="number" name="epf_options[default_height]" value="<?php echo esc_attr($o['default_height']); ?>"> px</td></tr>
        <tr><th><?php esc_html_e('Default colors','elimo-pdf-flipbook'); ?></th><td><input class="epf-color" name="epf_options[default_background]" value="<?php echo esc_attr($o['default_background']); ?>"> <input class="epf-color" name="epf_options[default_accent]" value="<?php echo esc_attr($o['default_accent']); ?>"></td></tr>
        <tr><th><?php esc_html_e('Default toolbar','elimo-pdf-flipbook'); ?></th><td><label><input type="checkbox" name="epf_options[default_thumbs]" value="1" <?php checked($o['default_thumbs']); ?>> Thumbnails</label><br><label><input type="checkbox" name="epf_options[default_download]" value="1" <?php checked($o['default_download']); ?>> Download</label><br><label><input type="checkbox" name="epf_options[default_share]" value="1" <?php checked($o['default_share']); ?>> Share</label></td></tr>
        </tbody></table><?php submit_button(); ?></form></div>
    <?php }
}
