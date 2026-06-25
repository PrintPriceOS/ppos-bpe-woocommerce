# Phase WCP-10 — WordPress.org / Commercial Release Checklist

## Distribution Files

- [x] `readme.txt` — WordPress.org standard readme with description, FAQ, changelog, screenshots
- [x] `uninstall.php` — Clean uninstall handler (removes options and scheduled events)
- [x] `index.php` — Directory traversal protection (root + all subdirectories)
- [x] `assets/icon.svg` — Plugin icon source (SVG, export to 128×128 and 256×256 PNG)
- [x] `assets/banner.svg` — Plugin banner source (SVG, export to 772×250 and 1544×500 PNG)
- [x] `assets/screenshots/` — Directory for numbered screenshot PNGs
- [x] `languages/printpricepro-bpe.pot` — Translation template

## Security Review

### Input Sanitization
- [x] All Settings API inputs sanitized in `PPP_BPE_Settings::sanitize_options()`
- [x] REST API args use `sanitize_callback` and validation
- [x] File upload validates MIME type via `finfo` and extension check
- [x] File upload enforces configurable max size limit
- [x] All user inputs use `sanitize_text_field()`, `absint()`, or `esc_url_raw()`

### Output Escaping
- [x] All HTML output uses `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- [x] JavaScript data passed via `wp_json_encode()` + `wp_add_inline_script()`
- [x] No raw `echo` of user-controlled data

### Authentication & Authorization
- [x] Admin pages require `manage_woocommerce` capability
- [x] REST admin endpoints check `current_user_can('manage_woocommerce')`
- [x] File upload/download endpoints verify order ownership or admin capability
- [x] Webhook endpoints verify HMAC-SHA256 signatures

### Data Protection
- [x] License key uses `type="password"` in admin forms
- [x] License key, webhook secret, node API key never sent to frontend
- [x] License key masked in REST responses (first/last 4 chars only)
- [x] Upload directory protected with `.htaccess` deny-all
- [x] File serving goes through REST endpoint with permission check

### Price Integrity
- [x] Offer prices signed with HMAC-SHA256 (`PPP_BPE_Offer_Signer`)
- [x] Signature verified on add-to-cart and during checkout
- [x] Signing key derived from WordPress auth salt (unique per site)

### WordPress Standards
- [x] Nonces used for Settings API forms (automatic via `settings_fields()`)
- [x] REST endpoints use `wp_rest` nonce for public actions
- [x] `defined('ABSPATH') || exit;` at top of every PHP file
- [x] No direct file access possible for plugin files

### External Connections
- [x] All external API calls use `wp_remote_post()`/`wp_remote_get()` (WordPress HTTP API)
- [x] SSL verification enabled (`sslverify => true`) for all API calls
- [x] Timeouts configured on all external requests
- [x] No external calls made in local-only mode by default

## WordPress Coding Standards

- [x] Tab indentation throughout
- [x] Yoda conditions used consistently
- [x] `snake_case` function/variable names
- [x] Class prefix `PPP_BPE_`
- [x] Option/meta prefix `ppp_bpe_`
- [x] Text domain `printpricepro-bpe` consistent across all files
- [x] Proper PHPDoc blocks on all public methods
- [x] `@package PrintPricePro_BPE` header in all files

## WooCommerce Compatibility

- [x] HPOS compatibility declared via `FeaturesUtil::declare_compatibility()`
- [x] WooCommerce dependency check on `plugins_loaded`
- [x] Admin notice shown when WooCommerce is missing
- [x] Base product creation is idempotent (reactivation safe)
- [x] Order meta uses WooCommerce CRUD methods
- [x] Cart integration via proper WooCommerce hooks
- [x] No direct database queries — all through WooCommerce APIs

## PHP Compatibility

- [x] Minimum PHP 8.1 declared in plugin header
- [x] Uses PHP 8.1 features (union types, named arguments, match expressions)
- [x] No deprecated function usage

## Pre-Submission Tasks

- [ ] Export SVG icons/banners to PNG at required dimensions
- [ ] Capture 5 screenshots and save as `screenshot-1.png` through `screenshot-5.png`
- [ ] Run PHPCS with WordPress-Extra ruleset
- [ ] Test on WordPress 6.0+ and latest
- [ ] Test on WooCommerce 8.0+ and latest
- [ ] Test on PHP 8.1, 8.2, 8.3
- [ ] Test activation/deactivation/uninstall cycle
- [ ] Test with popular themes (Storefront, Astra, GeneratePress)
- [ ] Test with Elementor active
- [ ] Verify no PHP warnings/notices in error log
- [ ] Verify no JavaScript console errors
- [ ] Submit to WordPress.org plugin review team
