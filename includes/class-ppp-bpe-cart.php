<?php
/**
 * WooCommerce Cart / Checkout / Order integration.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Cart {

	private const META_PREFIX     = 'ppp_bpe_';
	private const META_PREFIX_INT = '_ppp_bpe_';

	private const SPEC_FIELDS = array(
		'book_size',
		'pages',
		'copies',
		'interior_color',
		'cover_color',
		'binding',
		'paper',
		'country',
	);

	private const PRICE_FIELDS = array(
		'unit_price',
		'total',
		'currency',
	);

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_filter( 'woocommerce_is_purchasable', array( $this, 'make_base_product_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'display_admin_order_item_meta' ), 10, 3 );
	}

	/**
	 * @param bool       $purchasable Whether the product is purchasable.
	 * @param WC_Product $product     Product object.
	 * @return bool
	 */
	public function make_base_product_purchasable( bool $purchasable, WC_Product $product ): bool {
		if ( $product->get_id() === PPP_BPE_WooCommerce::get_base_product_id() ) {
			return true;
		}

		return $purchasable;
	}

	public function register_routes(): void {
		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/add-to-cart',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_add_to_cart' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'offer'     => array(
						'type'     => 'object',
						'required' => true,
					),
					'signature' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function handle_add_to_cart( WP_REST_Request $request ): WP_REST_Response {
		$offer     = $request->get_param( 'offer' );
		$signature = $request->get_param( 'signature' );

		if ( ! is_array( $offer ) || empty( $signature ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Invalid offer data.', 'printpricepro-bpe' ) ),
				400
			);
		}

		if ( ! PPP_BPE_Offer_Signer::verify( $offer, $signature ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Offer signature verification failed. Please recalculate your price.', 'printpricepro-bpe' ) ),
				403
			);
		}

		$product_id = PPP_BPE_WooCommerce::get_base_product_id();
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Base product not found. Please contact the administrator.', 'printpricepro-bpe' ) ),
				500
			);
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		$cart_item_data = array(
			'ppp_bpe_offer'     => $this->sanitize_offer( $offer ),
			'ppp_bpe_signature' => $signature,
		);

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

		if ( ! $cart_item_key ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Could not add item to cart.', 'printpricepro-bpe' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'cart_url' => wc_get_cart_url(),
				'message'  => __( 'Book added to cart.', 'printpricepro-bpe' ),
			),
			200
		);
	}

	/**
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id    Product ID.
	 * @return array
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id ): array {
		if ( ! isset( $cart_item_data['ppp_bpe_offer'] ) ) {
			return $cart_item_data;
		}

		$cart_item_data['unique_key'] = md5(
			wp_json_encode( $cart_item_data['ppp_bpe_offer'] ) . microtime()
		);

		return $cart_item_data;
	}

	public function set_cart_item_price( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['ppp_bpe_offer'] ) ) {
				continue;
			}

			$offer     = $cart_item['ppp_bpe_offer'];
			$signature = $cart_item['ppp_bpe_signature'] ?? '';

			if ( ! PPP_BPE_Offer_Signer::verify( $offer, $signature ) ) {
				continue;
			}

			$total = floatval( $offer['total'] ?? 0 );
			if ( $total > 0 ) {
				$cart_item['data']->set_price( $total );
			}
		}
	}

	/**
	 * @param array $item_data Display data for cart item.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( ! isset( $cart_item['ppp_bpe_offer']['specs'] ) ) {
			return $item_data;
		}

		$specs  = $cart_item['ppp_bpe_offer']['specs'];
		$labels = $this->get_spec_labels();

		foreach ( $labels as $key => $label ) {
			if ( ! empty( $specs[ $key ] ) ) {
				$item_data[] = array(
					'name'  => $label,
					'value' => $this->format_spec_value( $key, $specs[ $key ] ),
				);
			}
		}

		$offer = $cart_item['ppp_bpe_offer'];
		if ( ! empty( $offer['unit_price'] ) && ( $offer['copies'] ?? 0 ) > 1 ) {
			$item_data[] = array(
				'name'  => __( 'Unit Price', 'printpricepro-bpe' ),
				'value' => wc_price( $offer['unit_price'] ),
			);
		}

		return $item_data;
	}

	/**
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 */
	public function save_order_item_meta( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		if ( ! isset( $values['ppp_bpe_offer'] ) ) {
			return;
		}

		$offer = $values['ppp_bpe_offer'];

		if ( isset( $offer['specs'] ) && is_array( $offer['specs'] ) ) {
			foreach ( self::SPEC_FIELDS as $field ) {
				if ( isset( $offer['specs'][ $field ] ) ) {
					$item->add_meta_data( self::META_PREFIX . $field, sanitize_text_field( $offer['specs'][ $field ] ), true );
				}
			}
		}

		foreach ( self::PRICE_FIELDS as $field ) {
			if ( isset( $offer[ $field ] ) ) {
				$item->add_meta_data( self::META_PREFIX . $field, sanitize_text_field( $offer[ $field ] ), true );
			}
		}

		if ( isset( $offer['breakdown'] ) && is_array( $offer['breakdown'] ) ) {
			$item->add_meta_data( self::META_PREFIX_INT . 'breakdown', array_map( 'floatval', $offer['breakdown'] ), true );
		}

		$item->add_meta_data( self::META_PREFIX_INT . 'signature', sanitize_text_field( $values['ppp_bpe_signature'] ?? '' ), true );
		$item->add_meta_data( self::META_PREFIX_INT . 'source', sanitize_text_field( $offer['source'] ?? 'local' ), true );

		$license = PPP_BPE_Plugin::instance()->get_license();
		if ( null !== $license ) {
			$license->record_event( 'order_created' );
		}
	}

	/**
	 * @param array                  $formatted_meta Formatted meta data.
	 * @param WC_Order_Item_Product  $item           Order item.
	 * @return array
	 */
	public function format_order_item_meta( array $formatted_meta, WC_Order_Item_Product $item ): array {
		$labels = $this->get_spec_labels();

		foreach ( $formatted_meta as $meta_id => &$meta ) {
			$key = $meta->key;

			if ( 0 !== strpos( $key, self::META_PREFIX ) ) {
				continue;
			}

			$short_key = substr( $key, strlen( self::META_PREFIX ) );

			if ( isset( $labels[ $short_key ] ) ) {
				$meta->display_key = $labels[ $short_key ];
			}

			if ( in_array( $short_key, array( 'unit_price', 'total' ), true ) ) {
				$meta->display_value = wc_price( $meta->value );
			}
		}

		return $formatted_meta;
	}

	/**
	 * @param int            $item_id  Order item ID.
	 * @param WC_Order_Item  $item     Order item.
	 * @param WC_Product     $product  Product (unused).
	 */
	public function display_admin_order_item_meta( int $item_id, WC_Order_Item $item, ?WC_Product $product ): void {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$breakdown = $item->get_meta( self::META_PREFIX_INT . 'breakdown' );

		if ( ! is_array( $breakdown ) || empty( $breakdown ) ) {
			return;
		}

		$breakdown_labels = array(
			'interior_cost' => __( 'Interior', 'printpricepro-bpe' ),
			'cover_cost'    => __( 'Cover', 'printpricepro-bpe' ),
			'binding_cost'  => __( 'Binding', 'printpricepro-bpe' ),
			'setup_cost'    => __( 'Setup', 'printpricepro-bpe' ),
		);

		echo '<div class="ppp-bpe-admin-breakdown">';
		echo '<p><strong>' . esc_html__( 'PrintPricePro Price Breakdown', 'printpricepro-bpe' ) . '</strong></p>';
		echo '<table class="ppp-bpe-admin-breakdown-table" style="font-size:12px;border-collapse:collapse;">';
		foreach ( $breakdown as $key => $value ) {
			$label = $breakdown_labels[ $key ] ?? $key;
			echo '<tr>';
			echo '<td style="padding:2px 8px 2px 0;">' . esc_html( $label ) . '</td>';
			echo '<td style="padding:2px 0;text-align:right;">' . wp_kses_post( wc_price( $value ) ) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
		echo '</div>';
	}

	private function sanitize_offer( array $offer ): array {
		$sanitized = array();

		foreach ( self::PRICE_FIELDS as $field ) {
			if ( isset( $offer[ $field ] ) ) {
				$sanitized[ $field ] = 'currency' === $field
					? sanitize_text_field( $offer[ $field ] )
					: floatval( $offer[ $field ] );
			}
		}

		$sanitized['copies'] = absint( $offer['copies'] ?? 0 );

		if ( isset( $offer['specs'] ) && is_array( $offer['specs'] ) ) {
			$sanitized['specs'] = array_map( 'sanitize_text_field', $offer['specs'] );
			if ( isset( $offer['specs']['pages'] ) ) {
				$sanitized['specs']['pages'] = absint( $offer['specs']['pages'] );
			}
			if ( isset( $offer['specs']['copies'] ) ) {
				$sanitized['specs']['copies'] = absint( $offer['specs']['copies'] );
			}
		}

		if ( isset( $offer['breakdown'] ) && is_array( $offer['breakdown'] ) ) {
			$sanitized['breakdown'] = array_map( 'floatval', $offer['breakdown'] );
		}

		$sanitized['source'] = sanitize_text_field( $offer['source'] ?? 'local' );

		return $sanitized;
	}

	private function get_spec_labels(): array {
		return array(
			'book_size'      => __( 'Book Size', 'printpricepro-bpe' ),
			'pages'          => __( 'Pages', 'printpricepro-bpe' ),
			'copies'         => __( 'Copies', 'printpricepro-bpe' ),
			'interior_color' => __( 'Interior Color', 'printpricepro-bpe' ),
			'cover_color'    => __( 'Cover Color', 'printpricepro-bpe' ),
			'binding'        => __( 'Binding', 'printpricepro-bpe' ),
			'paper'          => __( 'Paper', 'printpricepro-bpe' ),
			'country'        => __( 'Country', 'printpricepro-bpe' ),
			'unit_price'     => __( 'Unit Price', 'printpricepro-bpe' ),
			'total'          => __( 'Total', 'printpricepro-bpe' ),
			'currency'       => __( 'Currency', 'printpricepro-bpe' ),
		);
	}

	private function format_spec_value( string $key, mixed $value ): string {
		if ( 'pages' === $key || 'copies' === $key ) {
			return number_format_i18n( (int) $value );
		}

		return (string) $value;
	}
}
