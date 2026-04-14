(function($){
    'use strict';

    var ZNC = {
        init: function(){
            this.bindCart();
            this.bindCheckout();
            this.bindZCredSlider();
        },

        /* ── Cart Page ────────────────────────────────────── */
        bindCart: function(){
            // Remove item
            $(document).on('click','.znc-remove-btn',function(e){
                e.preventDefault();
                var $btn = $(this), lineId = $btn.data('line');
                if(!confirm('Remove this item?')) return;
                $btn.closest('tr').fadeOut(300);
                $.post(zncFront.restUrl+'global-cart/remove',JSON.stringify({line_id:lineId}),function(){
                    location.reload();
                },'json').fail(function(){ $btn.closest('tr').fadeIn(); });
            });

            // Quantity update
            var qtyTimer;
            $(document).on('change','.znc-qty-input',function(){
                var $input = $(this), lineId = $input.data('line'), qty = parseInt($input.val(),10);
                clearTimeout(qtyTimer);
                qtyTimer = setTimeout(function(){
                    $.post(zncFront.restUrl+'global-cart/update-qty',JSON.stringify({line_id:lineId,quantity:qty}),function(){
                        location.reload();
                    },'json');
                },500);
            });

            // Tabs
            $(document).on('click','.znc-tab',function(){
                var site = $(this).data('site');
                $('.znc-tab').removeClass('active');
                $(this).addClass('active');
                $('.znc-shop-group').hide();
                $('.znc-shop-group[data-site="'+site+'"]').show();
            });
            $('.znc-tab:first').trigger('click');
        },

        /* ── Checkout Page ────────────────────────────────── */
        bindCheckout: function(){
            var currentStep = 1, maxStep = 3;

            $(document).on('click','.znc-next-step',function(){
                if(currentStep >= maxStep) return;
                // Validate current step
                if(currentStep === 2){
                    var valid = true;
                    $('.znc-checkout-section[data-step="2"] input[required]').each(function(){
                        if(!$(this).val()){ $(this).css('border-color','#ef4444'); valid = false; }
                        else { $(this).css('border-color','#d1d5db'); }
                    });
                    if(!valid) return;
                }
                currentStep++;
                ZNC.showStep(currentStep);
            });

            $(document).on('click','.znc-prev-step',function(){
                if(currentStep <= 1) return;
                currentStep--;
                ZNC.showStep(currentStep);
            });

            // Form submit
            $('#znc-checkout-form').on('submit',function(e){
                e.preventDefault();
                var $btn = $('.znc-place-order-btn');
                $btn.prop('disabled',true).text('Processing...');

                var formData = {};
                $(this).serializeArray().forEach(function(f){
                    if(f.name.indexOf('[')>-1){
                        var parts = f.name.replace(']','').split('[');
                        if(!formData[parts[0]]) formData[parts[0]] = {};
                        formData[parts[0]][parts[1]] = f.value;
                    } else {
                        formData[f.name] = f.value;
                    }
                });

                $.ajax({
                    url: zncFront.restUrl+'checkout',
                    method:'POST',
                    contentType:'application/json',
                    data: JSON.stringify(formData),
                    headers:{'X-WP-Nonce':zncFront.nonce},
                    success:function(res){
                        if(res.success){
                            window.location.href = zncFront.thankyouUrl+'?order='+res.parent_order_id;
                        } else {
                            alert(res.message||'Checkout failed.');
                            $btn.prop('disabled',false).text('Place Order');
                        }
                    },
                    error:function(xhr){
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Checkout failed.';
                        alert(msg);
                        $btn.prop('disabled',false).text('Place Order');
                    }
                });
            });
        },

        showStep: function(step){
            $('.znc-checkout-section').hide();
            $('.znc-checkout-section[data-step="'+step+'"]').show();
            $('.znc-step').removeClass('active completed');
            $('.znc-step').each(function(){
                var s = parseInt($(this).data('step'),10);
                if(s < step) $(this).addClass('completed');
                if(s === step) $(this).addClass('active');
            });
            if(step > 1) $('.znc-prev-step').show(); else $('.znc-prev-step').hide();
            if(step === 3){ $('.znc-next-step').hide(); ZNC.buildSummary(); }
            else { $('.znc-next-step').show(); }
        },

        buildSummary: function(){
            var html = '<p><strong>Billing:</strong> ';
            html += $('input[name="billing[first_name]"]').val()+' '+$('input[name="billing[last_name]"]').val();
            html += ', '+$('input[name="billing[email]"]').val();
            html += '</p><p><strong>Payment:</strong> '+$('select[name="payment_method"] option:selected').text()+'</p>';
            var zcred = $('input[name="zcred_amount"]').val();
            if(zcred && parseInt(zcred,10)>0){
                html += '<p><strong>ZCreds Applied:</strong> '+zcred+'</p>';
            }
            $('#znc-order-summary').html(html);
        },

        /* ── ZCred Slider ─────────────────────────────────── */
        bindZCredSlider: function(){
            $('#znc-zcred-slider').on('input',function(){
                var val = parseInt($(this).val(),10);
                var rate = parseFloat($(this).closest('.znc-zcred-checkout,.znc-zcred-widget').data('rate')||0.01);
                $('#znc-zcred-display').text(val);
                $('#znc-zcred-value').text('$'+(val*rate).toFixed(2));
            });
        }
    };

    $(document).ready(function(){ ZNC.init(); });
})(jQuery);
