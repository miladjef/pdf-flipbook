(function($){
'use strict';
$(function(){
  $('.epf-color').wpColorPicker();
  $(document).on('click','.epf-select-pdf',function(e){
    e.preventDefault();
    const input=$(this).siblings('#epf_pdf_url');
    const idInput=$(this).siblings('#epf_pdf_id');
    const frame=wp.media({title:'Select PDF',button:{text:'Use this PDF'},library:{type:'application/pdf'},multiple:false});
    frame.on('select',function(){const a=frame.state().get('selection').first().toJSON();input.val(a.url).trigger('change');idInput.val(a.id||'');});
    frame.open();
  });
  $(document).on('input','#epf_pdf_url',function(){ $('#epf_pdf_id').val(''); });
  $(document).on('click','.epf-copy-code',function(){this.select(); if(navigator.clipboard) navigator.clipboard.writeText(this.value);});
});
})(jQuery);
