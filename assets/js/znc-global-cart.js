/**
 * Zinckles Net Cart — Global Cart & Checkout JS (main site).
 */
(function ($) {
    'use strict';

    const API = zncCart.restBase;
    const NONCE = zncCart.nonce;

    // ── Helpers ──────────────────────────────────────────────────────────────

    function apiCall(endpoint, method, data) {
        const opts = {
            url: API + endpoint,
            method: method || 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
            contentType: 'application/json',
            dataType: 'json',
        };

        if (data && method !== 'GET') {
            opts.data = JSON.stringify(data);
        }

        return $.ajax(opts);
    }

    function showNotice(msg, type) {
        const cls = type === 'error' ? 'znc-notice-error' : 'znc-notice-success';
        const $notice = $('<div class="znc-notice ' + cls + '">' + msg + '</div>');
        $('#znc-global-cart, #znc-checkout').prepend($notice);
        setTimeout(() => $notice.fadeOut(400, () => $notice.remove()), 5000);
    }

    // ── Cart Page ────────────────────────────────────────────────────────────

    // Remove item.
    $(document).on('click', '.znc-remove-btn', function (e) {
        e.preventDefault();
        const lineId = $(this).data('line-id');
        const $row = $(this).closest('tr');

        apiCall('global-cart/remove', 'POST', { line_id: lineId })
            .done(function (res) {
                if (res.success) {
                    $row.fadeOut(300, function () {
                        $row.remove();
                        // Reload to recalculate totals.
                        location.reload();
                    });
                }
            })
            .fail(function () {
                showNotice('Failed to remove item.', 'error');
            });
    });

    // Update quantity.
    let qtyTimer = null;
    $(document).on('change', '.znc-qty', function () {
        const $input = $(this);
        const lineId = $input.data('line-id');
        const qty = parseInt($input.val(), 10);

        clearTimeout(qtyTimer);
        qtyTimer = setTimeout(function () {
            if (qty <= 0) {
                // Treat zero as remove.
                apiCall('global-cart/remove', 'POST', { line_id: lineId })
                    .done(function () { location.reload(); });
                return;
            }

            // Quantity update requires a page reload for now (totals are server-rendered).
            // In v2 this will be an AJAX partial.
            apiCall('global-cart/update-qty', 'POST', { line_id: lineId, quantity: qty })
                .done(function () { location.reload(); })
                .fail(function () {
                    showNotice('Failed to update quantity.', 'error');
                });
        }, 600);
    });

    // ── Checkout Page ────────────────────────────────────────────────────────

    $('#znc-checkout-form').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('.znc-place-order-btn');
        $btn.prop('disabled', true).text('Processing…');

        // Collect form data.
        const billing = {};
        $(this).find('[name^="billing["]').each(function () {
            const key = $(this).attr('name').match(/\[(\w+)\]/)[1];
            billing[key] = $(this).val();
        });

        const data = {
            billing: billing,
            shipping: billing, // same as billing for prototype
            payment_method: $('[name="payment_method"]').val(),
            mycred_amount: parseFloat($('[name="mycred_amount"]').val()) || 0,
        };

        apiCall('checkout', 'POST', data)
            .done(function (res) {
                if (res.success) {
                    const $result = $('#znc-checkout-result');
                    $result.html(
                        '<div class="znc-notice znc-notice-success">' +
                        '<h3>Order Placed Successfully!</h3>' +
                        '<p>Parent Order: #' + res.parent_order.order_id + '</p>' +
                        '<p>Child Orders: ' + res.child_orders.length + ' created</p>' +
                        (res.mycred_deducted > 0 ? '<p>Credits deducted: ' + res.mycred_deducted + '</p>' : '') +
                        '</div>'
                    ).show();
                    $('#znc-checkout-form').hide();
                } else {
                    showNotice('Checkout failed: ' + (res.message || 'Unknown error'), 'error');
                    $btn.prop('disabled', false).text('Place Order');
                }
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON?.message || 'Checkout request failed.';
                showNotice(msg, 'error');
                $btn.prop('disabled', false).text('Place Order');
            });
    });

    // ── Dynamic Notices CSS (injected) ───────────────────────────────────────
    $('<style>')
        .text(
            '.znc-notice{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:.95rem}' +
            '.znc-notice-success{background:#f0fff4;border:1px solid #68d391;color:#22543d}' +
            '.znc-notice-error{background:#fff5f5;border:1px solid #fc8181;color:#742a2a}'
        )
        .appendTo('head');

})(jQuery);
