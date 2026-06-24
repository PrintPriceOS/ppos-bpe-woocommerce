<?php
/**
 * Plugin Name: PrintPricePro BPE for WooCommerce
 * Plugin URI:  https://printpricepro.com
 * Description: Book price calculator for WooCommerce — entry point into the PrintPricePro federated OS for small print houses.
 * Version:     0.1.0
 * Author:      PrintPricePro
 * Author URI:  https://printpricepro.com
 * Text Domain: printpricepro-bpe
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

define( 'PPP_BPE_VERSION', '0.1.0' );
define( 'PPP_BPE_PLUGIN_FILE', __FILE__ );
define( 'PPP_BPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPP_BPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Declare HPOS (High-Performance Order Storage) compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

require_once PPP_BPE_PLUGIN_DIR . 'includes/class-ppp-bpe-plugin.php';

PPP_BPE_Plugin::instance();

register_activation_hook( __FILE__, array( 'PPP_BPE_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PPP_BPE_Plugin', 'deactivate' ) );
