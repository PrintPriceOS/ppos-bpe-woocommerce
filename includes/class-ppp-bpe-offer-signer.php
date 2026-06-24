<?php
/**
 * HMAC-based offer signing to prevent price tampering.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Offer_Signer {

	/**
	 * Sign offer data with HMAC-SHA256.
	 *
	 * @param array $data Price breakdown data to sign.
	 * @return string Hex-encoded HMAC signature.
	 */
	public static function sign( array $data ): string {
		$payload = self::build_payload( $data );
		return hash_hmac( 'sha256', $payload, self::get_signing_key() );
	}

	/**
	 * Verify that an offer signature is valid.
	 *
	 * @param array  $data      Price breakdown data.
	 * @param string $signature Signature to verify.
	 * @return bool True if the signature matches.
	 */
	public static function verify( array $data, string $signature ): bool {
		$expected = self::sign( $data );
		return hash_equals( $expected, $signature );
	}

	private static function build_payload( array $data ): string {
		$fields = array(
			'unit_price' => $data['unit_price'] ?? 0,
			'copies'     => $data['copies'] ?? 0,
			'total'      => $data['total'] ?? 0,
			'currency'   => $data['currency'] ?? 'EUR',
		);

		if ( isset( $data['specs'] ) && is_array( $data['specs'] ) ) {
			$fields['book_size']      = $data['specs']['book_size'] ?? '';
			$fields['pages']          = $data['specs']['pages'] ?? 0;
			$fields['binding']        = $data['specs']['binding'] ?? '';
			$fields['paper']          = $data['specs']['paper'] ?? '';
			$fields['interior_color'] = $data['specs']['interior_color'] ?? '';
			$fields['cover_color']    = $data['specs']['cover_color'] ?? '';
		}

		ksort( $fields );
		return wp_json_encode( $fields );
	}

	private static function get_signing_key(): string {
		return wp_salt( 'auth' ) . '|ppp_bpe_offer';
	}
}
