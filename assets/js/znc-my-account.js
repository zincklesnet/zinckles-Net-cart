/**
 * Zinckles Net Cart — My Account Front-End JS
 *
 * Handles filter persistence, expandable order cards, AJAX detail loading,
 * and responsive interactions for the Net Cart Orders tab.
 *
 * @package Zinckles_Net_Cart
 * @since   1.1.0
 */

(function ($) {
    'use strict';

    var ZNCAccount = {

        /**
         * Initialize all account page interactions.
         */
        init: function () {
            this.bindFilterAutoSubmit();
            this.bindOrderCardToggle();
            this.bindPrintButton();
            this.bindShopSectionToggle();
            this.initTooltips();
            this.highlightActiveFilters();
            this.initStickyHeader();
        },

        /**
         * Auto-submit filters on select change.
         */
        bindFilterAutoSubmit: function () {
            $('.znc-filters__form select').on('change', function () {
                $(this).closest('form').submit();
            });

            // Clear date inputs.
            $('.znc-btn--clear').on('click', function (e) {
                e.preventDefault();
                var $form = $(this).closest('.znc-filters').find('form');
                $form.find('input, select').each(function () {
                    if (this.type === 'date' || this.type === 'text') {
                        $(this).val('');
                    } else if (this.tagName === 'SELECT') {
                        this.selectedIndex = 0;
                    }
                });
                window.location.href = $(this).attr('href');
            });
        },

        /**
         * Toggle order card expansion for quick preview.
         */
        bindOrderCardToggle: function () {
            $('.znc-order-card__header').on('click', function (e) {
                // Don't toggle if clicking a link.
                if ($(e.target).closest('a').length) return;

                var $card = $(this).closest('.znc-order-card');
                $card.toggleClass('znc-order-card--expanded');

                var $shops = $card.find('.znc-order-card__shops');
                if ($card.hasClass('znc-order-card--expanded')) {
                    $shops.slideDown(200);
                } else {
                    $shops.slideUp(200);
                }
            });
        },

        /**
         * Toggle shop sections in detail view.
         */
        bindShopSectionToggle: function () {
            $('.znc-shop-section__header').on('click', function (e) {
                if ($(e.target).closest('a').length) return;

                var $section = $(this).closest('.znc-shop-section');
                var $items = $section.find('.znc-shop-items, .znc-shop-totals, .znc-shop-notes');

                $section.toggleClass('znc-shop-section--collapsed');
                $items.slideToggle(200);
            });
        },

        /**
         * Print order detail functionality.
         */
        bindPrintButton: function () {
            $(document).on('click', '.znc-btn--print', function (e) {
                e.preventDefault();
                window.print();
            });
        },

        /**
         * Initialize tooltip behavior on badges and chips.
         */
        initTooltips: function () {
            $('[title]', '.znc-myaccount').each(function () {
                var $el = $(this);
                var title = $el.attr('title');
                if (!title) return;

                $el.removeAttr('title');
                $el.attr('data-znc-tooltip', title);

                $el.on('mouseenter', function () {
                    var $tip = $('<div class="znc-tooltip">' + title + '</div>');
                    $('body').append($tip);

                    var rect = this.getBoundingClientRect();
                    $tip.css({
                        top: rect.top - $tip.outerHeight() - 8 + window.scrollY,
                        left: rect.left + (rect.width / 2) - ($tip.outerWidth() / 2)
                    });
                });

                $el.on('mouseleave', function () {
                    $('.znc-tooltip').remove();
                });
            });
        },

        /**
         * Highlight filters that have active values.
         */
        highlightActiveFilters: function () {
            $('.znc-filter-group select, .znc-filter-group input').each(function () {
                var $input = $(this);
                var $group = $input.closest('.znc-filter-group');

                if ($input.val() && $input.val() !== '') {
                    $group.addClass('znc-filter-group--active');
                }
            });
        },

        /**
         * Make detail header sticky on scroll.
         */
        initStickyHeader: function () {
            var $header = $('.znc-detail-header');
            if (!$header.length) return;

            var headerTop = $header.offset().top;
            var $sentinel = $('<div class="znc-sticky-sentinel"></div>');
            $header.before($sentinel);

            $(window).on('scroll', function () {
                if (window.scrollY > headerTop + 60) {
                    $header.addClass('znc-detail-header--sticky');
                } else {
                    $header.removeClass('znc-detail-header--sticky');
                }
            });
        },

        /**
         * Format currency display consistently.
         *
         * @param {number} amount - The amount to format.
         * @param {string} symbol - Currency symbol.
         * @param {number} decimals - Decimal places.
         * @return {string}
         */
        formatCurrency: function (amount, symbol, decimals) {
            decimals = typeof decimals !== 'undefined' ? decimals : 2;
            symbol = symbol || '$';
            return symbol + parseFloat(amount).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Format ZCred amounts.
         *
         * @param {number} amount - ZCred amount.
         * @param {boolean} showSign - Whether to show +/-.
         * @return {string}
         */
        formatZCred: function (amount, showSign) {
            var formatted = parseFloat(amount).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (showSign && amount > 0) {
                return '+' + formatted;
            } else if (showSign && amount < 0) {
                return formatted;
            }
            return formatted;
        }
    };

    // ── Tooltip styles (injected dynamically) ──────────────
    $('<style>')
        .text(
            '.znc-tooltip{position:absolute;z-index:99999;padding:6px 12px;background:#1e1b4b;color:#fff;' +
            'font-size:12px;border-radius:6px;pointer-events:none;white-space:nowrap;' +
            'animation:zncFadeIn .15s ease}' +
            '.znc-tooltip::after{content:"";position:absolute;top:100%;left:50%;margin-left:-4px;' +
            'border:4px solid transparent;border-top-color:#1e1b4b}' +
            '@keyframes zncFadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}' +
            '.znc-detail-header--sticky{position:sticky;top:0;z-index:100;background:#fff;' +
            'padding:12px 0;border-bottom:1px solid #e5e2f0;box-shadow:0 2px 8px rgba(0,0,0,.08)}' +
            '.znc-filter-group--active label{color:#7c3aed;font-weight:700}' +
            '.znc-filter-group--active select,.znc-filter-group--active input{border-color:#7c3aed;background:#faf5ff}' +
            '.znc-shop-section--collapsed .znc-shop-section__header::after{content:"▸";margin-left:auto;font-size:14px;color:#6b7280}' +
            '.znc-shop-section__header{cursor:pointer}' +
            '.znc-shop-section__header:hover{background:color-mix(in srgb,var(--shop-color) 10%,white)}' +
            '@media print{.znc-filters,.znc-back-link,.znc-btn,.znc-pagination,.woocommerce-MyAccount-navigation{display:none!important}' +
            '.znc-myaccount{padding:0!important}.znc-detail-card{box-shadow:none!important;border:1px solid #ddd!important}}'
        )
        .appendTo('head');

    // ── Boot ───────────────────────────────────────────────
    $(document).ready(function () {
        if ($('.znc-myaccount').length || $('.znc-dashboard-widget').length) {
            ZNCAccount.init();
        }
    });

})(jQuery);
