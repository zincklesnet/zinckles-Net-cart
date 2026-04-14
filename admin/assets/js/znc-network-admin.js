/**
 * Zinckles Net Cart — Network Admin JS
 *
 * v1.2.0 — Fixed enrollment toggle:
 *  - Uses correct AJAX action name 'znc_toggle_site'
 *  - Sends 'enroll' param (not 'enrolled') matching PHP handler
 *  - Updates button text/class on success response
 *  - Disables button during request to prevent double-clicks
 */
(function ($) {
    'use strict';

    var ajax = zncAdmin || {};

    /* ─── Site Enrollment Toggle ──────────────────────────── */
    $(document).on('click', '.znc-toggle-enroll', function (e) {
        e.preventDefault();

        var $btn    = $(this);
        var blogId  = $btn.data('blog-id');
        var doEnroll = !$btn.hasClass('znc-enrolled'); // if not enrolled, we enroll

        // Prevent double-click.
        if ($btn.prop('disabled')) return;
        $btn.prop('disabled', true).addClass('znc-processing');

        var originalText = $btn.text();
        $btn.text('Processing…');

        $.post(ajax.ajaxUrl, {
            action:  'znc_toggle_site',
            nonce:   ajax.nonce,
            blog_id: blogId,
            enroll:  doEnroll ? '1' : '0'
        })
        .done(function (response) {
            if (response.success && response.data) {
                var enrolled = response.data.enrolled;
                var $row = $btn.closest('tr');

                if (enrolled) {
                    $btn.removeClass('button-primary')
                        .addClass('znc-enrolled button-secondary')
                        .text('Remove');
                    $row.find('.znc-status-badge')
                        .removeClass('znc-status-not-enrolled')
                        .addClass('znc-status-enrolled')
                        .text('Enrolled');
                } else {
                    $btn.removeClass('znc-enrolled button-secondary')
                        .addClass('button-primary')
                        .text('Enroll');
                    $row.find('.znc-status-badge')
                        .removeClass('znc-status-enrolled')
                        .addClass('znc-status-not-enrolled')
                        .text('Not Enrolled');
                }

                // Flash success.
                $row.css('background-color', enrolled ? '#e8f5e9' : '#fff3e0');
                setTimeout(function () {
                    $row.css('background-color', '');
                }, 1500);
            } else {
                alert('Error: ' + (response.data?.message || 'Unknown error'));
                $btn.text(originalText);
            }
        })
        .fail(function (xhr) {
            alert('AJAX request failed: ' + xhr.statusText);
            $btn.text(originalText);
        })
        .always(function () {
            $btn.prop('disabled', false).removeClass('znc-processing');
        });
    });

    /* ─── Test Connection ─────────────────────────────────── */
    $(document).on('click', '.znc-test-connection', function (e) {
        e.preventDefault();

        var $btn   = $(this);
        var blogId = $btn.data('blog-id');
        var $result = $btn.siblings('.znc-connection-result');

        $btn.prop('disabled', true);
        $result.html('<span class="spinner is-active" style="float:none;margin:0 4px;"></span> Testing…');

        $.post(ajax.ajaxUrl, {
            action:  'znc_test_site_connection',
            nonce:   ajax.nonce,
            blog_id: blogId
        })
        .done(function (response) {
            if (response.success && response.data) {
                var d = response.data;
                if (d.status === 'ok') {
                    $result.html('<span class="dashicons dashicons-yes-alt" style="color:#4caf50"></span> Connected');
                } else {
                    $result.html('<span class="dashicons dashicons-warning" style="color:#f44336"></span> ' + d.message);
                }
            }
        })
        .fail(function () {
            $result.html('<span class="dashicons dashicons-warning" style="color:#f44336"></span> Request failed');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ─── Regenerate Secret ───────────────────────────────── */
    $(document).on('click', '#znc-regenerate-secret', function (e) {
        e.preventDefault();

        if (!confirm('This will regenerate the HMAC secret and propagate it to all enrolled sites. Continue?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Regenerating…');

        $.post(ajax.ajaxUrl, {
            action: 'znc_regenerate_secret',
            nonce:  ajax.nonce
        })
        .done(function (response) {
            if (response.success && response.data) {
                $('#znc-secret-preview').text(response.data.secret_preview);
                $('#znc-secret-status').html(
                    '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>'
                );
            }
        })
        .fail(function () {
            alert('Failed to regenerate secret.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Regenerate & Propagate');
        });
    });

    /* ─── Detect MyCred Types ─────────────────────────────── */
    $(document).on('click', '#znc-detect-mycred', function (e) {
        e.preventDefault();

        var $btn = $(this);
        $btn.prop('disabled', true).text('Scanning network…');

        $.post(ajax.ajaxUrl, {
            action: 'znc_detect_mycred_types',
            nonce:  ajax.nonce
        })
        .done(function (response) {
            if (response.success && response.data) {
                var types = response.data.types;
                var $table = $('#znc-mycred-types-table tbody');
                $table.empty();

                $.each(types, function (slug, type) {
                    $table.append(
                        '<tr>' +
                        '<td><code>' + slug + '</code></td>' +
                        '<td>' + (type.label || slug) + '</td>' +
                        '<td>' + (type.singular || '') + ' / ' + (type.plural || '') + '</td>' +
                        '<td><input type="number" step="0.01" min="0" name="znc[mycred_point_types][' + slug + '][exchange_rate]" value="' + (type.exchange_rate || 1) + '" class="small-text"></td>' +
                        '<td><input type="number" min="0" max="100" name="znc[mycred_point_types][' + slug + '][max_percent]" value="' + (type.max_percent || 50) + '" class="small-text">%</td>' +
                        '<td><label><input type="checkbox" name="znc[mycred_point_types][' + slug + '][enabled]" value="1"' + (type.enabled ? ' checked' : '') + '> Active</label></td>' +
                        '<td><span class="znc-source-badge">' + (type.source || 'unknown') + '</span></td>' +
                        '<input type="hidden" name="znc[mycred_point_types][' + slug + '][label]" value="' + (type.label || slug) + '">' +
                        '</tr>'
                    );
                });

                $('#znc-mycred-detect-status').html(
                    '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>'
                );
            }
        })
        .fail(function () {
            alert('Failed to detect MyCred types.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Detect Point Types');
        });
    });

    /* ─── Test All Connections (Diagnostics) ───────────────── */
    $(document).on('click', '#znc-test-all', function (e) {
        e.preventDefault();
        $('.znc-test-connection').each(function (i) {
            var $btn = $(this);
            setTimeout(function () {
                $btn.trigger('click');
            }, i * 500); // stagger to avoid hammering
        });
    });

})(jQuery);
