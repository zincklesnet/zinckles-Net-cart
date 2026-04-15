(function($){
    if (typeof zncFront === 'undefined') return;
    var aj = zncFront.ajaxurl, nonce = zncFront.nonce;

    function updateCount(c) {
        $('.znc-cart-count, .znc-widget-count, .cart-contents-count, .wc-cart-count').text(c);
        if (typeof zncFront !== 'undefined') zncFront.cartCount = c;
    }

    // Remove item
    $(document).on('click', '.znc-remove-btn', function(e){
        e.preventDefault();
        var btn = $(this), key = btn.data('key'), row = btn.closest('.znc-cart-item, tr');
        btn.prop('disabled', true);
        $.post(aj, {action:'znc_remove_cart_item', item_key:key, nonce:nonce}, function(r){
            if (r.success) { row.fadeOut(300, function(){ $(this).remove(); }); updateCount(r.data.cart_count); }
            else { btn.prop('disabled', false); }
        }).fail(function(){ btn.prop('disabled', false); });
    });

    // Quantity buttons
    $(document).on('click', '.znc-qty-minus, .znc-qty-plus', function(){
        var btn = $(this), key = btn.data('key'),
            input = btn.siblings('.znc-qty-input'),
            val = parseInt(input.val()) || 1,
            newVal = btn.hasClass('znc-qty-minus') ? Math.max(1, val - 1) : val + 1;
        var max = parseInt(input.attr('max'));
        if (max && newVal > max) newVal = max;
        input.val(newVal);
        $.post(aj, {action:'znc_update_cart_qty', item_key:key, quantity:newVal, nonce:nonce}, function(r){
            if (r.success) updateCount(r.data.cart_count);
        });
    });

    // Quantity input change
    $(document).on('change', '.znc-qty-input', function(){
        var input = $(this), key = input.data('key'), val = Math.max(1, parseInt(input.val()) || 1);
        input.val(val);
        $.post(aj, {action:'znc_update_cart_qty', item_key:key, quantity:val, nonce:nonce}, function(r){
            if (r.success) updateCount(r.data.cart_count);
        });
    });

    // Clear cart
    $(document).on('click', '.znc-btn-clear', function(e){
        e.preventDefault();
        if (!confirm('Clear your entire global cart?')) return;
        $.post(aj, {action:'znc_clear_global_cart', nonce:nonce}, function(r){
            if (r.success) { updateCount(0); location.reload(); }
        });
    });

    // Checkout redirect
    $(document).on('click', '.znc-btn-checkout', function(e){
        var href = $(this).attr('href');
        if (href && href !== '#') return; // let normal link work
        e.preventDefault();
        if (zncFront.checkoutUrl) window.location.href = zncFront.checkoutUrl;
    });

    // Update cart badge on page load
    $(function(){
        if (zncFront.cartCount !== undefined) updateCount(zncFront.cartCount);
    });
})(jQuery);
