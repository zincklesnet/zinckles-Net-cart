/**
 * Zinckles Net Cart — Admin JavaScript
 *
 * Shared utilities, confirmation dialogs, and UX enhancements
 * for all admin panels (Network, Main Site, Subsite).
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

(function($) {
    'use strict';

    var ZNC = window.ZNC = window.ZNC || {};

    /**
     * Toast notification system.
     */
    ZNC.toast = function(message, type) {
        type = type || 'success';
        var $toast = $('<div class="znc-toast znc-toast-' + type + '">' + message + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.addClass('znc-toast-visible'); }, 10);
        setTimeout(function() {
            $toast.removeClass('znc-toast-visible');
            setTimeout(function() { $toast.remove(); }, 300);
        }, 3000);
    };

    /**
     * Confirm dialog wrapper.
     */
    ZNC.confirm = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    /**
     * AJAX helper with automatic nonce.
     */
    ZNC.ajax = function(action, data, callback) {
        data = data || {};
        data.action = action;
        data.nonce  = data.nonce || (typeof zncAdmin !== 'undefined' ? zncAdmin.nonce : '');

        $.post(typeof zncAdmin !== 'undefined' ? zncAdmin.ajaxUrl : ajaxurl, data, function(response) {
            if (callback) callback(response);
        }).fail(function() {
            ZNC.toast(typeof zncAdmin !== 'undefined' ? zncAdmin.i18n.error : 'Error', 'error');
        });
    };

    /**
     * Initialize conditional field visibility.
     */
    ZNC.initConditionals = function() {
        // MyCred rows: show/hide based on MyCred enabled checkbox.
        $('input[name$="[mycred_enabled]"]').on('change', function() {
            $('.znc-mycred-row').toggle($(this).is(':checked'));
        }).trigger('change');

        // Flat rate row visibility based on shipping mode.
        $('input[name$="[shipping_mode]"]').on('change', function() {
            if (!$(this).is(':checked')) return;
            $('.znc-flat-rate-row').toggle($(this).val() === 'flat');
        }).filter(':checked').trigger('change');

        // Tax override row visibility.
        $('input[name$="[tax_mode]"]').on('change', function() {
            if (!$(this).is(':checked')) return;
            $('.znc-tax-override-row').toggle($(this).val() === 'override');
        }).filter(':checked').trigger('change');

        // Exchange rate source toggle.
        $('#znc-rate-source').on('change', function() {
            $('#znc-api-config').toggle($(this).val() === 'api');
        });

        // Product mode toggle.
        $('input[name$="[product_mode]"]').on('change', function() {
            if (!$(this).is(':checked')) return;
            var mode = $(this).val();
            $('#znc-product-selector').toggle(mode !== 'all');
            if (mode !== 'all') {
                $('#znc-selected-includes').toggle(mode === 'include');
                $('#znc-selected-excludes').toggle(mode === 'exclude');
            }
        }).filter(':checked').trigger('change');
    };

    /**
     * Initialize color pickers.
     */
    ZNC.initColorPickers = function() {
        if ($.fn.wpColorPicker) {
            $('input[type="color"]').each(function() {
                // Only enhance if not already a native color input we want to keep.
                // WordPress color picker provides a better UX with hex input.
            });
        }
    };

    /**
     * Unsaved changes warning.
     */
    ZNC.initUnsavedWarning = function() {
        var formChanged = false;

        $('.znc-admin-wrap form').on('change input', 'input, select, textarea', function() {
            formChanged = true;
        });

        $('.znc-admin-wrap form').on('submit', function() {
            formChanged = false;
        });

        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    };

    /**
     * Keyboard shortcut: Ctrl+S to save.
     */
    ZNC.initKeyboardShortcuts = function() {
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                var $form = $('.znc-admin-wrap form:first');
                if ($form.length) {
                    $form.find('[type="submit"]').first().trigger('click');
                }
            }
        });
    };

    /**
     * Smooth scroll to error notices.
     */
    ZNC.scrollToNotices = function() {
        var $notice = $('.notice-error, .notice-warning').first();
        if ($notice.length) {
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
        }
    };

    /**
     * Auto-dismiss success notices after delay.
     */
    ZNC.autoDismissNotices = function() {
        setTimeout(function() {
            $('.notice-success.is-dismissible').fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);
    };

    /**
     * Initialize everything on DOM ready.
     */
    $(function() {
        ZNC.initConditionals();
        ZNC.initColorPickers();
        ZNC.initUnsavedWarning();
        ZNC.initKeyboardShortcuts();
        ZNC.scrollToNotices();
        ZNC.autoDismissNotices();
    });

})(jQuery);
