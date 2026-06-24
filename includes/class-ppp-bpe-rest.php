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

		register_rest_route(
			self::NAMESPACE,
			'/calculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calculate' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_calculate_args(),
			)
		);
	}

	public function calculate( WP_REST_Request $request ): WP_REST_Response {
		$calculator = new PPP_BPE_Calculator();
		$validated  = $calculator->validate_specs( $request->get_params() );

		if ( is_wp_error( $validated ) ) {
			return new WP_REST_Response(
				array( 'errors' => $validated->get_error_messages() ),
				400
			);
		}

		return new WP_REST_Response( $calculator->calculate( $validated ), 200 );
	}

	private function get_calculate_args(): array {
		return array(
			'book_size'      => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'pages'          => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 8,
				'maximum'  => 1000,
			),
			'copies'         => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
				'maximum'  => 10000,
			),
			'interior_color' => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array( 'bw', 'color' ),
			),
			'cover_color'    => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array( 'bw', 'color' ),
			),
			'binding'        => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'paper'          => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'country'        => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'ES',
				'sanitize_callback' => 'sanitize_text_field',
			),
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
