# Zinckles Net Cart — Deployment Checklist

## Pre-Deployment Requirements

- [ ] WordPress Multisite network configured and operational
- [ ] WooCommerce installed and activated on all participating subsites
- [ ] MyCred (optional) installed on subsites that will accept ZCred payments
- [ ] PHP 7.4+ on all sites
- [ ] WordPress 6.0+ across the network
- [ ] WooCommerce 7.0+ on all subsites
- [ ] SSL certificates active on all sites (required for secure REST communication)
- [ ] REST API enabled and accessible on all sites

## Installation

1. [ ] Upload `zinckles-net-cart/` folder to `wp-content/plugins/`
2. [ ] Navigate to Network Admin → Plugins
3. [ ] Click **Network Activate** for Zinckles Net Cart
4. [ ] Verify activation completes without errors
5. [ ] Check that database tables were created:
   - `wp_znc_global_cart`
   - `wp_znc_order_map`
   - `wp_znc_inventory_retry`
   - `wp_znc_enrolled_sites`

## Configuration

6. [ ] Go to Network Admin → Net Cart → Settings
   - Set enrollment mode (opt-in recommended for prototype)
   - Set base currency
   - Configure ZCred exchange rate and max percentage
   - Set cart expiry, max items, max shops
7. [ ] Go to Network Admin → Net Cart → Security
   - Verify shared secret was generated
   - Set clock skew tolerance (300s default)
8. [ ] Go to Network Admin → Net Cart → Subsites
   - Enroll at least 2 subsites for testing
   - Run connection test on each enrolled site
9. [ ] On the main site, go to Net Cart → Settings
   - Create pages with shortcodes `[znc_global_cart]` and `[znc_checkout]`
   - Assign pages in settings
   - Configure checkout options
10. [ ] On the main site, go to Net Cart → Currency & ZCreds
    - Set exchange rates (manual or API)
    - Enable/disable ZCred checkout
11. [ ] On each enrolled subsite, go to Net Cart → Dashboard
    - Verify prerequisites are met
    - Configure product selection mode
    - Set shipping/tax overrides if needed
    - Configure branding (display name, badge color, icon)

## Testing

12. [ ] Add products to cart on Subsite A → verify they appear in global cart on main site
13. [ ] Add products to cart on Subsite B → verify mixed-shop cart displays correctly
14. [ ] Test mixed currency cart (if applicable)
15. [ ] Complete a checkout → verify parent order on main site + child orders on subsites
16. [ ] Test ZCred payment (if MyCred active)
17. [ ] Test inventory sync (verify stock decremented on subsites)
18. [ ] Run PHPUnit test suite: `vendor/bin/phpunit`

## 3-Week Checkpoint Milestones

### Week 1: Foundation
- Plugin installed and network-activated
- Database tables created
- 2+ subsites enrolled
- Add-to-cart flow working from subsites to global cart
- REST authentication verified

### Week 2: Checkout & Payments
- Full checkout flow operational
- Parent + child orders created correctly
- Mixed currency handling working
- ZCred integration tested
- Inventory sync confirmed

### Week 3: Polish & Production Prep
- Admin controls configured and tested
- All automated tests passing
- Edge cases handled (price changes, stock changes, network errors)
- Notifications configured
- Performance settings tuned
- Documentation reviewed
- Demo walkthrough completed

## Production Notes

- Change `ZNC_VERSION` from `1.0.0-prototype` before go-live
- Review and harden REST authentication for production
- Set up monitoring for the inventory retry queue
- Configure Slack notifications for order alerts
- Set log level to `error` for production (not `debug`)
- Review cart expiry settings for your use case
- Test with real payment gateways before accepting live orders
