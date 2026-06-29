<?php
/**
 * HTTP client for the external PrintPricePro BPE API.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Api_Client {

	private const TIMEOUT_SECONDS = 15;
	private const CONNECT_TIMEOUT = 5;

	private string $api_url;
	private string $license_key;
	private string $tenant_id;

	public function __construct( string $api_url, string $license_key, string $tenant_id = '' ) {
		$this->api_url     = untrailingslashit( $api_url );
		$this->license_key = $license_key;
		$this->tenant_id   = $tenant_id;
	}

	/**
	 * Send book specs to the BPE API and return a signed offer.
	 *
	 * @param array $specs Validated book specifications.
	 * @return array|WP_Error Price breakdown with offer signature, or WP_Error on failure.
	 */
	public function calculate( array $specs ): array|WP_Error {
		$endpoint = $this->api_url . '/api/budget/calculate';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => self::TIMEOUT_SECONDS,
				'redirection' => 0,
				'headers'     => $this->get_headers(),
				'body'        => wp_json_encode( $specs ),
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'API request failed: ' . $response->get_error_message() );
			return new WP_Error(
				'ppp_bpe_api_connection',
				__( 'Could not connect to the pricing service. Please try again later.', 'printpricepro-bpe' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$this->log( sprintf( 'API returned HTTP %d: %s', $code, $body ) );

			$message = __( 'The pricing service returned an error. Please try again later.', 'printpricepro-bpe' );
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$message = sanitize_text_field( $data['message'] );
			}
			if ( is_array( $data ) && ! empty( $data['errors'] ) ) {
				return new WP_Error( 'ppp_bpe_api_validation', $message, array( 'errors' => $data['errors'] ) );
			}

			return new WP_Error( 'ppp_bpe_api_error', $message );
		}

		if ( ! is_array( $data ) || ! isset( $data['total'] ) ) {
			$this->log( 'API returned invalid response structure.' );
			return new WP_Error(
				'ppp_bpe_api_invalid',
				__( 'Invalid response from the pricing service.', 'printpricepro-bpe' )
			);
		}

		if ( ! isset( $data['offer_signature'] ) ) {
			$data['offer_signature'] = $this->sign_offer( $data );
		}

		$data['source'] = 'api';

		return $data;
	}

	/**
	 * Test connectivity and authentication with the BPE API.
	 *
	 * @return array|WP_Error API health info or WP_Error.
	 */
	public function health_check(): array|WP_Error {
		$endpoint = $this->api_url . '/api/health';

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'   => self::CONNECT_TIMEOUT,
				'headers'   => $this->get_headers(),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ppp_bpe_api_connection',
				__( 'Could not connect to the BPE API.', 'printpricepro-bpe' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return new WP_Error(
				'ppp_bpe_api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'BPE API returned HTTP %d.', 'printpricepro-bpe' ),
					$code
				)
			);
		}

		return is_array( $body ) ? $body : array();
	}

	private function get_headers(): array {
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => 'PrintPricePro-BPE-WooCommerce/' . PPP_BPE_VERSION,
		);

		if ( '' !== $this->license_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->license_key;
		}

		if ( '' !== $this->tenant_id ) {
			$headers['X-PPP-Tenant-ID'] = $this->tenant_id;
		}

		return $headers;
	}

	/**
	 * Generate HMAC signature for an offer to prevent price tampering.
	 */
	private function sign_offer( array $offer_data ): string {
		return PPP_BPE_Offer_Signer::sign( $offer_data );
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
