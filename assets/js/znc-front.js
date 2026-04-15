/**
 * Zinckles Net Cart — Front-End JS — v1.5.0
 * Handles global cart interactions: remove item, quantity update, AJAX refresh.
 *
 * v1.5.0: Updated to use item_key (blog_product_variation composite key)
 *         instead of separate blog_id/product_id/variation_id params.
 *         AJAX actions aligned with ZNC_Global_Cart_Store v1.5.0.
 */
(function($) {
    'use strict';

    if (typeof zncFront === 'undefined') return;

    var ajaxUrl = zncFront.ajaxUrl;
    var nonce   = zncFront.nonce;

    /* ── Remove Item ─────────────────────────────── */
    $(document).on('click', '.znc-remove-btn, .znc-remove-item', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $row = $btn.closest('.znc-cart-item, tr');
        var itemKey = $btn.data('item-key') || '';

        // Fallback: build key from individual data attrs (backward compat)
        if (!itemKey && $btn.data('blog-id')) {
            itemKey = $btn.data('blog-id') + '_' + $btn.data('product-id') + '_' + ($btn.data('variation-id') || 0);
        }

        if (!itemKey) {
            alert('Error: missing item key.');
            return;
        }

        $btn.prop('disabled', true).text('…');

        $.post(ajaxUrl, {
            action: 'znc_remove_cart_item',
            item_key: itemKey,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    updateCartCount(response.data.count);
                    updateCartTotal(response.data.total);
                    // Check if shop group is now empty
                    $('.znc-shop-group').each(function() {
                        if ($(this).find('.znc-cart-item').length === 0) {
                            $(this).fadeOut(200, function() { $(this).remove(); });
                        }
                    });
                    if ($('.znc-cart-item, .znc-cart-table tbody tr').length === 0) {
                        showEmptyCart();
                    }
                });
            } else {
                alert(response.data.message || response.data || 'Error removing item.');
                $btn.prop('disabled', false).text('✕');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
            $btn.prop('disabled', false).text('✕');
        });
    });

    /* ── Quantity +/- Buttons ────────────────────── */
    $(document).on('click', '.znc-qty-minus, .znc-qty-plus', function(e) {
        e.preventDefault();
        var $btn   = $(this);
        var $item  = $btn.closest('.znc-cart-item, tr');
        var $input = $item.find('.znc-qty-input');
        var qty    = parseInt($input.val(), 10) || 1;

        if ($btn.hasClass('znc-qty-minus') || $btn.data('action') === 'decrease') {
            qty = Math.max(1, qty - 1);
        } else {
            qty = Math.min(99, qty + 1);
        }

        $input.val(qty).trigger('change');
    });

    /* ── Quantity Update ─────────────────────────── */
    var qtyTimer = null;
    $(document).on('change', '.znc-qty-input', function() {
        var $input  = $(this);
        var itemKey = $input.data('item-key') || '';
        var qty     = parseInt($input.val(), 10);

        // Fallback: build key from data-item-id (backward compat)
        if (!itemKey && $input.data('item-id')) {
            itemKey = $input.data('item-id');
        }

        if (qty < 1) { $input.val(1); qty = 1; }
        if (qty > 99) { $input.val(99); qty = 99; }

        clearTimeout(qtyTimer);
        qtyTimer = setTimeout(function() {
            $input.prop('disabled', true);
            $.post(ajaxUrl, {
                action: 'znc_update_cart_qty',
                item_key: itemKey,
                quantity: qty,
                nonce: nonce
            })
            .done(function(response) {
                if (response.success) {
                    updateCartCount(response.data.count);
                    updateCartTotal(response.data.total);
                    if (response.data.line_total) {
                        $input.closest('.znc-cart-item, tr').find('.znc-line-total').text(response.data.line_total);
                    }
                    // Update the line total display
                    var $item = $input.closest('.znc-cart-item');
                    if ($item.length) {
                        var price = parseFloat($item.data('price')) || 0;
                        if (price > 0) {
                            $item.find('.znc-line-total').text('$' + (price * qty).toFixed(2));
                        }
                    }
                }
            })
            .always(function() {
                $input.prop('disabled', false);
            });
        }, 500);
    });

    /* ── Cart Badge Sync ─────────────────────────── */
    function updateCartCount(count) {
        count = parseInt(count, 10) || 0;
        // Update all known cart count elements
        $('.znc-cart-count, .znc-cart-badge-count, .znc-global-cart-count, .znc-cart-badge').text(count);
        // WC fragments
        $('.cart-contents .count, .cart-count, .wc-cart-count').text(count);
        // REIGN theme specific
        $('.wb-cart-count, .cart-items-count, .header-cart-count').text(count);
        // Trigger custom event for other plugins to listen to
        $(document.body).trigger('znc_cart_count_updated', [count]);
    }

    function updateCartTotal(total) {
        if (total !== undefined) {
            $('.znc-cart-total-amount, .znc-grand-total-amount').text(
                typeof total === 'number' ? '$' + total.toFixed(2) : total
            );
        }
    }

    function showEmptyCart() {
        var $container = $('.znc-global-cart');
        if ($container.length) {
            $container.html(
                '<div class="znc-empty-cart">' +
                '<span class="znc-empty-icon">🛒</span>' +
                '<p>Your global cart is empty.</p>' +
                '<a href="/" class="button znc-continue-shopping">Continue Shopping</a>' +
                '</div>'
            );
        }
    }

    /* ── Initial Cart Count Load ─────────────────── */
    function loadGlobalCartCount() {
        if (!zncFront.loadCount) return;
        $.post(ajaxUrl, {
            action: 'znc_get_cart_data',
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                updateCartCount(response.data.count);
                if (response.data.total !== undefined) {
                    updateCartTotal(response.data.total);
                }
            }
        });
    }

    $(document).ready(function() {
        loadGlobalCartCount();
    });

})(jQuery);
