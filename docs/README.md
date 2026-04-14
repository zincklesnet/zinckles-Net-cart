# Zinckles Net Cart — Admin README

**Version:** 1.0.0-prototype  
**Requires:** WordPress 6.0+, PHP 7.4+, WooCommerce 7.0+  
**License:** GPL-2.0-or-later  
**Network Plugin:** Yes (must be Network Activated)

---

## Overview

Zinckles Net Cart aggregates WooCommerce products from multiple subsites in a WordPress Multisite network into a unified global cart on the main site. Customers can browse and add products from any enrolled shop, then check out once — with mixed currency support, ZCred (MyCred) payments, and automatic parent/child order creation.

## Architecture

```
┌──────────────────────────────────────────────────┐
│                  MAIN SITE                        │
│  ┌─────────────┐  ┌──────────────┐               │
│  │ Global Cart  │  │  Checkout    │               │
│  │   Store      │──│ Orchestrator │               │
│  └──────┬──────┘  └──────┬───────┘               │
│         │                │                        │
│  ┌──────┴──────┐  ┌──────┴───────┐               │
│  │ Cart Merger  │  │ Order Factory│               │
│  └──────┬──────┘  └──────┬───────┘               │
│         │                │                        │
│  ┌──────┴──────┐  ┌──────┴───────┐               │
│  │  Currency   │  │  Inventory   │               │
│  │  Handler    │  │    Sync      │               │
│  └─────────────┘  └──────────────┘               │
│  ┌─────────────┐                                  │
│  │ MyCred      │                                  │
│  │ Engine      │                                  │
│  └─────────────┘                                  │
│         ▲  REST (HMAC-SHA256)                     │
└─────────┼────────────────────────────────────────┘
          │
    ┌─────┴─────┐     ┌───────────┐
    │ Subsite A │     │ Subsite B │  ...
    │ ┌───────┐ │     │ ┌───────┐ │
    │ │Snapshot│ │     │ │Snapshot│ │
    │ │Builder │ │     │ │Builder │ │
    │ └───────┘ │     │ └───────┘ │
    │ ┌───────┐ │     │ ┌───────┐ │
    │ │ Shop  │ │     │ │ Shop  │ │
    │ │Settings│ │     │ │Settings│ │
    │ └───────┘ │     │ └───────┘ │
    └───────────┘     └───────────┘
```

## Module Reference

| Module | File | Role |
|--------|------|------|
| Autoloader | `includes/class-znc-autoloader.php` | PSR-4-style class loading |
| Activator | `includes/class-znc-activator.php` | DB tables, REST secret, cron scheduling |
| Deactivator | `includes/class-znc-deactivator.php` | Cron cleanup |
| REST Auth | `includes/class-znc-rest-auth.php` | HMAC-SHA256 signing & verification |
| REST Endpoints | `includes/class-znc-rest-endpoints.php` | 10 REST routes |
| Cart Snapshot | `includes/class-znc-cart-snapshot.php` | Subsite cart serialization & auto-push |
| Shop Settings | `includes/class-znc-shop-settings.php` | Subsite configuration provider |
| Global Cart Store | `includes/class-znc-global-cart-store.php` | Custom DB table CRUD |
| Global Cart Merger | `includes/class-znc-global-cart-merger.php` | Add items + cart refresh |
| Currency Handler | `includes/class-znc-currency-handler.php` | Mixed currency + parallel totals |
| Checkout Orchestrator | `includes/class-znc-checkout-orchestrator.php` | 10-step checkout sequence |
| MyCred Engine | `includes/class-znc-mycred-engine.php` | ZCred balance/deduct/refund |
| Order Factory | `includes/class-znc-order-factory.php` | Parent + child orders |
| Inventory Sync | `includes/class-znc-inventory-sync.php` | Stock sync + retry queue |
| Network Settings | `includes/class-znc-network-settings.php` | Network admin settings |
| Main Admin | `admin/class-znc-main-admin.php` | Main site admin pages |
| Subsite Admin | `admin/class-znc-subsite-admin.php` | Per-subsite admin pages |
| Admin Loader | `admin/class-znc-admin-loader.php` | Settings → module bridge |

## REST Endpoints

| Endpoint | Method | Location | Auth | Purpose |
|----------|--------|----------|------|---------|
| `/znc/v1/cart-snapshot` | GET | Subsite | HMAC | Get user's cart snapshot |
| `/znc/v1/shop-settings` | GET | Subsite | HMAC | Get shop configuration |
| `/znc/v1/pricing/validate` | POST | Subsite | HMAC | Validate prices & stock |
| `/znc/v1/inventory/deduct` | POST | Subsite | HMAC | Deduct stock |
| `/znc/v1/inventory/restore` | POST | Subsite | HMAC | Restore stock (rollback) |
| `/znc/v1/orders/create-child` | POST | Subsite | HMAC | Create child order |
| `/znc/v1/global-cart` | GET | Main | Login | Get user's global cart |
| `/znc/v1/global-cart/add` | POST | Main | HMAC/Login | Add item to global cart |
| `/znc/v1/global-cart/remove` | POST | Main | Login | Remove item from cart |
| `/znc/v1/checkout` | POST | Main | Login | Process checkout |

## Admin Pages

### Network Admin (Super Admin)
- **Settings** — Enrollment mode, currency, ZCreds, cart limits, validation, logging
- **Subsites** — Enroll/remove sites, connection testing, WC/MyCred detection
- **Security** — HMAC secret management, clock skew, rate limiting, IP whitelist
- **Diagnostics** — Global stats, retry queue status, connection tests

### Main Site Admin
- **Settings** — Page assignments, order limits, account requirements
- **Cart Display** — Layout style, badges, currency breakdown, ZCred widget
- **Checkout** — Steps display, price/stock change actions, coupons, shipping
- **Currency & ZCreds** — Exchange rates (manual/API), ZCred checkout config
- **Orders** — Prefixes, statuses, parent→child mapping
- **Cart Browser** — View/clear any user's cart
- **Notifications** — Email recipients, Slack integration
- **Performance** — Caching, async operations

### Per-Subsite Admin (Shop Owners)
- **Dashboard** — Enrollment status, prerequisites, snapshot preview
- **Products** — Selection mode, category/tag exclusions, price thresholds
- **Shipping & Tax** — Mode overrides, flat rates, tax exemptions
- **ZCreds** — Accept/decline, max %, earn multiplier
- **Branding** — Display name, tagline, badge color, shop icon
- **Stock** — Reservation, low stock threshold, coupon availability

## Hooks & Filters

| Hook | Type | Description |
|------|------|-------------|
| `znc_cart_snapshot_enabled` | Filter | Check if snapshot is enabled for current subsite |
| `znc_product_eligible` | Filter | Apply product eligibility rules |
| `znc_checkout_config` | Filter | Provide checkout configuration |
| `znc_currency_rates` | Filter | Provide exchange rates |
| `znc_mycred_config` | Filter | Provide MyCred configuration |
| `znc_shipping_config` | Filter | Per-subsite shipping overrides |
| `znc_tax_config` | Filter | Per-subsite tax overrides |
| `znc_shop_display` | Filter | Shop branding for cart display |
| `znc_checkout_completed` | Action | Fired after successful checkout |
| `znc_zcreds_deducted` | Action | Fired after ZCred deduction |
| `znc_zcreds_refunded` | Action | Fired after ZCred refund |
| `znc_zcreds_awarded` | Action | Fired after purchase rewards |
| `znc_inventory_sync_failed` | Action | Fired when retry queue exhausted |

## Shortcodes

| Shortcode | Page | Description |
|-----------|------|-------------|
| `[znc_global_cart]` | Cart page | Displays the unified global cart |
| `[znc_checkout]` | Checkout page | Displays the checkout form |

## Database Tables

| Table | Purpose |
|-------|---------|
| `wp_znc_global_cart` | Stores cart items per user with expiry |
| `wp_znc_order_map` | Maps parent orders to child orders |
| `wp_znc_inventory_retry` | Queue for failed inventory sync operations |
| `wp_znc_enrolled_sites` | Tracks which subsites are enrolled |

## Troubleshooting

| Issue | Check |
|-------|-------|
| Cart items not appearing | Verify subsite is enrolled; check REST connectivity in Diagnostics |
| HMAC auth failures | Verify shared secret matches across network; check server clock sync |
| Inventory not syncing | Check retry queue in Diagnostics; verify subsite REST endpoint accessible |
| ZCreds not working | Verify MyCred is active on subsite; check exchange rate is set |
| Mixed currency errors | Ensure exchange rates are configured for all currencies in use |
| Checkout failing | Check log level=debug temporarily; review checkout config actions |

## Running Tests

```bash
# Install WP test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/test-currency-handler.php
```
