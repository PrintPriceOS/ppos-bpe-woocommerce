=== PrintPricePro BPE for WooCommerce ===
Contributors: printpricepro
Tags: book printing, price calculator, woocommerce, print shop, book production
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.6
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional book price calculator for WooCommerce. Turn your print shop into a connected PrintPricePro node.

== Description ==

**PrintPricePro BPE for WooCommerce** is a professional book price calculator that integrates seamlessly with WooCommerce. Designed for small print houses, it lets your customers configure book specifications — size, pages, paper, binding, color — and get instant price estimates. Calculated quotes convert directly into WooCommerce orders.

= Start with a calculator. Evolve into a federated node. =

This plugin is the entry point into the PrintPricePro federated operating system for print production. Begin with a simple, self-hosted calculator and progressively unlock advanced features:

* **Free** — Basic calculator with branding. Up to 50 quotes/month.
* **Pro Calculator** — Custom pricing rules, WooCommerce checkout, unlimited quotes, no branding.
* **Preflight Add-on** — PDF upload and automated file validation before production.
* **Connected Node** — Sync orders, files, and production status with PrintPrice OS.
* **Marketplace Node** — Receive external orders from the PrintPrice federated network.

= Key Features =

* Book price calculator via shortcode `[printpricepro_bpe_calculator]`
* Supports multiple book sizes (A4, A5, Letter, Digest, Pocket)
* Paper types, binding options, color modes
* Server-side price calculation with signed offers (tamper-proof)
* WooCommerce cart and checkout integration
* PDF file upload for interior and cover
* Optional Preflight file validation
* Control Plane connection for federated production
* Production queue for print house workflow
* License-based feature gating and usage metering
* REST API with health endpoint
* HPOS (High-Performance Order Storage) compatible
* Fully translatable (i18n ready)

= Shortcode Usage =

`[printpricepro_bpe_calculator]`

With attributes:

`[printpricepro_bpe_calculator product_type="paperback" mode="compact" default_copies="500" country="DE"]`

= Requirements =

* WordPress 6.0 or later
* WooCommerce 8.0 or later
* PHP 8.1 or later

== Installation ==

1. Upload the `printpricepro-bpe-woocommerce` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure **WooCommerce** is installed and activated.
4. Go to **PrintPricePro > Settings** to configure mode and options.
5. Add the shortcode `[printpricepro_bpe_calculator]` to any page or post.

The plugin automatically creates a hidden WooCommerce product ("PrintPricePro Custom Book Order") used as the base for all calculated book orders. Do not delete this product.

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No. WooCommerce 8.0 or later is required. The plugin will display an admin notice if WooCommerce is not active.

= Can I use the calculator without a license? =

Yes. The Free plan includes a basic calculator with up to 50 quotes per month and PrintPricePro branding. No license key is needed for the Free plan.

= How is the price calculated? =

Prices are calculated server-side based on book specifications (pages, paper type, binding, color mode, copies). In Local mode, the plugin uses its built-in pricing engine. In API mode, prices come from the PrintPricePro BPE service with signed offers to prevent tampering.

= Is the price secure? =

Yes. All calculated prices are cryptographically signed. The signature is verified when adding to cart and during checkout, so prices cannot be manipulated from the browser.

= What is a "Federated Node"? =

A Federated Node is a print house connected to the PrintPrice OS. This enables order synchronization, advanced preflight, production queue management, and participation in the PrintPrice marketplace network.

= Does the plugin support HPOS? =

Yes. The plugin declares full compatibility with WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables).

= Where are uploaded PDFs stored? =

PDFs are stored in a protected directory under `wp-content/uploads/ppp-bpe-files/`. Direct URL access is blocked via `.htaccess`. Files are served through WordPress with permission checks.

== Screenshots ==

1. Book price calculator form on the frontend.
2. Price calculation results with cost breakdown.
3. Plugin settings page in WordPress admin.
4. Production queue management for print houses.
5. License and plan management page.

== External Services ==

This plugin can connect to the PrintPricePro API (https://printpricepro.com) when
configured by the site administrator. All connections require an explicit API URL
to be entered in **PrintPricePro > Settings**. No data is sent in default local mode.

Depending on the features you enable, the following data may be transmitted:

* **License activation / verification** — sends your license key and site URL to
  verify your subscription. Triggered on manual activation and via a daily cron job.
* **Price calculation (API mode)** — sends book specifications (size, pages, paper,
  binding, color, quantity) to receive a signed price quote.
* **PDF Preflight** — sends uploaded PDF files to the Preflight API for automated
  file validation before production.
* **Control Plane sync (Connected Node mode)** — sends order details, production
  status, and uploaded files to the PrintPrice OS control plane.

Privacy policy: https://printpricepro.com/privacy
Terms of service: https://printpricepro.com/terms

== Privacy Policy ==

This plugin does not collect or transmit any data by default. When the administrator
configures an API URL and enables optional features (API pricing, Preflight, or
Control Plane), the plugin transmits data to the configured endpoint as described
in the External Services section above.

Uploaded PDF files are stored in `wp-content/uploads/ppp-bpe-files/` on your server
and are deleted when the plugin is uninstalled. Order metadata (book specifications
attached to WooCommerce orders) is retained after uninstall to preserve order history.

For full details on how PrintPricePro handles data, see:
https://printpricepro.com/privacy

== Changelog ==

= 0.1.0 =
* Initial release.
* Book price calculator with shortcode support.
* Local pricing engine with multiple book sizes, paper types, and bindings.
* WooCommerce cart and checkout integration with signed offers.
* REST API with health and calculate endpoints.
* PDF file upload for interior and cover files.
* Preflight bridge for file validation.
* Control Plane connection for federated node mode.
* Production queue with status workflow.
* License activation, feature gating, and usage metering.
* HPOS compatibility.
* Full i18n support.

== Upgrade Notice ==

= 0.1.0 =
Initial release. Install and activate to start using the book price calculator.
