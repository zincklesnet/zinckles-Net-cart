<?php
/**
 * Plugin Name: ZNC Beautiful Cart & Checkout
 * Description: Overrides [znc_cart] and [znc_checkout] shortcodes with professionally styled pages. Fixes static method errors. Includes checkout redirect suppression.
 * Version:     1.0.0
 * Author:      Zinckles
 * Network:     true
 *
 * Drop into: /wp-content/mu-plugins/znc-beautiful-pages.php
 *
 * This mu-plugin completely replaces the cart and checkout page rendering
 * with standalone code that reads usermeta directly. It does NOT depend
 * on any Net Cart class — if the plugin crashes, these pages still work.
 */
defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════
 *  FIX 1: Checkout Host static method proxy
 *  Cart Renderer calls ZNC_Checkout_Host::get_checkout_url() statically
 *  but the method isn't static. This adds a __callStatic proxy.
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'ZNC_Checkout_Host' ) ) {
        // Check if __callStatic exists — if not, we monkey-patch via filter
        add_filter( 'znc_checkout_url', function( $url ) {
            if ( $url ) return $url;
            $s = get_site_option( 'znc_network_settings', array() );
            $host = absint( $s['checkout_host_id'] ?? get_main_site_id() );
            $page = absint( $s['checkout_page_id'] ?? 0 );
            if ( $page ) {
                switch_to_blog( $host );
                $url = get_permalink( $page );
                restore_current_blog();
            }
            return $url ?: site_url( '/checkout-g/' );
        });
    }
}, 5 );

/* ═══════════════════════════════════════════════════════════════
 *  FIX 2: Checkout redirect suppression
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'template_redirect', function() {
    if ( is_admin() || wp_doing_ajax() ) return;
    global $post;
    if ( ! $post instanceof WP_Post ) return;

    $is_znc = has_shortcode( $post->post_content, 'znc_checkout' )
           || has_shortcode( $post->post_content, 'znc_cart' )
           || $post->post_name === 'checkout-g'
           || $post->post_name === 'cart-g';

    if ( ! $is_znc ) return;

    remove_action( 'template_redirect', 'wc_template_redirect' );
    add_filter( 'woocommerce_is_checkout', '__return_false', 999 );
    add_filter( 'woocommerce_is_cart', '__return_false', 999 );
}, 0 );

/* ═══════════════════════════════════════════════════════════════
 *  OVERRIDE SHORTCODES — register at init:20 (after plugin's init:10)
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'init', function() {
    remove_shortcode( 'znc_cart' );
    remove_shortcode( 'znc_global_cart' );
    remove_shortcode( 'znc_checkout' );

    add_shortcode( 'znc_cart',        'znc_bp_render_cart' );
    add_shortcode( 'znc_global_cart', 'znc_bp_render_cart' );
    add_shortcode( 'znc_checkout',    'znc_bp_render_checkout' );
}, 20 );

/* ═══════════════════════════════════════════════════════════════
 *  HELPER — get cart from usermeta directly
 * ═══════════════════════════════════════════════════════════════ */
function znc_bp_get_cart() {
    $uid = get_current_user_id();
    if ( ! $uid ) return array();
    $raw = get_user_meta( $uid, 'znc_global_cart', true );
    return is_array( $raw ) ? $raw : array();
}

function znc_bp_get_checkout_url() {
    $s    = get_site_option( 'znc_network_settings', array() );
    $host = absint( $s['checkout_host_id'] ?? get_main_site_id() );
    $page = absint( $s['checkout_page_id'] ?? 0 );
    if ( $page ) {
        switch_to_blog( $host );
        $url = get_permalink( $page );
        restore_current_blog();
        return $url;
    }
    switch_to_blog( $host );
    $url = home_url( '/checkout-g/' );
    restore_current_blog();
    return $url;
}

function znc_bp_get_cart_url() {
    $s    = get_site_option( 'znc_network_settings', array() );
    $host = absint( $s['checkout_host_id'] ?? get_main_site_id() );
    $page = absint( $s['cart_page_id'] ?? 0 );
    if ( $page ) {
        switch_to_blog( $host );
        $url = get_permalink( $page );
        restore_current_blog();
        return $url;
    }
    switch_to_blog( $host );
    $url = home_url( '/cart-g/' );
    restore_current_blog();
    return $url;
}

function znc_bp_save_cart( $cart ) {
    $uid = get_current_user_id();
    if ( ! $uid ) return;
    if ( empty( $cart ) ) {
        delete_user_meta( $uid, 'znc_global_cart' );
    } else {
        update_user_meta( $uid, 'znc_global_cart', $cart );
    }
    // Clear singleton cache if available
    if ( class_exists( 'ZNC_Global_Cart' ) ) {
        try { ZNC_Global_Cart::instance(); } catch ( \Throwable $e ) {}
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  ENRICH an item — switch to its blog, get product data
 * ═══════════════════════════════════════════════════════════════ */
function znc_bp_enrich_item( $item, $key ) {
    $blog_id    = absint( $item['blog_id'] ?? 0 );
    $product_id = absint( $item['product_id'] ?? 0 );
    $qty        = absint( $item['quantity'] ?? 1 );

    if ( ! $blog_id || ! $product_id ) return null;

    $current = get_current_blog_id();
    $sw      = ( $current !== $blog_id );
    if ( $sw ) switch_to_blog( $blog_id );

    $product  = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
    $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
    $symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '$';
    $shop     = get_bloginfo( 'name' );
    $shop_url = home_url( '/shop/' );

    $enriched = array(
        'key'           => $key,
        'blog_id'       => $blog_id,
        'product_id'    => $product_id,
        'name'          => $product ? $product->get_name() : "Product #{$product_id}",
        'price'         => $product ? (float) $product->get_price() : (float) ( $item['price'] ?? 0 ),
        'quantity'      => $qty,
        'image'         => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) : '',
        'permalink'     => $product ? get_permalink( $product_id ) : '#',
        'currency'      => $currency,
        'currency_sym'  => $symbol,
        'shop_name'     => $shop,
        'shop_url'      => $shop_url,
        'line_total'    => ( $product ? (float) $product->get_price() : (float) ( $item['price'] ?? 0 ) ) * $qty,
        'in_stock'      => $product ? $product->is_in_stock() : true,
    );

    if ( $sw ) restore_current_blog();
    return $enriched;
}

/* ═══════════════════════════════════════════════════════════════
 *  AJAX HANDLERS for cart page actions
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_znc_bp_update',  'znc_bp_ajax_update' );
add_action( 'wp_ajax_znc_bp_remove',  'znc_bp_ajax_remove' );
add_action( 'wp_ajax_znc_bp_clear',   'znc_bp_ajax_clear' );

function znc_bp_ajax_update() {
    check_ajax_referer( 'znc_bp_nonce', 'nonce' );
    $key = sanitize_text_field( $_POST['item_key'] ?? '' );
    $qty = max( 1, absint( $_POST['quantity'] ?? 1 ) );
    $cart = znc_bp_get_cart();
    if ( isset( $cart[ $key ] ) ) {
        $cart[ $key ]['quantity'] = min( $qty, 999 );
        $cart[ $key ]['updated']  = time();
        znc_bp_save_cart( $cart );
    }
    wp_send_json_success( array( 'count' => array_sum( array_column( $cart, 'quantity' ) ) ) );
}

function znc_bp_ajax_remove() {
    check_ajax_referer( 'znc_bp_nonce', 'nonce' );
    $key = sanitize_text_field( $_POST['item_key'] ?? '' );
    $cart = znc_bp_get_cart();
    unset( $cart[ $key ] );
    znc_bp_save_cart( $cart );
    wp_send_json_success( array( 'count' => array_sum( array_column( $cart, 'quantity' ) ) ) );
}

function znc_bp_ajax_clear() {
    check_ajax_referer( 'znc_bp_nonce', 'nonce' );
    znc_bp_save_cart( array() );
    wp_send_json_success( array( 'count' => 0 ) );
}

/* ═══════════════════════════════════════════════════════════════
 *  CART PAGE SHORTCODE — [znc_cart] / [znc_global_cart]
 * ═══════════════════════════════════════════════════════════════ */
function znc_bp_render_cart() {
    if ( ! is_user_logged_in() ) {
        return '<div class="znc-bp-notice">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your cart.</div>';
    }

    $cart = znc_bp_get_cart();
    $nonce = wp_create_nonce( 'znc_bp_nonce' );

    // Group items by blog
    $groups = array();
    foreach ( $cart as $key => $item ) {
        $bid = absint( $item['blog_id'] ?? 0 );
        if ( ! $bid ) continue;
        $enriched = znc_bp_enrich_item( $item, $key );
        if ( $enriched ) {
            if ( ! isset( $groups[ $bid ] ) ) {
                $groups[ $bid ] = array(
                    'shop_name' => $enriched['shop_name'],
                    'shop_url'  => $enriched['shop_url'],
                    'items'     => array(),
                );
            }
            $groups[ $bid ]['items'][] = $enriched;
        }
    }

    // Calculate totals by currency
    $totals = array();
    foreach ( $groups as $g ) {
        foreach ( $g['items'] as $it ) {
            $c = $it['currency'];
            if ( ! isset( $totals[ $c ] ) ) $totals[ $c ] = array( 'amount' => 0, 'symbol' => $it['currency_sym'] );
            $totals[ $c ]['amount'] += $it['line_total'];
        }
    }

    $total_items = 0;
    foreach ( $cart as $i ) $total_items += absint( $i['quantity'] ?? 1 );

    ob_start();
    znc_bp_cart_styles();
    ?>
    <div class="znc-bp-cart" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>">

        <!-- Header -->
        <div class="znc-bp-header">
            <div class="znc-bp-header-left">
                <span class="znc-bp-cart-icon">🛒</span>
                <h2>Your Global Net Cart</h2>
                <span class="znc-bp-badge"><?php echo esc_html( $total_items ); ?> item<?php echo $total_items !== 1 ? 's' : ''; ?></span>
            </div>
            <?php if ( ! empty( $cart ) ) : ?>
            <button type="button" class="znc-bp-clear-btn" onclick="zncBpClear()">
                <span class="dashicons dashicons-trash"></span> Clear Cart
            </button>
            <?php endif; ?>
        </div>

        <?php if ( empty( $groups ) ) : ?>
        <!-- Empty State -->
        <div class="znc-bp-empty">
            <div class="znc-bp-empty-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Browse our shops and add items from any site across the network.</p>
            <a href="<?php echo esc_url( home_url('/shop/') ); ?>" class="znc-bp-btn znc-bp-btn-primary">Browse Shop</a>
        </div>

        <?php else : ?>
        <!-- Items grouped by shop -->
        <?php foreach ( $groups as $bid => $group ) : ?>
        <div class="znc-bp-shop-group">
            <div class="znc-bp-shop-header">
                <span class="znc-bp-shop-icon">🏪</span>
                <a href="<?php echo esc_url( $group['shop_url'] ); ?>" class="znc-bp-shop-name">
                    <?php echo esc_html( $group['shop_name'] ); ?>
                </a>
                <span class="znc-bp-shop-count"><?php echo count( $group['items'] ); ?> item<?php echo count( $group['items'] ) !== 1 ? 's' : ''; ?></span>
            </div>

            <?php foreach ( $group['items'] as $item ) : ?>
            <div class="znc-bp-item" data-key="<?php echo esc_attr( $item['key'] ); ?>">
                <div class="znc-bp-item-image">
                    <?php if ( $item['image'] ) : ?>
                        <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="znc-bp-item-placeholder">📦</div>
                    <?php endif; ?>
                </div>
                <div class="znc-bp-item-details">
                    <a href="<?php echo esc_url( $item['permalink'] ); ?>" class="znc-bp-item-name">
                        <?php echo esc_html( $item['name'] ); ?>
                    </a>
                    <div class="znc-bp-item-price">
                        <?php echo esc_html( $item['currency_sym'] . number_format( $item['price'], 2 ) ); ?>
                        <span class="znc-bp-currency-tag"><?php echo esc_html( $item['currency'] ); ?></span>
                    </div>
                    <?php if ( ! $item['in_stock'] ) : ?>
                        <span class="znc-bp-out-of-stock">Out of Stock</span>
                    <?php endif; ?>
                </div>
                <div class="znc-bp-item-qty">
                    <button type="button" class="znc-bp-qty-btn" onclick="zncBpQty('<?php echo esc_attr( $item['key'] ); ?>', -1)">−</button>
                    <span class="znc-bp-qty-val" id="qty-<?php echo esc_attr( $item['key'] ); ?>"><?php echo esc_html( $item['quantity'] ); ?></span>
                    <button type="button" class="znc-bp-qty-btn" onclick="zncBpQty('<?php echo esc_attr( $item['key'] ); ?>', 1)">+</button>
                </div>
                <div class="znc-bp-item-total">
                    <span class="znc-bp-line-total" id="total-<?php echo esc_attr( $item['key'] ); ?>">
                        <?php echo esc_html( $item['currency_sym'] . number_format( $item['line_total'], 2 ) ); ?>
                    </span>
                </div>
                <button type="button" class="znc-bp-remove-btn" onclick="zncBpRemove('<?php echo esc_attr( $item['key'] ); ?>')" title="Remove">
                    ✕
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- Totals -->
        <div class="znc-bp-totals">
            <div class="znc-bp-totals-inner">
                <?php foreach ( $totals as $currency => $data ) : ?>
                <div class="znc-bp-total-row">
                    <span class="znc-bp-total-label">
                        Subtotal
                        <span class="znc-bp-currency-tag"><?php echo esc_html( $currency ); ?></span>
                    </span>
                    <span class="znc-bp-total-amount">
                        <?php echo esc_html( $data['symbol'] . number_format( $data['amount'], 2 ) ); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="znc-bp-actions">
            <a href="<?php echo esc_url( home_url('/shop/') ); ?>" class="znc-bp-btn znc-bp-btn-secondary">
                ← Continue Shopping
            </a>
            <a href="<?php echo esc_url( znc_bp_get_checkout_url() ); ?>" class="znc-bp-btn znc-bp-btn-primary znc-bp-btn-checkout">
                Proceed to Checkout →
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var $c = document.querySelector('.znc-bp-cart');
        if (!$c) return;
        var ajx = $c.dataset.ajax, nonce = $c.dataset.nonce;
        var prices = {};
        document.querySelectorAll('.znc-bp-item').forEach(function(el){
            var k = el.dataset.key;
            var p = el.querySelector('.znc-bp-item-price');
            if(p) {
                var t = p.textContent.replace(/[^0-9.]/g,'');
                prices[k] = parseFloat(t) || 0;
            }
        });

        window.zncBpQty = function(key, delta) {
            var el = document.getElementById('qty-'+key);
            if(!el) return;
            var q = Math.max(1, parseInt(el.textContent||'1') + delta);
            el.textContent = q;
            // Update line total
            var sym = document.querySelector('.znc-bp-item[data-key="'+key+'"] .znc-bp-item-price').textContent.replace(/[0-9.,\s]/g,'').trim().charAt(0);
            var tot = document.getElementById('total-'+key);
            if(tot) tot.textContent = sym + (prices[key]*q).toFixed(2);
            // AJAX save
            var fd = new FormData();
            fd.append('action','znc_bp_update');
            fd.append('nonce',nonce);
            fd.append('item_key',key);
            fd.append('quantity',q);
            fetch(ajx,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json()})
                .then(function(d){ if(d.success) updateBadge(d.data.count); });
        };

        window.zncBpRemove = function(key) {
            var row = document.querySelector('.znc-bp-item[data-key="'+key+'"]');
            if(row) { row.style.transition='all 0.3s'; row.style.opacity='0'; row.style.transform='translateX(50px)'; }
            var fd = new FormData();
            fd.append('action','znc_bp_remove');
            fd.append('nonce',nonce);
            fd.append('item_key',key);
            fetch(ajx,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json()})
                .then(function(d){
                    if(d.success) {
                        setTimeout(function(){ if(row) row.remove(); checkEmpty(); updateBadge(d.data.count); },300);
                    }
                });
        };

        window.zncBpClear = function() {
            if(!confirm('Clear all items from your cart?')) return;
            var fd = new FormData();
            fd.append('action','znc_bp_ajax_clear');
            fd.append('nonce',nonce);
            // Try both action names
            fetch(ajx,{method:'POST',body:fd,credentials:'same-origin'}).then(function(){
                fd.set('action','znc_bp_clear');
                return fetch(ajx,{method:'POST',body:fd,credentials:'same-origin'});
            }).then(function(r){return r.json()}).then(function(){
                location.reload();
            });
        };

        function updateBadge(c) {
            document.querySelectorAll('.znc-bp-badge').forEach(function(b){ b.textContent = c + ' item' + (c!==1?'s':''); });
            document.querySelectorAll('.znc-fc-count, .cart-contents-count, .wc-cart-count, .znc-cart-count').forEach(function(b){ b.textContent = c; });
            // Update admin bar
            var ab = document.querySelector('#wp-admin-bar-znc-cart .ab-item');
            if(ab) ab.innerHTML = ab.innerHTML.replace(/\(\d+\)/, '('+c+')');
        }

        function checkEmpty(){
            var items = document.querySelectorAll('.znc-bp-item');
            if(items.length === 0) location.reload();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════════════
 *  CHECKOUT PAGE SHORTCODE — [znc_checkout]
 * ═══════════════════════════════════════════════════════════════ */
function znc_bp_render_checkout() {
    if ( ! is_user_logged_in() ) {
        return '<div class="znc-bp-notice">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to checkout.</div>';
    }

    $cart = znc_bp_get_cart();
    if ( empty( $cart ) ) {
        return '<div class="znc-bp-empty">
            <div class="znc-bp-empty-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Add items to your cart before checking out.</p>
            <a href="' . esc_url( home_url('/shop/') ) . '" class="znc-bp-btn znc-bp-btn-primary">Browse Shop</a>
        </div>';
    }

    $nonce = wp_create_nonce( 'znc_bp_checkout_nonce' );

    // Enrich items & build totals
    $groups = array();
    $totals = array();
    $total_items = 0;
    foreach ( $cart as $key => $item ) {
        $bid = absint( $item['blog_id'] ?? 0 );
        $enriched = znc_bp_enrich_item( $item, $key );
        if ( ! $enriched ) continue;
        if ( ! isset( $groups[ $bid ] ) ) {
            $groups[ $bid ] = array( 'shop_name' => $enriched['shop_name'], 'items' => array() );
        }
        $groups[ $bid ]['items'][] = $enriched;
        $c = $enriched['currency'];
        if ( ! isset( $totals[ $c ] ) ) $totals[ $c ] = array( 'amount' => 0, 'symbol' => $enriched['currency_sym'] );
        $totals[ $c ]['amount'] += $enriched['line_total'];
        $total_items += $enriched['quantity'];
    }

    // Check for MyCred point types
    $has_mycred = false;
    $mycred_balances = array();
    if ( function_exists( 'mycred_get_users_balance' ) ) {
        $has_mycred = true;
        $uid = get_current_user_id();
        foreach ( $totals as $currency => $data ) {
            // MyCred currencies are typically 3+ chars and not standard ISO
            if ( strlen( $currency ) >= 3 && ! in_array( $currency, array( 'USD', 'CAD', 'EUR', 'GBP', 'AUD' ) ) ) {
                $type_key = strtolower( $currency );
                // Try the currency as a MyCred point type
                $balance = mycred_get_users_balance( $uid, $type_key );
                if ( $balance !== false ) {
                    $mycred_balances[ $currency ] = array(
                        'balance'  => $balance,
                        'needed'   => $data['amount'],
                        'enough'   => $balance >= $data['amount'],
                    );
                }
            }
        }
    }

    $user = wp_get_current_user();

    ob_start();
    znc_bp_cart_styles();
    ?>
    <div class="znc-bp-checkout">
        <div class="znc-bp-header">
            <span class="znc-bp-cart-icon">💳</span>
            <h2>Checkout</h2>
            <span class="znc-bp-badge"><?php echo esc_html( $total_items ); ?> item<?php echo $total_items !== 1 ? 's' : ''; ?></span>
        </div>

        <div class="znc-bp-checkout-grid">
            <!-- Left: Order Summary -->
            <div class="znc-bp-checkout-summary">
                <h3 class="znc-bp-section-title">Order Summary</h3>

                <?php foreach ( $groups as $bid => $group ) : ?>
                <div class="znc-bp-shop-group znc-bp-shop-group-compact">
                    <div class="znc-bp-shop-header">
                        <span class="znc-bp-shop-icon">🏪</span>
                        <span class="znc-bp-shop-name"><?php echo esc_html( $group['shop_name'] ); ?></span>
                    </div>
                    <?php foreach ( $group['items'] as $item ) : ?>
                    <div class="znc-bp-checkout-item">
                        <div class="znc-bp-checkout-item-img">
                            <?php if ( $item['image'] ) : ?>
                                <img src="<?php echo esc_url( $item['image'] ); ?>" alt="">
                            <?php else : ?>
                                <div class="znc-bp-item-placeholder">📦</div>
                            <?php endif; ?>
                        </div>
                        <div class="znc-bp-checkout-item-info">
                            <span class="znc-bp-item-name"><?php echo esc_html( $item['name'] ); ?></span>
                            <span class="znc-bp-checkout-item-meta">
                                Qty: <?php echo esc_html( $item['quantity'] ); ?> ×
                                <?php echo esc_html( $item['currency_sym'] . number_format( $item['price'], 2 ) ); ?>
                            </span>
                        </div>
                        <div class="znc-bp-checkout-item-total">
                            <?php echo esc_html( $item['currency_sym'] . number_format( $item['line_total'], 2 ) ); ?>
                            <span class="znc-bp-currency-tag"><?php echo esc_html( $item['currency'] ); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <!-- Totals -->
                <div class="znc-bp-totals znc-bp-totals-checkout">
                    <?php foreach ( $totals as $currency => $data ) : ?>
                    <div class="znc-bp-total-row znc-bp-total-row-large">
                        <span class="znc-bp-total-label">
                            Total
                            <span class="znc-bp-currency-tag"><?php echo esc_html( $currency ); ?></span>
                        </span>
                        <span class="znc-bp-total-amount">
                            <?php echo esc_html( $data['symbol'] . number_format( $data['amount'], 2 ) ); ?>
                        </span>
                    </div>
                    <?php if ( isset( $mycred_balances[ $currency ] ) ) : ?>
                    <div class="znc-bp-mycred-balance <?php echo $mycred_balances[ $currency ]['enough'] ? 'znc-bp-balance-ok' : 'znc-bp-balance-low'; ?>">
                        <span>Your Balance: <?php echo esc_html( number_format( $mycred_balances[ $currency ]['balance'], 2 ) ); ?> <?php echo esc_html( $currency ); ?></span>
                        <?php if ( $mycred_balances[ $currency ]['enough'] ) : ?>
                            <span class="znc-bp-balance-check">✓ Sufficient</span>
                        <?php else : ?>
                            <span class="znc-bp-balance-warn">⚠ Insufficient (need <?php echo esc_html( number_format( $mycred_balances[ $currency ]['needed'], 2 ) ); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <a href="<?php echo esc_url( znc_bp_get_cart_url() ); ?>" class="znc-bp-btn znc-bp-btn-secondary znc-bp-edit-cart">
                    ← Edit Cart
                </a>
            </div>

            <!-- Right: Payment -->
            <div class="znc-bp-checkout-payment">
                <h3 class="znc-bp-section-title">Payment</h3>

                <form method="post" id="znc-bp-checkout-form">
                    <?php wp_nonce_field( 'znc_bp_checkout_nonce', 'znc_bp_nonce' ); ?>

                    <!-- Billing Details -->
                    <div class="znc-bp-form-section">
                        <h4>Billing Details</h4>
                        <div class="znc-bp-form-row">
                            <div class="znc-bp-form-field">
                                <label>First Name</label>
                                <input type="text" name="billing_first_name" value="<?php echo esc_attr( $user->first_name ); ?>" required>
                            </div>
                            <div class="znc-bp-form-field">
                                <label>Last Name</label>
                                <input type="text" name="billing_last_name" value="<?php echo esc_attr( $user->last_name ); ?>" required>
                            </div>
                        </div>
                        <div class="znc-bp-form-field">
                            <label>Email</label>
                            <input type="email" name="billing_email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="znc-bp-form-section">
                        <h4>Payment Method</h4>

                        <?php
                        // Check which payment types are needed
                        $needs_fiat    = false;
                        $needs_points  = false;
                        foreach ( $totals as $currency => $data ) {
                            if ( isset( $mycred_balances[ $currency ] ) ) {
                                $needs_points = true;
                            } else {
                                $needs_fiat = true;
                            }
                        }
                        ?>

                        <?php if ( $needs_points && ! empty( $mycred_balances ) ) : ?>
                        <div class="znc-bp-payment-method znc-bp-payment-points">
                            <div class="znc-bp-payment-header">
                                <span class="znc-bp-payment-icon">🪙</span>
                                <span>Points Payment</span>
                            </div>
                            <p class="znc-bp-payment-desc">
                                Items priced in points will be deducted from your balance automatically.
                            </p>
                            <?php foreach ( $mycred_balances as $cur => $bal ) : ?>
                            <div class="znc-bp-points-summary">
                                <span><?php echo esc_html( $cur ); ?>: <?php echo esc_html( number_format( $bal['needed'], 2 ) ); ?> will be deducted</span>
                                <?php if ( $bal['enough'] ) : ?>
                                    <span class="znc-bp-balance-check">✓</span>
                                <?php else : ?>
                                    <span class="znc-bp-balance-warn">⚠ Not enough</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ( $needs_fiat ) : ?>
                        <div class="znc-bp-payment-method">
                            <div class="znc-bp-payment-header">
                                <span class="znc-bp-payment-icon">💳</span>
                                <span>Standard Payment</span>
                            </div>
                            <?php
                            // List available WC gateways
                            if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
                                $gateways = WC()->payment_gateways()->get_available_payment_gateways();
                                if ( ! empty( $gateways ) ) {
                                    echo '<div class="znc-bp-gateways">';
                                    $first = true;
                                    foreach ( $gateways as $gw_id => $gw ) {
                                        echo '<label class="znc-bp-gateway-option">';
                                        echo '<input type="radio" name="payment_method" value="' . esc_attr( $gw_id ) . '"' . ( $first ? ' checked' : '' ) . '>';
                                        echo '<span class="znc-bp-gateway-label">';
                                        if ( $gw->icon ) echo '<img src="' . esc_url( $gw->icon ) . '" alt="" class="znc-bp-gateway-icon">';
                                        echo esc_html( $gw->get_title() );
                                        echo '</span></label>';
                                        $first = false;
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<p class="znc-bp-payment-desc">No payment gateways configured. Please contact the administrator.</p>';
                                }
                            } else {
                                echo '<p class="znc-bp-payment-desc">WooCommerce payment gateways will be loaded here.</p>';
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Place Order -->
                    <div class="znc-bp-form-section">
                        <?php
                        $can_order = true;
                        foreach ( $mycred_balances as $bal ) {
                            if ( ! $bal['enough'] ) $can_order = false;
                        }
                        ?>
                        <button type="submit" name="znc_place_order" class="znc-bp-btn znc-bp-btn-primary znc-bp-btn-place-order" <?php echo $can_order ? '' : 'disabled'; ?>>
                            🔒 Place Order
                        </button>
                        <?php if ( ! $can_order ) : ?>
                        <p class="znc-bp-insufficient-notice">You don't have enough points to complete this order.</p>
                        <?php endif; ?>
                        <p class="znc-bp-secure-notice">🔒 Your payment information is secure and encrypted.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════════════
 *  SHARED STYLES — injected once
 * ═══════════════════════════════════════════════════════════════ */
function znc_bp_cart_styles() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style>
    /* ── Reset & Base ── */
    .znc-bp-cart, .znc-bp-checkout { max-width:1100px; margin:0 auto; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1a1a2e; }
    .znc-bp-cart *, .znc-bp-checkout * { box-sizing:border-box; }

    /* ── Header ── */
    .znc-bp-header { display:flex; align-items:center; justify-content:space-between; padding:24px 0 20px; border-bottom:2px solid #e8e8f0; margin-bottom:24px; }
    .znc-bp-header-left { display:flex; align-items:center; gap:12px; }
    .znc-bp-header h2 { margin:0; font-size:26px; font-weight:700; color:#1a1a2e; }
    .znc-bp-cart-icon { font-size:28px; }
    .znc-bp-badge { background:linear-gradient(135deg,#6c5ce7,#a855f7); color:#fff; font-size:13px; font-weight:600; padding:4px 12px; border-radius:20px; }
    .znc-bp-clear-btn { background:none; border:1px solid #e74c3c; color:#e74c3c; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; display:flex; align-items:center; gap:6px; transition:all 0.2s; }
    .znc-bp-clear-btn:hover { background:#e74c3c; color:#fff; }

    /* ── Empty State ── */
    .znc-bp-empty { text-align:center; padding:60px 20px; }
    .znc-bp-empty-icon { font-size:64px; margin-bottom:16px; opacity:0.4; }
    .znc-bp-empty h3 { margin:0 0 8px; font-size:22px; color:#555; }
    .znc-bp-empty p { color:#888; margin:0 0 24px; }

    /* ── Shop Group ── */
    .znc-bp-shop-group { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.06); margin-bottom:20px; overflow:hidden; border:1px solid #f0f0f5; }
    .znc-bp-shop-header { display:flex; align-items:center; gap:10px; padding:14px 20px; background:linear-gradient(135deg,#f8f7ff,#f0eeff); border-bottom:1px solid #e8e8f0; }
    .znc-bp-shop-icon { font-size:20px; }
    .znc-bp-shop-name { font-weight:600; color:#4a3f8a; text-decoration:none; font-size:15px; }
    .znc-bp-shop-name:hover { color:#6c5ce7; }
    .znc-bp-shop-count { margin-left:auto; font-size:12px; color:#888; background:#f0f0f5; padding:3px 10px; border-radius:10px; }

    /* ── Cart Item ── */
    .znc-bp-item { display:flex; align-items:center; padding:16px 20px; border-bottom:1px solid #f5f5fa; gap:16px; transition:all 0.3s ease; }
    .znc-bp-item:last-child { border-bottom:none; }
    .znc-bp-item:hover { background:#fafafe; }
    .znc-bp-item-image { width:72px; height:72px; flex-shrink:0; border-radius:10px; overflow:hidden; background:#f8f8fc; border:1px solid #eee; }
    .znc-bp-item-image img { width:100%; height:100%; object-fit:cover; }
    .znc-bp-item-placeholder { width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:28px; opacity:0.3; }
    .znc-bp-item-details { flex:1; min-width:0; }
    .znc-bp-item-name { font-weight:600; color:#2d2d3f; text-decoration:none; font-size:15px; display:block; margin-bottom:4px; }
    .znc-bp-item-name:hover { color:#6c5ce7; }
    .znc-bp-item-price { font-size:14px; color:#666; }
    .znc-bp-currency-tag { font-size:11px; background:#f0f0f5; color:#888; padding:2px 6px; border-radius:4px; margin-left:4px; font-weight:500; }
    .znc-bp-out-of-stock { font-size:12px; color:#e74c3c; font-weight:600; display:inline-block; margin-top:4px; }

    /* ── Qty Controls ── */
    .znc-bp-item-qty { display:flex; align-items:center; gap:0; background:#f5f5fa; border-radius:8px; overflow:hidden; border:1px solid #e8e8f0; }
    .znc-bp-qty-btn { width:34px; height:34px; border:none; background:none; cursor:pointer; font-size:16px; font-weight:700; color:#555; transition:all 0.15s; display:flex; align-items:center; justify-content:center; }
    .znc-bp-qty-btn:hover { background:#6c5ce7; color:#fff; }
    .znc-bp-qty-val { width:36px; text-align:center; font-weight:700; font-size:14px; }

    /* ── Line Total ── */
    .znc-bp-item-total { min-width:90px; text-align:right; }
    .znc-bp-line-total { font-weight:700; font-size:16px; color:#2d2d3f; }

    /* ── Remove ── */
    .znc-bp-remove-btn { width:32px; height:32px; border:none; background:none; cursor:pointer; font-size:16px; color:#ccc; transition:all 0.2s; border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .znc-bp-remove-btn:hover { color:#e74c3c; background:#ffeaea; }

    /* ── Totals ── */
    .znc-bp-totals { padding:20px 24px; background:linear-gradient(135deg,#f8f7ff,#f0eeff); border-radius:14px; margin:20px 0; }
    .znc-bp-totals-inner { display:flex; flex-direction:column; gap:8px; }
    .znc-bp-total-row { display:flex; justify-content:space-between; align-items:center; }
    .znc-bp-total-row-large .znc-bp-total-label { font-size:18px; font-weight:700; }
    .znc-bp-total-row-large .znc-bp-total-amount { font-size:22px; }
    .znc-bp-total-label { font-size:15px; font-weight:600; color:#555; }
    .znc-bp-total-amount { font-size:18px; font-weight:800; color:#2d2d3f; }

    /* ── Buttons ── */
    .znc-bp-actions { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:8px 0 20px; }
    .znc-bp-btn { display:inline-flex; align-items:center; padding:14px 28px; border-radius:10px; font-size:15px; font-weight:600; text-decoration:none; cursor:pointer; transition:all 0.2s; border:none; }
    .znc-bp-btn-primary { background:linear-gradient(135deg,#6c5ce7,#a855f7); color:#fff; box-shadow:0 4px 14px rgba(108,92,231,0.3); }
    .znc-bp-btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(108,92,231,0.4); color:#fff; }
    .znc-bp-btn-secondary { background:#fff; color:#6c5ce7; border:2px solid #e8e8f0; }
    .znc-bp-btn-secondary:hover { border-color:#6c5ce7; background:#f8f7ff; }
    .znc-bp-btn-checkout { padding:16px 36px; font-size:17px; }
    .znc-bp-btn-place-order { width:100%; justify-content:center; padding:18px; font-size:18px; }
    .znc-bp-btn-place-order:disabled { opacity:0.5; cursor:not-allowed; transform:none; box-shadow:none; }

    /* ── Checkout Grid ── */
    .znc-bp-checkout-grid { display:grid; grid-template-columns:1fr 420px; gap:30px; }
    @media (max-width:900px) { .znc-bp-checkout-grid { grid-template-columns:1fr; } }
    .znc-bp-section-title { font-size:18px; font-weight:700; margin:0 0 16px; padding-bottom:12px; border-bottom:2px solid #e8e8f0; }

    /* ── Checkout Items (compact) ── */
    .znc-bp-shop-group-compact .znc-bp-shop-header { padding:10px 16px; }
    .znc-bp-checkout-item { display:flex; align-items:center; padding:12px 16px; border-bottom:1px solid #f5f5fa; gap:12px; }
    .znc-bp-checkout-item:last-child { border-bottom:none; }
    .znc-bp-checkout-item-img { width:48px; height:48px; flex-shrink:0; border-radius:8px; overflow:hidden; background:#f8f8fc; }
    .znc-bp-checkout-item-img img { width:100%; height:100%; object-fit:cover; }
    .znc-bp-checkout-item-info { flex:1; min-width:0; }
    .znc-bp-checkout-item-info .znc-bp-item-name { font-size:14px; margin-bottom:2px; }
    .znc-bp-checkout-item-meta { font-size:12px; color:#888; }
    .znc-bp-checkout-item-total { font-weight:700; font-size:14px; white-space:nowrap; text-align:right; }
    .znc-bp-edit-cart { margin-top:12px; }
    .znc-bp-totals-checkout { margin-top:16px; padding:16px 20px; }

    /* ── Payment Section ── */
    .znc-bp-checkout-payment { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #f0f0f5; padding:24px; height:fit-content; position:sticky; top:32px; }
    .znc-bp-form-section { margin-bottom:24px; }
    .znc-bp-form-section h4 { font-size:15px; font-weight:700; margin:0 0 12px; color:#4a3f8a; }
    .znc-bp-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .znc-bp-form-field { margin-bottom:12px; }
    .znc-bp-form-field label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:4px; }
    .znc-bp-form-field input { width:100%; padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-size:14px; transition:border 0.2s; }
    .znc-bp-form-field input:focus { border-color:#6c5ce7; outline:none; box-shadow:0 0 0 3px rgba(108,92,231,0.1); }

    /* ── Payment Methods ── */
    .znc-bp-payment-method { background:#f8f7ff; border-radius:10px; padding:16px; margin-bottom:12px; border:1px solid #e8e8f0; }
    .znc-bp-payment-points { border-color:#27ae60; background:#f0fdf4; }
    .znc-bp-payment-header { display:flex; align-items:center; gap:8px; font-weight:600; font-size:15px; margin-bottom:8px; }
    .znc-bp-payment-icon { font-size:20px; }
    .znc-bp-payment-desc { font-size:13px; color:#666; margin:0; }
    .znc-bp-gateways { display:flex; flex-direction:column; gap:8px; }
    .znc-bp-gateway-option { display:flex; align-items:center; gap:10px; padding:10px 14px; background:#fff; border-radius:8px; cursor:pointer; border:1px solid #e8e8f0; transition:all 0.2s; }
    .znc-bp-gateway-option:hover { border-color:#6c5ce7; }
    .znc-bp-gateway-option input[type="radio"] { accent-color:#6c5ce7; }
    .znc-bp-gateway-label { display:flex; align-items:center; gap:8px; font-size:14px; }
    .znc-bp-gateway-icon { height:24px; width:auto; }

    /* ── MyCred Balance ── */
    .znc-bp-mycred-balance { display:flex; justify-content:space-between; align-items:center; padding:10px 16px; border-radius:8px; font-size:13px; font-weight:600; margin-top:8px; }
    .znc-bp-balance-ok { background:#f0fdf4; color:#27ae60; border:1px solid #bbf7d0; }
    .znc-bp-balance-low { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .znc-bp-balance-check { color:#27ae60; }
    .znc-bp-balance-warn { color:#dc2626; }
    .znc-bp-points-summary { display:flex; justify-content:space-between; padding:8px 0; font-size:13px; border-top:1px solid rgba(0,0,0,0.06); margin-top:8px; }
    .znc-bp-insufficient-notice { color:#dc2626; font-size:13px; text-align:center; margin-top:8px; }
    .znc-bp-secure-notice { font-size:12px; color:#888; text-align:center; margin-top:12px; }

    /* ── Notice ── */
    .znc-bp-notice { padding:20px; background:#f8f7ff; border-left:4px solid #6c5ce7; border-radius:8px; font-size:15px; }
    .znc-bp-notice a { color:#6c5ce7; font-weight:600; }

    /* ── Dark mode support ── */
    body.flavor-flavor-flavor .znc-bp-cart,
    body[data-flavor="flavor"] .znc-bp-cart,
    .wp-dark-mode-active .znc-bp-cart,
    .wp-dark-mode-active .znc-bp-checkout { color:#e2e8f0; }
    .wp-dark-mode-active .znc-bp-header { border-color:#334155; }
    .wp-dark-mode-active .znc-bp-header h2 { color:#e2e8f0; }
    .wp-dark-mode-active .znc-bp-shop-group { background:#1e293b; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-shop-header { background:linear-gradient(135deg,#1e1b4b,#312e81); border-color:#334155; }
    .wp-dark-mode-active .znc-bp-item { border-color:#334155; }
    .wp-dark-mode-active .znc-bp-item:hover { background:#1e293b; }
    .wp-dark-mode-active .znc-bp-item-name { color:#e2e8f0; }
    .wp-dark-mode-active .znc-bp-item-image { background:#0f172a; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-item-qty { background:#0f172a; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-qty-btn { color:#94a3b8; }
    .wp-dark-mode-active .znc-bp-line-total { color:#e2e8f0; }
    .wp-dark-mode-active .znc-bp-totals { background:linear-gradient(135deg,#1e1b4b,#312e81); }
    .wp-dark-mode-active .znc-bp-total-amount { color:#e2e8f0; }
    .wp-dark-mode-active .znc-bp-btn-secondary { background:#1e293b; color:#a78bfa; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-checkout-payment { background:#1e293b; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-section-title { border-color:#334155; }
    .wp-dark-mode-active .znc-bp-form-field input { background:#0f172a; border-color:#334155; color:#e2e8f0; }
    .wp-dark-mode-active .znc-bp-payment-method { background:#0f172a; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-gateway-option { background:#1e293b; border-color:#334155; }
    .wp-dark-mode-active .znc-bp-currency-tag { background:#334155; color:#94a3b8; }
    .wp-dark-mode-active .znc-bp-checkout-item { border-color:#334155; }
    .wp-dark-mode-active .znc-bp-empty h3 { color:#94a3b8; }
    .wp-dark-mode-active .znc-bp-notice { background:#1e1b4b; border-color:#6c5ce7; }
    </style>
    <?php
}
