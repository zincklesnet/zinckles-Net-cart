(function($){
    'use strict';

    $(document).ready(function(){

        // Color picker
        if($.fn.wpColorPicker){ $('.znc-color-picker').wpColorPicker(); }

        // Media uploader for icon
        $('#znc-upload-icon').on('click',function(e){
            e.preventDefault();
            var frame = wp.media({ title:'Select Shop Icon', multiple:false, library:{type:'image'} });
            frame.on('select',function(){
                var att = frame.state().get('selection').first().toJSON();
                $('#znc-icon-url').val(att.url);
                $('#znc-icon-preview').attr('src',att.url).show();
                $('#znc-remove-icon').show();
            });
            frame.open();
        });
        $('#znc-remove-icon').on('click',function(){
            $('#znc-icon-url').val('');
            $('#znc-icon-preview').hide();
            $(this).hide();
        });

        // Enrollment request
        $('#znc-request-enrollment').on('click',function(){
            var $btn = $(this);
            $btn.prop('disabled',true).text('Requesting...');
            $.post(zncAdmin.ajaxUrl,{action:'znc_enroll_request',nonce:zncAdmin.nonce},function(res){
                if(res.success){ $btn.text('Request Sent!').addClass('button-disabled'); }
                else { alert(res.data.message||'Failed'); $btn.prop('disabled',false).text('Request Enrollment'); }
            });
        });

        // Snapshot preview
        $('#znc-preview-snapshot').on('click',function(){
            var $btn = $(this), $out = $('#znc-snapshot-output');
            $btn.prop('disabled',true).text('Generating...');
            $.post(zncAdmin.ajaxUrl,{action:'znc_snapshot_preview',nonce:zncAdmin.nonce},function(res){
                $out.text(JSON.stringify(res.data,null,2)).slideDown();
                $btn.prop('disabled',false).text('Generate Snapshot Preview');
            });
        });

        // Clear user cart
        $(document).on('click','.znc-clear-cart',function(){
            var userId = $(this).data('user');
            $.post(zncAdmin.ajaxUrl,{action:'znc_clear_user_cart',nonce:zncAdmin.nonce,user_id:userId},function(res){
                if(res.success) location.reload();
            });
        });

        // Flush cache
        $('#znc-flush-cache').on('click',function(){
            var $btn = $(this);
            $btn.prop('disabled',true);
            $.post(zncAdmin.ajaxUrl,{action:'znc_flush_cache',nonce:zncAdmin.nonce},function(res){
                $('#znc-flush-result').text(res.data.message||'Done').fadeIn();
                $btn.prop('disabled',false);
            });
        });

        // Toggle API fields
        $('#znc-rate-source').on('change',function(){
            $('.znc-api-row').toggle($(this).val()==='api');
        }).trigger('change');
    });
})(jQuery);
