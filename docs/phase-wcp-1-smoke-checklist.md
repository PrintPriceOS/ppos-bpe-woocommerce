# Phase WCP-1 Smoke Test Checklist

## 1. Activation without WooCommerce
- [ ] Deactivate WooCommerce, then activate PrintPricePro BPE
- [ ] No fatal error occurs
- [ ] Admin notice appears: "PrintPricePro BPE for WooCommerce requires WooCommerce 8.0 or later."
- [ ] Plugin remains active but inert

## 2. Activation with WooCommerce
- [ ] Activate WooCommerce, then activate PrintPricePro BPE
- [ ] No fatal error occurs
- [ ] Product "PrintPricePro Custom Book Order" created (status: private, visibility: hidden)
- [ ] `wp_options` row `ppp_bpe_base_product_id` contains valid product ID

## 3. Re-activation (idempotency)
- [ ] Deactivate and reactivate the plugin
- [ ] Same product ID reused, no duplicate product created

## 4. Admin menu
- [ ] "PrintPricePro" top-level menu visible in wp-admin
- [ ] Sub-items: Settings, Pricing Rules, Orders, Join PrintPrice OS
- [ ] Menu only visible to users with `manage_woocommerce` capability

## 5. Settings page
- [ ] Navigate to PrintPricePro > Settings
- [ ] Form displays 8 fields with defaults (mode=local, currency=EUR, country=ES, debug=off)
- [ ] Change values, save, refresh — values persist
- [ ] Invalid API URL is sanitized on save
- [ ] License key field renders as password type

## 6. Shortcode
- [ ] Create page with `[printpricepro_bpe_calculator]`
- [ ] Page renders `<div id="printpricepro-bpe-calculator">`
- [ ] Public CSS/JS loaded on that page
- [ ] Page WITHOUT shortcode does NOT load public CSS/JS

## 7. REST health endpoint
- [ ] `GET /wp-json/printpricepro/v1/health` returns 200 OK
- [ ] Response includes: plugin_version, woocommerce_active, mode, base_product_id
- [ ] All production_flags are false
- [ ] Response does NOT contain license_key, tenant_id, or node_id

## 8. Deactivation
- [ ] Deactivate plugin — no data deleted
- [ ] Product still exists
- [ ] Options still in wp_options

## 9. Admin assets
- [ ] Plugin admin pages load admin CSS/JS
- [ ] Other admin pages (e.g., Posts) do NOT load plugin admin CSS/JS

## 10. PHP compatibility
- [ ] No PHP warnings/notices/deprecations with PHP 8.1+ and WP_DEBUG=true
