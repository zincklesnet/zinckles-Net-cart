# Zinckles Net Cart — Demo Video Script

**Duration:** ~5 minutes  
**Format:** Screen recording with voiceover  
**Resolution:** 1920×1080  

---

## Scene 1: Introduction (0:00–0:30)

**Screen:** Zinckles.com landing page or logo  
**Voiceover:**  
"Welcome to Zinckles Net Cart — a unified multisite shopping experience that lets your customers browse products from multiple WooCommerce shops and check out in one place. Today I'll walk you through the complete flow, from adding items across shops to completing a multi-shop, multi-currency checkout with ZCred rewards."

---

## Scene 2: Network Admin Setup (0:30–1:00)

**Screen:** Network Admin → Net Cart → Settings  
**Voiceover:**  
"In the Network Admin, we configure the foundation — enrollment mode, base currency, ZCred exchange rate, and cart limits. Here we've set opt-in enrollment, USD as base currency, and a ZCred rate of one cent per point."

**Action:** Scroll through settings, show subsites page with two enrolled shops.

---

## Scene 3: Subsite Configuration (1:00–1:45)

**Screen:** Subsite A → Net Cart → Dashboard, then Products  
**Voiceover:**  
"Each shop owner controls their own participation. On the dashboard, they see enrollment status and prerequisites at a glance. In Products, they choose which items to share with Net Cart — all products, a whitelist, or exclusions by category."

**Action:** Show dashboard with green checkmarks, switch to Products page, show branding page with badge color picker.

---

## Scene 4: Adding to Cart from Subsite A (1:45–2:15)

**Screen:** Subsite A storefront → product page → add to cart  
**Voiceover:**  
"Now let's shop. On Subsite A — our Portland Goods store — I'll add this handmade mug to my cart. Behind the scenes, Net Cart instantly pushes this item to the global cart on the main site via a signed REST request."

**Action:** Click "Add to Cart" on a product, show the admin bar Net Cart icon updating.

---

## Scene 5: Adding from Subsite B (Different Currency) (2:15–2:45)

**Screen:** Subsite B storefront → product page → add to cart  
**Voiceover:**  
"Now I'll switch to Subsite B — our Euro-based shop. I'll add this artisan notebook priced in Euros. Net Cart handles the currency difference automatically, showing both the original price and the converted amount."

**Action:** Add a EUR-priced product, navigate to main site global cart.

---

## Scene 6: Global Cart on Main Site (2:45–3:30)

**Screen:** Main site → Global Cart page (shortcode)  
**Voiceover:**  
"Here's the magic — the global cart on our main site. Items are grouped by shop with colored badges. You can see the Portland Goods mug in USD and the Euro shop notebook with both original and converted prices. The currency breakdown shows per-currency subtotals and a unified total. And look — the ZCred widget shows my balance and how much I can apply."

**Action:** Point out shop badges, currency breakdown, ZCred widget, quantity controls.

---

## Scene 7: Checkout Flow (3:30–4:30)

**Screen:** Main site → Checkout page  
**Voiceover:**  
"Clicking 'Proceed to Checkout' takes us to our three-step flow. Step one: order review with all items, their shops, and the ZCred slider. I'll apply 500 ZCreds — that's five dollars off my total. Step two: billing details and payment method. Step three: final confirmation. When I click Place Order, the orchestrator validates prices and stock with each subsite in real-time, deducts my ZCreds, creates the parent order here on the main site, creates child orders on each subsite, and syncs inventory — all in one atomic transaction."

**Action:** Use ZCred slider, fill billing, click Place Order, show success message.

---

## Scene 8: Admin Verification & Wrap-Up (4:30–5:00)

**Screen:** Main site → WooCommerce → Orders → Net Cart parent order  
**Voiceover:**  
"In WooCommerce Orders, we can see the parent order with line items tagged by origin site. In Net Cart → Orders, the order map shows the parent-to-child relationship. Each subsite now has its own child order with decremented stock. That's Zinckles Net Cart — unified multisite commerce, mixed currencies, ZCred rewards, and complete admin control. Thanks for watching!"

**Action:** Show parent order details, order map lookup, briefly show child order on subsite.

---

## Production Notes

- Use a clean browser with no extensions visible
- Set browser zoom to 100%
- Use a calm, professional voiceover pace
- Add subtle transition effects between scenes
- Include lower-third labels for each admin page shown
- Background music: light, upbeat, non-distracting
- End card: Zinckles logo + "zinckles.com/net-cart"
