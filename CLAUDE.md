# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PrintPricePro BPE for WooCommerce — a WordPress/WooCommerce plugin that serves as a book price calculator and CMS entry point for small print houses into the PrintPricePro federated OS. The plugin progresses through tiers: Free calculator → Pro (custom pricing) → Connected Node (PrintPrice OS) → Marketplace Node (federated production).

## Architecture

**Entry point:** `printpricepro-bpe-woocommerce.php` — plugin header, constants, HPOS compatibility declaration, activation/deactivation hooks, loads the orchestrator.

**Orchestrator:** `PPP_BPE_Plugin` (singleton) in `includes/class-ppp-bpe-plugin.php` — checks WooCommerce dependency, loads all classes, registers shortcode `[printpricepro_bpe_calculator]`, manages conditional asset enqueueing (public assets only when shortcode is present, admin assets only on plugin pages).

**Class responsibilities:**
- `PPP_BPE_Settings` — WordPress Settings API integration. All options stored as a single serialized array in `wp_options` under key `ppp_bpe_options`. Sanitization is centralized in `sanitize_options()`.
- `PPP_BPE_Admin` — Admin menu under top-level "PrintPricePro" with capability `manage_woocommerce`. Stores page hook suffixes for conditional admin asset loading.
- `PPP_BPE_WooCommerce` — Creates/detects a base WooCommerce product ("PrintPricePro Custom Book Order", private/hidden) on activation. Idempotent — reactivation reuses existing product.
- `PPP_BPE_Calculator` — Local pricing engine. Holds book sizes, paper types, binding types, and cost constants. Provides `get_form_options()` (single source of truth for form selects/radios), `validate_specs()` (returns sanitized array or `WP_Error`), and `calculate()` (returns price breakdown). Formula: `unit_cost = (pages × paper_cost × color_multiplier) + cover + binding + setup; total = unit_cost × copies`.
- `PPP_BPE_Rest` — REST namespace `printpricepro/v1`. Health endpoint (`GET /health`) is public but excludes sensitive data. Calculate endpoint (`POST /calculate`) accepts book specs, validates server-side, returns price breakdown.

**Asset loading pattern:** Public CSS/JS are registered (not enqueued) in `wp_enqueue_scripts`, then enqueued inside the shortcode callback. This ensures assets load only on pages using the shortcode.

## Conventions

- **PHP style:** WordPress coding standards — tabs for indentation, Yoda conditions, `snake_case` for functions/variables.
- **Class prefix:** `PPP_BPE_`
- **Option/meta prefix:** `ppp_bpe_`
- **Text domain:** `printpricepro-bpe`
- **Minimum requirements:** PHP 8.1, WordPress 6.0, WooCommerce 8.0
- **Security:** License key uses `type="password"` in admin and is never sent to frontend via `wp_localize_script` or REST responses. All admin pages require `manage_woocommerce` capability. Settings API handles nonces automatically.

## Roadmap Phases

The spec document (`PrintPricePro BPE WooCommerce Plugin.md`) defines 10 phases (WCP-1 through WCP-10). WCP-1 (Plugin Skeleton) and WCP-2 (Calculator UI MVP) are complete. The calculator uses native PHP/JS (Option A) — no build toolchain, maximum theme/page-builder compatibility. The recommended MVP is WCP-1 through WCP-4 plus WCP-9.

**Shortcode attributes:** `[printpricepro_bpe_calculator product_type="paperback" mode="compact" default_copies="500" country="DE"]`. Mode accepts `full` (default) or `compact`.

**Frontend data flow:** `wp_localize_script` passes `pppBpeCalc` object (REST URL, nonce, currency, defaults, i18n strings) to `ppp-bpe-public.js`. JS POSTs to `/wp-json/printpricepro/v1/calculate`, renders price breakdown on success. Auto-recalculates on field change after first calculation (500ms debounce).
