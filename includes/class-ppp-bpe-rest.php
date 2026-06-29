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
		$license = PPP_BPE_Plugin::instance()->get_license();

		if ( null !== $license && $license->is_quote_limit_reached() ) {
			$usage = $license->get_quote_usage_summary();
			return new WP_REST_Response(
				array(
					'errors'        => array( __( 'Monthly quote limit reached. Please upgrade your plan for unlimited quotes.', 'printpricepro-bpe' ) ),
					'limit_reached' => true,
					'usage'         => $usage,
				),
				429
			);
		}

		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$mode    = $options['mode'] ?? 'local';

		if ( 'api' === $mode || 'federated_node' === $mode ) {
			$response = $this->calculate_via_api( $request, $options );
		} else {
			$response = $this->calculate_local( $request );
		}

		if ( 200 === $response->get_status() && null !== $license ) {
			$license->record_event( 'quote_calculated' );

			$data                  = $response->get_data();
			$data['show_branding'] = ! $license->can_remove_branding();
			$data['usage']         = $license->get_quote_usage_summary();
			$response->set_data( $data );
		}

		return $response;
	}

	private function calculate_local( WP_REST_Request $request ): WP_REST_Response {
		$calculator = new PPP_BPE_Calculator();
		$validated  = $calculator->validate_specs( $request->get_params() );

		if ( is_wp_error( $validated ) ) {
			return new WP_REST_Response(
				array( 'errors' => $validated->get_error_messages() ),
				400
			);
		}

		$result = $calculator->calculate( $validated );

		$result['offer_signature'] = PPP_BPE_Offer_Signer::sign( $result );
		$result['source']          = 'local';

		return new WP_REST_Response( $result, 200 );
	}

	private function calculate_via_api( WP_REST_Request $request, array $options ): WP_REST_Response {
		$api_url     = $options['bpe_api_url'] ?? '';
		$license_key = $options['license_key'] ?? '';
		$tenant_id   = $options['tenant_id'] ?? '';

		if ( '' === $api_url ) {
			$this->log( 'API mode active but no BPE API URL configured. Falling back to local.' );
			return $this->calculate_local( $request );
		}

		$calculator = new PPP_BPE_Calculator();
		$validated  = $calculator->validate_specs( $request->get_params() );

		if ( is_wp_error( $validated ) ) {
			return new WP_REST_Response(
				array( 'errors' => $validated->get_error_messages() ),
				400
			);
		}

		$client = new PPP_BPE_Api_Client( $api_url, $license_key, $tenant_id );
		$result = $client->calculate( $validated );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();

			if ( ! empty( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
				return new WP_REST_Response(
					array( 'errors' => array_map( 'sanitize_text_field', $error_data['errors'] ) ),
					400
				);
			}

			$this->log( 'API call failed: ' . $result->get_error_message() . '. Falling back to local calculator.' );
			$local_result   = $this->calculate_local( $request );
			$data           = $local_result->get_data();
			$data['source'] = 'local_fallback';
			$local_result->set_data( $data );
			return $local_result;
		}

		return new WP_REST_Response( $result, 200 );
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
				'os_connection' => 'federated_node' === $mode,
				'control_plane' => PPP_BPE_Control_Plane::is_enabled(),
				'preflight'     => PPP_BPE_Preflight::is_enabled(),
				'marketplace'   => false,
			),
			'license'            => array(
				'plan'   => $this->get_license_plan(),
				'active' => $this->is_license_active(),
			),
			'timestamp'          => current_time( 'c' ),
		);

		if ( 'api' === $mode || 'federated_node' === $mode ) {
			$api_url                = $options['bpe_api_url'] ?? '';
			$data['api_configured'] = '' !== $api_url;
		}

		return new WP_REST_Response( $data, 200 );
	}

	private function get_license_plan(): string {
		$license = PPP_BPE_Plugin::instance()->get_license();
		return null !== $license ? $license->get_plan() : PPP_BPE_License::PLAN_FREE;
	}

	private function is_license_active(): bool {
		$license = PPP_BPE_Plugin::instance()->get_license();
		return null !== $license && $license->is_active();
	}

	private function log( string $message ): void {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );

		if ( empty( $options['debug_mode'] ) ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'printpricepro-bpe' ) );
		}
	}
}
