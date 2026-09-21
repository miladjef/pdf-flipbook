<?php
if (!defined('ABSPATH')) { exit; }

class EPF_Frontend {
    private static $assets_enqueued=false;
    public static function init(){
        add_shortcode('elimo_pdf',array(__CLASS__,'shortcode'));
        add_filter('the_content',array(__CLASS__,'single_content'));
        add_action('template_redirect',array(__CLASS__,'serve_local_pdf'),0);
    }

    private static function pdf_key($attachment_id) {
        return substr(wp_hash('epf-pdf|' . (int)$attachment_id), 0, 20);
    }

    private static function local_pdf_url($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) return '';
        return add_query_arg(array(
            'epf_pdf_file' => $attachment_id,
            'epf_key' => self::pdf_key($attachment_id),
        ), home_url('/'));
    }

    public static function serve_local_pdf() {
        if (empty($_GET['epf_pdf_file'])) return;
        $attachment_id = absint(wp_unslash($_GET['epf_pdf_file']));
        $key = isset($_GET['epf_key']) ? sanitize_text_field(wp_unslash($_GET['epf_key'])) : '';
        if (!$attachment_id || !$key || !hash_equals(self::pdf_key($attachment_id), $key)) {
            status_header(403);
            exit('Forbidden');
        }
        if (get_post_mime_type($attachment_id) !== 'application/pdf') {
            status_header(404);
            exit('PDF not found');
        }
        $file = get_attached_file($attachment_id);
        $real = $file ? realpath($file) : false;
        $uploads = wp_get_upload_dir();
        $base = !empty($uploads['basedir']) ? realpath($uploads['basedir']) : false;
        if (!$real || !$base || !is_file($real) || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
            status_header(404);
            exit('PDF not found');
        }

        $size = filesize($real);
        if ($size === false || $size < 1) {
            status_header(404);
            exit('PDF not found');
        }
        $start = 0;
        $end = $size - 1;
        $status = 200;
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE'])), $m)) {
            if ($m[1] !== '') $start = max(0, (int)$m[1]);
            if ($m[2] !== '') $end = min($end, (int)$m[2]);
            if ($m[1] === '' && $m[2] !== '') {
                $suffix = max(0, (int)$m[2]);
                $start = max(0, $size - $suffix);
                $end = $size - 1;
            }
            if ($start > $end || $start >= $size) {
                status_header(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            $status = 206;
        }
        $length = $end - $start + 1;
        while (ob_get_level()) @ob_end_clean();
        status_header($status);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . rawurlencode(sanitize_file_name(wp_basename($real))) . '"');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        if ($status === 206) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);

        $fp = fopen($real, 'rb');
        if (!$fp) { status_header(500); exit('Unable to read PDF'); }
        if ($start > 0) fseek($fp, $start);
        $remaining = $length;
        $chunk = 1024 * 1024;
        while ($remaining > 0 && !feof($fp)) {
            $read = min($chunk, $remaining);
            $buffer = fread($fp, $read);
            if ($buffer === false || $buffer === '') break;
            echo $buffer;
            $remaining -= strlen($buffer);
            if (function_exists('fastcgi_finish_request')) { /* do not call: stream must continue */ }
            flush();
        }
        fclose($fp);
        exit;
    }

    private static function enqueue(){
        if(self::$assets_enqueued) return;
        self::$assets_enqueued=true;
        $o=epf_options();
        $pdfjs_url = EPF_URL . 'assets/vendor/pdf.min.js';
        $worker_url = EPF_URL . 'assets/vendor/pdf.worker.min.js';
        wp_enqueue_script('epf-pdfjs',$pdfjs_url,array(),EPF_VERSION,true);
        wp_enqueue_style('epf-viewer',EPF_URL.'assets/css/viewer.css',array(),EPF_VERSION);
        wp_enqueue_script('epf-viewer',EPF_URL.'assets/js/viewer.js',array('epf-pdfjs'),EPF_VERSION,true);
        wp_localize_script('epf-viewer','EPF_CONFIG',array('workerUrl'=>$worker_url,'strings'=>array(
            'loading'=>__('Loading PDF…','elimo-pdf-flipbook'),
            'error'=>__('The PDF could not be loaded.','elimo-pdf-flipbook'),
            'page'=>__('Page','elimo-pdf-flipbook'),
            'of'=>__('of','elimo-pdf-flipbook'),
            'thumbs'=>__('Pages','elimo-pdf-flipbook'),
        )));
    }

    public static function single_content($content){
        if(!is_singular('epf_book') || !in_the_loop() || !is_main_query()) return $content;
        return '<div class="epf-single-intro">'.($content?:'').'</div>'.self::render(array('id'=>get_the_ID(),'height'=>'820'));
    }

    public static function shortcode($atts){
        $atts=shortcode_atts(array('id'=>0,'url'=>'','height'=>'','mode'=>'','download'=>'','thumbs'=>'','share'=>'','start'=>'','display'=>'embed','label'=>__('Open PDF','elimo-pdf-flipbook')),$atts,'elimo_pdf');
        return self::render($atts);
    }

    private static function bool_attr($value,$fallback){ if($value==='') return (bool)$fallback; return !in_array(strtolower((string)$value),array('0','false','no','off'),true); }

    private static function render($atts){
        self::enqueue();
        $id=absint($atts['id']??0); $o=epf_options();
        $url=$atts['url']??''; $attachment_id=0; $title='PDF'; $mode=$atts['mode']??''; $height=$atts['height']??''; $download=$atts['download']??''; $thumbs=$atts['thumbs']??''; $share=$atts['share']??''; $start=$atts['start']??'';
        $bg=$o['default_background']; $accent=$o['default_accent'];
        if($id){
            $p=get_post($id); if(!$p || $p->post_type!=='epf_book') return '';
            $title=get_the_title($id); $url=$url?:get_post_meta($id,'_epf_pdf_url',true);
            $attachment_id=(int)get_post_meta($id,'_epf_pdf_id',true);
            $mode=$mode?:get_post_meta($id,'_epf_mode',true);
            $height=$height?:get_post_meta($id,'_epf_height',true);
            $start=$start?:get_post_meta($id,'_epf_start_page',true);
            $bg=get_post_meta($id,'_epf_background',true)?:$bg; $accent=get_post_meta($id,'_epf_accent',true)?:$accent;
            if($download==='') $download=get_post_meta($id,'_epf_download',true);
            if($thumbs==='') $thumbs=get_post_meta($id,'_epf_thumbs',true);
            if($share==='') $share=get_post_meta($id,'_epf_share',true);
        }
        $url=esc_url($url); if(!$url) return '<div class="epf-error-box">'.esc_html__('No PDF file has been selected.','elimo-pdf-flipbook').'</div>';
        if (!$attachment_id) $attachment_id = (int)attachment_url_to_postid($url);
        $viewer_url = $attachment_id ? self::local_pdf_url($attachment_id) : $url;
        $mode=in_array($mode,array('flipbook','single'),true)?$mode:'flipbook';
        $height=max(420,min(1400,(int)($height?:$o['default_height']))); $start=max(1,(int)($start?:1));
        $showDownload=self::bool_attr($download,$o['default_download']); $showThumbs=self::bool_attr($thumbs,$o['default_thumbs']); $showShare=self::bool_attr($share,$o['default_share']);
        $uid='epf-'.wp_unique_id();
        if(($atts['display']??'embed')==='lightbox'){
            return '<button type="button" class="epf-lightbox-trigger" data-epf-open="'.esc_attr($uid).'">'.esc_html($atts['label']??'Open PDF').'</button><div class="epf-lightbox" id="'.esc_attr($uid).'-lightbox" hidden><button class="epf-lightbox-close" type="button" aria-label="Close">×</button>'.self::viewer_markup($uid,$viewer_url,$url,$title,$mode,$height,$start,$bg,$accent,$showDownload,$showThumbs,$showShare,true).'</div>';
        }
        return self::viewer_markup($uid,$viewer_url,$url,$title,$mode,$height,$start,$bg,$accent,$showDownload,$showThumbs,$showShare,false);
    }

    private static function icon($name){
        $icons=array(
            'menu'=>'<svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
            'prev'=>'<svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>',
            'next'=>'<svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>',
            'minus'=>'<svg viewBox="0 0 24 24"><path d="M5 12h14"/></svg>',
            'plus'=>'<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
            'fullscreen'=>'<svg viewBox="0 0 24 24"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg>',
            'download'=>'<svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>',
            'share'=>'<svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="2"/><circle cx="6" cy="12" r="2"/><circle cx="18" cy="19" r="2"/><path d="M8 11l8-5M8 13l8 5"/></svg>'
        ); return $icons[$name]??'';
    }

    private static function viewer_markup($uid,$viewer_url,$original_url,$title,$mode,$height,$start,$bg,$accent,$download,$thumbs,$share,$lightbox){ ob_start(); ?>
        <div id="<?php echo esc_attr($uid); ?>" class="epf-viewer <?php echo $lightbox?'epf-is-lightbox':''; ?>" style="--epf-height:<?php echo (int)$height; ?>px;--epf-bg:<?php echo esc_attr($bg); ?>;--epf-accent:<?php echo esc_attr($accent); ?>" data-pdf="<?php echo esc_attr($viewer_url); ?>" data-fallback-pdf="<?php echo esc_attr($original_url); ?>" data-mode="<?php echo esc_attr($mode); ?>" data-start="<?php echo (int)$start; ?>">
            <div class="epf-loading"><span class="epf-loader"></span><strong><?php esc_html_e('Loading PDF…','elimo-pdf-flipbook'); ?></strong><small class="epf-loading-progress">0%</small></div>
            <div class="epf-toolbar">
                <div class="epf-toolbar-left">
                    <?php if($thumbs): ?><button type="button" class="epf-tool epf-thumbs-toggle" title="<?php esc_attr_e('Thumbnails','elimo-pdf-flipbook'); ?>"><?php echo self::icon('menu'); ?></button><?php endif; ?>
                    <strong class="epf-title"><?php echo esc_html($title); ?></strong>
                </div>
                <div class="epf-page-control"><button type="button" class="epf-tool epf-prev" title="Previous"><?php echo self::icon('prev'); ?></button><label><input type="number" class="epf-page-input" min="1" value="1"><span>/ <b class="epf-total">—</b></span></label><button type="button" class="epf-tool epf-next" title="Next"><?php echo self::icon('next'); ?></button></div>
                <div class="epf-toolbar-right"><button type="button" class="epf-tool epf-zoom-out" title="Zoom out"><?php echo self::icon('minus'); ?></button><button type="button" class="epf-tool epf-zoom-in" title="Zoom in"><?php echo self::icon('plus'); ?></button><?php if($share): ?><button type="button" class="epf-tool epf-share" title="Share"><?php echo self::icon('share'); ?></button><?php endif; ?><?php if($download): ?><a class="epf-tool epf-download" title="Download" href="<?php echo esc_url($original_url); ?>" download><?php echo self::icon('download'); ?></a><?php endif; ?><button type="button" class="epf-tool epf-fullscreen" title="Fullscreen"><?php echo self::icon('fullscreen'); ?></button></div>
            </div>
            <div class="epf-body">
                <?php if($thumbs): ?><aside class="epf-thumbs" hidden><div class="epf-thumbs-head"><strong><?php esc_html_e('Pages','elimo-pdf-flipbook'); ?></strong><button type="button" class="epf-thumbs-close">×</button></div><div class="epf-thumbs-list"></div></aside><?php endif; ?>
                <main class="epf-stage" tabindex="0">
                    <button type="button" class="epf-side-nav epf-side-prev" aria-label="Previous page"><?php echo self::icon('prev'); ?></button>
                    <div class="epf-book"><div class="epf-page epf-left-page"><canvas></canvas><span class="epf-page-number"></span></div><div class="epf-page epf-right-page"><canvas></canvas><span class="epf-page-number"></span></div></div>
                    <button type="button" class="epf-side-nav epf-side-next" aria-label="Next page"><?php echo self::icon('next'); ?></button>
                    <div class="epf-error" hidden><strong><?php esc_html_e('The PDF could not be loaded.','elimo-pdf-flipbook'); ?></strong><small class="epf-error-detail" hidden></small><a href="<?php echo esc_url($original_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF directly','elimo-pdf-flipbook'); ?></a></div>
                </main>
            </div>
            <div class="epf-mobile-bar"><button type="button" class="epf-tool epf-prev-mobile"><?php echo self::icon('prev'); ?></button><span><b class="epf-mobile-current">1</b> / <b class="epf-mobile-total">—</b></span><button type="button" class="epf-tool epf-next-mobile"><?php echo self::icon('next'); ?></button></div>
            <div class="epf-toast" aria-live="polite"></div>
        </div>
    <?php return ob_get_clean(); }
}
