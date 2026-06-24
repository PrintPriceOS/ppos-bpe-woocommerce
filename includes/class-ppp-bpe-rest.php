<?php
/**
 * REST API namespace and endpoints.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Rest {

	public const NAMESPACE = 'printpricepro/v1';

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function health_check( WP_REST_Request $request ): WP_REST_Response {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$mode    = $options['mode'] ?? 'local';

		$data = array(
			'plugin_version'     => PPP_BPE_VERSION,
			'woocommerce_active' => class_exists( 'WooCommerce' ),
			'mode'               => $mode,
			'base_product_id'    => (int) get_option( PPP_BPE_WooCommerce::OPTION_PRODUCT_ID, 0 ),
			'production_flags'   => array(
				'os_connection' => false,
				'preflight'     => false,
				'marketplace'   => false,
			),
			'timestamp'          => current_time( 'c' ),
		);

		return new WP_REST_Response( $data, 200 );
	}
}
