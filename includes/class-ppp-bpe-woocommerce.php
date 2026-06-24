<?php
/**
 * WooCommerce integration — base product creation and detection.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_WooCommerce {

	public const BASE_PRODUCT_NAME = 'PrintPricePro Custom Book Order';
	public const OPTION_PRODUCT_ID = 'ppp_bpe_base_product_id';

	public static function create_base_product(): void {
		$existing_id = self::get_base_product_id();

		if ( $existing_id && wc_get_product( $existing_id ) ) {
			return;
		}

		$found_id = self::find_existing_product();
		if ( $found_id ) {
			update_option( self::OPTION_PRODUCT_ID, $found_id );
			return;
		}

		$product = new WC_Product_Simple();
		$product->set_name( self::BASE_PRODUCT_NAME );
		$product->set_status( 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_virtual( false );
		$product->set_sold_individually( false );
		$product->set_description(
			__( 'Base product for PrintPricePro book orders. Do not delete.', 'printpricepro-bpe' )
		);
		$product->save();

		update_option( self::OPTION_PRODUCT_ID, $product->get_id() );
	}

	public static function get_base_product_id(): int {
		return (int) get_option( self::OPTION_PRODUCT_ID, 0 );
	}

	private static function find_existing_product(): int {
		$products = wc_get_products(
			array(
				'limit'  => 1,
				'status' => array( 'private', 'publish', 'draft' ),
				's'      => self::BASE_PRODUCT_NAME,
			)
		);

		foreach ( $products as $product ) {
			if ( $product->get_name() === self::BASE_PRODUCT_NAME ) {
				return $product->get_id();
			}
		}

		return 0;
	}
}
