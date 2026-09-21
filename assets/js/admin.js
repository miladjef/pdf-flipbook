(function($){
'use strict';
$(function(){
  $('.epf-color').wpColorPicker();
  $(document).on('click','.epf-select-pdf',function(e){
    e.preventDefault();
    const input=$(this).siblings('input');
    const frame=wp.media({title:'Select PDF',button:{text:'Use this PDF'},library:{type:'application/pdf'},multiple:false});
    frame.on('select',function(){const a=frame.state().get('selection').first().toJSON();input.val(a.url).trigger('change');});
    frame.open();
  });
  $(document).on('click','.epf-copy-code',function(){this.select(); if(navigator.clipboard) navigator.clipboard.writeText(this.value);});
});
})(jQuery);
