<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WordPress admin.
 *
 * @package PrintPricePro_BPE
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Options.
delete_option( 'ppp_bpe_options' );
delete_option( 'ppp_bpe_base_product_id' );
delete_option( 'ppp_bpe_license_data' );
delete_option( 'ppp_bpe_usage_data' );

// Scheduled events.
wp_clear_scheduled_hook( 'ppp_bpe_daily_license_check' );

// Order meta and item meta are intentionally preserved so that
// existing WooCommerce orders retain their print specification
// history even after the plugin is removed.
