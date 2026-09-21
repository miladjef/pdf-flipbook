<?php
if (!defined('ABSPATH')) { exit; }

class EPF_Frontend {
    private static $assets_enqueued=false;
    public static function init(){
        add_shortcode('elimo_pdf',array(__CLASS__,'shortcode'));
        add_filter('the_content',array(__CLASS__,'single_content'));
    }

    private static function enqueue(){
        if(self::$assets_enqueued) return;
        self::$assets_enqueued=true;
        $o=epf_options();
        wp_enqueue_script('epf-pdfjs',$o['pdfjs_url'],array(),null,true);
        wp_enqueue_style('epf-viewer',EPF_URL.'assets/css/viewer.css',array(),EPF_VERSION);
        wp_enqueue_script('epf-viewer',EPF_URL.'assets/js/viewer.js',array('epf-pdfjs'),EPF_VERSION,true);
        wp_localize_script('epf-viewer','EPF_CONFIG',array('workerUrl'=>$o['worker_url'],'strings'=>array(
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
        $url=$atts['url']??''; $title='PDF'; $mode=$atts['mode']??''; $height=$atts['height']??''; $download=$atts['download']??''; $thumbs=$atts['thumbs']??''; $share=$atts['share']??''; $start=$atts['start']??'';
        $bg=$o['default_background']; $accent=$o['default_accent'];
        if($id){
            $p=get_post($id); if(!$p || $p->post_type!=='epf_book') return '';
            $title=get_the_title($id); $url=$url?:get_post_meta($id,'_epf_pdf_url',true);
            $mode=$mode?:get_post_meta($id,'_epf_mode',true);
            $height=$height?:get_post_meta($id,'_epf_height',true);
            $start=$start?:get_post_meta($id,'_epf_start_page',true);
            $bg=get_post_meta($id,'_epf_background',true)?:$bg; $accent=get_post_meta($id,'_epf_accent',true)?:$accent;
            if($download==='') $download=get_post_meta($id,'_epf_download',true);
            if($thumbs==='') $thumbs=get_post_meta($id,'_epf_thumbs',true);
            if($share==='') $share=get_post_meta($id,'_epf_share',true);
        }
        $url=esc_url($url); if(!$url) return '<div class="epf-error-box">'.esc_html__('No PDF file has been selected.','elimo-pdf-flipbook').'</div>';
        $mode=in_array($mode,array('flipbook','single'),true)?$mode:'flipbook';
        $height=max(420,min(1400,(int)($height?:$o['default_height']))); $start=max(1,(int)($start?:1));
        $showDownload=self::bool_attr($download,$o['default_download']); $showThumbs=self::bool_attr($thumbs,$o['default_thumbs']); $showShare=self::bool_attr($share,$o['default_share']);
        $uid='epf-'.wp_unique_id();
        if(($atts['display']??'embed')==='lightbox'){
            return '<button type="button" class="epf-lightbox-trigger" data-epf-open="'.esc_attr($uid).'">'.esc_html($atts['label']??'Open PDF').'</button><div class="epf-lightbox" id="'.esc_attr($uid).'-lightbox" hidden><button class="epf-lightbox-close" type="button" aria-label="Close">×</button>'.self::viewer_markup($uid,$url,$title,$mode,$height,$start,$bg,$accent,$showDownload,$showThumbs,$showShare,true).'</div>';
        }
        return self::viewer_markup($uid,$url,$title,$mode,$height,$start,$bg,$accent,$showDownload,$showThumbs,$showShare,false);
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

    private static function viewer_markup($uid,$url,$title,$mode,$height,$start,$bg,$accent,$download,$thumbs,$share,$lightbox){ ob_start(); ?>
        <div id="<?php echo esc_attr($uid); ?>" class="epf-viewer <?php echo $lightbox?'epf-is-lightbox':''; ?>" style="--epf-height:<?php echo (int)$height; ?>px;--epf-bg:<?php echo esc_attr($bg); ?>;--epf-accent:<?php echo esc_attr($accent); ?>" data-pdf="<?php echo esc_attr($url); ?>" data-mode="<?php echo esc_attr($mode); ?>" data-start="<?php echo (int)$start; ?>">
            <div class="epf-loading"><span class="epf-loader"></span><strong><?php esc_html_e('Loading PDF…','elimo-pdf-flipbook'); ?></strong><small class="epf-loading-progress">0%</small></div>
            <div class="epf-toolbar">
                <div class="epf-toolbar-left">
                    <?php if($thumbs): ?><button type="button" class="epf-tool epf-thumbs-toggle" title="<?php esc_attr_e('Thumbnails','elimo-pdf-flipbook'); ?>"><?php echo self::icon('menu'); ?></button><?php endif; ?>
                    <strong class="epf-title"><?php echo esc_html($title); ?></strong>
                </div>
                <div class="epf-page-control"><button type="button" class="epf-tool epf-prev" title="Previous"><?php echo self::icon('prev'); ?></button><label><input type="number" class="epf-page-input" min="1" value="1"><span>/ <b class="epf-total">—</b></span></label><button type="button" class="epf-tool epf-next" title="Next"><?php echo self::icon('next'); ?></button></div>
                <div class="epf-toolbar-right"><button type="button" class="epf-tool epf-zoom-out" title="Zoom out"><?php echo self::icon('minus'); ?></button><button type="button" class="epf-tool epf-zoom-in" title="Zoom in"><?php echo self::icon('plus'); ?></button><?php if($share): ?><button type="button" class="epf-tool epf-share" title="Share"><?php echo self::icon('share'); ?></button><?php endif; ?><?php if($download): ?><a class="epf-tool epf-download" title="Download" href="<?php echo esc_url($url); ?>" download><?php echo self::icon('download'); ?></a><?php endif; ?><button type="button" class="epf-tool epf-fullscreen" title="Fullscreen"><?php echo self::icon('fullscreen'); ?></button></div>
            </div>
            <div class="epf-body">
                <?php if($thumbs): ?><aside class="epf-thumbs" hidden><div class="epf-thumbs-head"><strong><?php esc_html_e('Pages','elimo-pdf-flipbook'); ?></strong><button type="button" class="epf-thumbs-close">×</button></div><div class="epf-thumbs-list"></div></aside><?php endif; ?>
                <main class="epf-stage" tabindex="0">
                    <button type="button" class="epf-side-nav epf-side-prev" aria-label="Previous page"><?php echo self::icon('prev'); ?></button>
                    <div class="epf-book"><div class="epf-page epf-left-page"><canvas></canvas><span class="epf-page-number"></span></div><div class="epf-page epf-right-page"><canvas></canvas><span class="epf-page-number"></span></div></div>
                    <button type="button" class="epf-side-nav epf-side-next" aria-label="Next page"><?php echo self::icon('next'); ?></button>
                    <div class="epf-error" hidden><strong><?php esc_html_e('The PDF could not be loaded.','elimo-pdf-flipbook'); ?></strong><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF directly','elimo-pdf-flipbook'); ?></a></div>
                </main>
            </div>
            <div class="epf-mobile-bar"><button type="button" class="epf-tool epf-prev-mobile"><?php echo self::icon('prev'); ?></button><span><b class="epf-mobile-current">1</b> / <b class="epf-mobile-total">—</b></span><button type="button" class="epf-tool epf-next-mobile"><?php echo self::icon('next'); ?></button></div>
            <div class="epf-toast" aria-live="polite"></div>
        </div>
    <?php return ob_get_clean(); }
}
