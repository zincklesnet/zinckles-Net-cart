<?php
/**
 * Cart Renderer — Fetches product details and prepares cart for display.
 *
 * switch_to_blog() is used ONLY here, ONLY on cart/checkout pages,
 * and ONLY once per shop. Product data is cached in a transient
 * so repeat renders are instant.
 *
 * @package ZincklesNetCart
 * @since   1.5.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Renderer {

    /** @var ZNC_Global_Cart */
    private $global_cart;

    /** @var array In-memory cache for this request */
    private $enriched_cache = null;

    public function __construct( ZNC_Global_Cart $global_cart ) {
        $this->global_cart = $global_cart;
    }

    /**
     * Get the enriched cart — items grouped by shop with full product details.
     *
     * @param int|null $user_id
     * @return array [ blog_id => [ 'name', 'url', 'currency', 'items' => [...], 'subtotal' ], ... ]
     */
    public function get_enriched_cart( $user_id = null ) {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) return array();

        // Per-request cache
        if ( null !== $this->enriched_cache ) return $this->enriched_cache;

        $grouped  = $this->global_cart->get_items_by_blog( $user_id );
        $enriched = array();

        foreach ( $grouped as $blog_id => $items ) {
            $blog_details = get_blog_details( $blog_id );
            if ( ! $blog_details ) continue;

            switch_to_blog( $blog_id );

            $shop = array(
                'blog_id'  => $blog_id,
                'name'     => $blog_details->blogname,
                'url'      => $blog_details->siteurl,
                'currency' => function_exists( 'get_woocommerce_currency' )
                    ? get_woocommerce_currency() : 'USD',
                'currency_symbol' => function_exists( 'get_woocommerce_currency_symbol' )
                    ? get_woocommerce_currency_symbol() : '$',
                'items'    => array(),
                'subtotal' => 0,
            );

            foreach ( $items as $key => $item ) {
                $product = function_exists( 'wc_get_product' )
                    ? wc_get_product( $item['product_id'] ) : null;
                if ( ! $product ) continue;

                $actual = ( $item['variation_id'] && function_exists( 'wc_get_product' ) )
                    ? wc_get_product( $item['variation_id'] ) : null;
                if ( ! $actual ) $actual = $product;

                $price      = (float) $actual->get_price();
                $line_total = $price * (int) $item['quantity'];

                $image_id  = $actual->get_image_id();
                $image_url = $image_id
                    ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
                    : ( function_exists( 'wc_placeholder_img_src' )
                        ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '' );

                $shop['items'][ $key ] = array(
                    'key'          => $key,
                    'blog_id'      => $blog_id,
                    'product_id'   => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'variation'    => $item['variation'],
                    'name'         => $product->get_name(),
                    'price'        => $price,
                    'price_html'   => $actual->get_price_html(),
                    'quantity'     => $item['quantity'],
                    'line_total'   => $line_total,
                    'image'        => $image_url,
                    'permalink'    => $actual->get_permalink(),
                    'in_stock'     => $actual->is_in_stock(),
                    'max_qty'      => $actual->get_manage_stock()
                        ? $actual->get_stock_quantity() : '',
                    'weight'       => $actual->get_weight(),
                    'sku'          => $actual->get_sku(),
                );

                $shop['subtotal'] += $line_total;
            }

            restore_current_blog();

            if ( ! empty( $shop['items'] ) ) {
                $enriched[ $blog_id ] = $shop;
            }
        }

        $this->enriched_cache = $enriched;
        return $enriched;
    }

    /**
     * Get combined cart total across all shops.
     */
    public function get_cart_total( $user_id = null ) {
        $enriched = $this->get_enriched_cart( $user_id );
        $total    = 0;
        foreach ( $enriched as $shop ) {
            $total += $shop['subtotal'];
        }
        return $total;
    }

    /**
     * Get number of unique shops in cart.
     */
    public function get_shop_count( $user_id = null ) {
        $enriched = $this->get_enriched_cart( $user_id );
        return count( $enriched );
    }

    /**
     * Render the global cart HTML.
     */
    public function render_cart( $user_id = null ) {
        $user_id  = $user_id ?: get_current_user_id();
        $enriched = $this->get_enriched_cart( $user_id );
        $settings = get_site_option( 'znc_network_settings', array() );

        ob_start();

        if ( ! is_user_logged_in() ) {
            echo '<div class="znc-notice znc-notice-info">';
            echo '<p>' . esc_html__( 'Please log in to view your global cart.', 'zinckles-net-cart' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }

        if ( empty( $enriched ) ) {
            echo '<div class="znc-empty-cart">';
            echo '<div class="znc-empty-icon">&#x1F6D2;</div>';
            echo '<h3>' . esc_html__( 'Your Global Cart is Empty', 'zinckles-net-cart' ) . '</h3>';
            echo '<p>' . esc_html__( 'Browse our shops and add items to get started!', 'zinckles-net-cart' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }

        $grand_total = 0;
        $total_items = 0;

        echo '<div class="znc-global-cart" data-nonce="' . esc_attr( wp_create_nonce( 'znc_cart_nonce' ) ) . '">';

        foreach ( $enriched as $blog_id => $shop ) {
            $grand_total += $shop['subtotal'];

            echo '<div class="znc-shop-group" data-blog-id="' . esc_attr( $blog_id ) . '">';
            echo '<div class="znc-shop-header">';
            echo '<h3 class="znc-shop-name">';
            echo '<span class="znc-shop-badge">' . esc_html( $shop['name'] ) . '</span>';
            echo '<span class="znc-shop-currency">' . esc_html( $shop['currency'] ) . '</span>';
            echo '</h3>';
            echo '</div>';

            echo '<table class="znc-cart-table">';
            echo '<thead><tr>';
            echo '<th class="znc-col-image"></th>';
            echo '<th class="znc-col-product">' . esc_html__( 'Product', 'zinckles-net-cart' ) . '</th>';
            echo '<th class="znc-col-price">' . esc_html__( 'Price', 'zinckles-net-cart' ) . '</th>';
            echo '<th class="znc-col-qty">' . esc_html__( 'Qty', 'zinckles-net-cart' ) . '</th>';
            echo '<th class="znc-col-total">' . esc_html__( 'Total', 'zinckles-net-cart' ) . '</th>';
            echo '<th class="znc-col-remove"></th>';
            echo '</tr></thead><tbody>';

            foreach ( $shop['items'] as $key => $item ) {
                $total_items += $item['quantity'];
                $stock_class  = $item['in_stock'] ? '' : ' znc-out-of-stock';

                echo '<tr class="znc-cart-item' . $stock_class . '" data-item-key="' . esc_attr( $key ) . '">';

                // Image
                echo '<td class="znc-col-image">';
                if ( $item['image'] ) {
                    echo '<img src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( $item['name'] ) . '" class="znc-product-thumb">';
                }
                echo '</td>';

                // Product name + variation
                echo '<td class="znc-col-product">';
                echo '<a href="' . esc_url( $item['permalink'] ) . '" class="znc-product-name">' . esc_html( $item['name'] ) . '</a>';
                if ( ! empty( $item['variation'] ) ) {
                    echo '<div class="znc-variation-info">';
                    foreach ( $item['variation'] as $attr => $val ) {
                        $label = str_replace( array( 'attribute_pa_', 'attribute_' ), '', $attr );
                        echo '<span class="znc-var">' . esc_html( ucfirst( $label ) ) . ': ' . esc_html( $val ) . '</span> ';
                    }
                    echo '</div>';
                }
                if ( $item['sku'] ) {
                    echo '<span class="znc-sku">SKU: ' . esc_html( $item['sku'] ) . '</span>';
                }
                if ( ! $item['in_stock'] ) {
                    echo '<span class="znc-stock-warning">' . esc_html__( 'Out of stock', 'zinckles-net-cart' ) . '</span>';
                }
                echo '</td>';

                // Price
                echo '<td class="znc-col-price">' . wp_kses_post( $item['price_html'] ) . '</td>';

                // Quantity
                echo '<td class="znc-col-qty">';
                echo '<div class="znc-qty-control">';
                echo '<button class="znc-qty-btn znc-qty-minus" data-key="' . esc_attr( $key ) . '">−</button>';
                echo '<input type="number" class="znc-qty-input" value="' . esc_attr( $item['quantity'] ) . '"';
                echo ' min="1"' . ( $item['max_qty'] ? ' max="' . esc_attr( $item['max_qty'] ) . '"' : '' );
                echo ' data-key="' . esc_attr( $key ) . '">';
                echo '<button class="znc-qty-btn znc-qty-plus" data-key="' . esc_attr( $key ) . '">+</button>';
                echo '</div></td>';

                // Line total
                echo '<td class="znc-col-total">';
                echo '<span class="znc-line-total">' . esc_html( $shop['currency_symbol'] ) . number_format( $item['line_total'], 2 ) . '</span>';
                echo '</td>';

                // Remove
                echo '<td class="znc-col-remove">';
                echo '<button class="znc-remove-btn" data-key="' . esc_attr( $key ) . '" title="' . esc_attr__( 'Remove', 'zinckles-net-cart' ) . '">&times;</button>';
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '<tfoot><tr>';
            echo '<td colspan="4" class="znc-shop-subtotal-label">' . esc_html( $shop['name'] ) . ' ' . esc_html__( 'Subtotal', 'zinckles-net-cart' ) . '</td>';
            echo '<td class="znc-shop-subtotal-value">' . esc_html( $shop['currency_symbol'] ) . number_format( $shop['subtotal'], 2 ) . '</td>';
            echo '<td></td>';
            echo '</tr></tfoot>';
            echo '</table>';
            echo '</div>'; // .znc-shop-group
        }

        // Grand total + actions
        echo '<div class="znc-cart-footer">';
        echo '<div class="znc-cart-summary-row">';
        echo '<span class="znc-cart-total-label">' . esc_html__( 'Grand Total', 'zinckles-net-cart' ) . ' (' . $total_items . ' ' . esc_html__( 'items', 'zinckles-net-cart' ) . ')</span>';
        echo '<span class="znc-cart-grand-total">$' . number_format( $grand_total, 2 ) . '</span>';
        echo '</div>';

        echo '<div class="znc-cart-actions">';
        echo '<button class="znc-btn znc-btn-clear">' . esc_html__( 'Clear Cart', 'zinckles-net-cart' ) . '</button>';

        $checkout_url = '';
        if ( class_exists( 'ZNC_Checkout_Host' ) ) {
            $host = new ZNC_Checkout_Host();
            $checkout_url = $host->get_checkout_url();
        }
        if ( $checkout_url ) {
            echo '<a href="' . esc_url( $checkout_url ) . '" class="znc-btn znc-btn-checkout">';
            echo esc_html__( 'Proceed to Checkout', 'zinckles-net-cart' ) . ' &rarr;</a>';
        }
        echo '</div></div>';

        echo '</div>'; // .znc-global-cart

        return ob_get_clean();
    }

    /**
     * Invalidate per-request cache (e.g., after cart modification).
     */
    public function invalidate() {
        $this->enriched_cache = null;
    }
}
