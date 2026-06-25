<?php
/**
 * Control Plane Node Connection — federated OS integration.
 *
 * Syncs orders, files, and production status with the PrintPrice OS
 * Control Plane. Receives marketplace dispatch packages and exposes
 * local production capacity. All operations are gated on federated_node mode.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Control_Plane {

	private const API_TIMEOUT     = 15;
	private const CONNECT_TIMEOUT = 5;

	private const META_CP_ORDER_ID     = '_ppp_bpe_cp_order_id';
	private const META_CP_SYNCED_AT    = '_ppp_bpe_cp_synced_at';
	private const META_CP_FILES_SYNCED = '_ppp_bpe_cp_files_synced';
	private const META_CP_PROD_STATUS  = '_ppp_bpe_cp_production_status';
	private const META_CP_DISPATCH_ID  = '_ppp_bpe_cp_dispatch_id';

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_action( 'woocommerce_checkout_order_created', array( $this, 'sync_order_on_create' ), 20 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'sync_order_on_status_change' ), 10, 3 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_cp_status' ), 12 );

		add_action( 'ppp_bpe_files_uploaded', array( $this, 'sync_files_after_upload' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/node/handshake',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_handshake' ),
				'permission_callback' => array( $this, 'verify_node_webhook' ),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/node/capacity',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_capacity' ),
				'permission_callback' => array( $this, 'verify_node_webhook' ),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/webhooks/control-plane',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_node_webhook' ),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/node/orders/(?P<order_id>\d+)/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'manual_sync_order' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'order_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/node/orders/(?P<order_id>\d+)/sync-files',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'manual_sync_files' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'order_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/node/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_node_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);
	}

	/**
	 * Verify incoming webhook/API requests via HMAC signature or node API key.
	 */
	public function verify_node_webhook( WP_REST_Request $request ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$options = self::get_options();

		$api_key = $request->get_header( 'X-PPP-Node-Key' );
		if ( ! empty( $api_key ) && ! empty( $options['node_api_key'] ) ) {
			return hash_equals( $options['node_api_key'], $api_key );
		}

		$secret = $options['webhook_secret'] ?? '';
		if ( '' === $secret ) {
			return false;
		}

		$signature = $request->get_header( 'X-PPP-Signature' );
		if ( empty( $signature ) ) {
			return false;
		}

		$body     = $request->get_body();
		$expected = hash_hmac( 'sha256', $body, $secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Handle Control Plane handshake — confirms node identity and connectivity.
	 */
	public function handle_handshake( WP_REST_Request $request ): WP_REST_Response {
		$options = self::get_options();

		return new WP_REST_Response(
			array(
				'node_id'        => $options['node_id'] ?? '',
				'tenant_id'      => $options['tenant_id'] ?? '',
				'plugin_version' => PPP_BPE_VERSION,
				'capabilities'   => array(
					'calculator'  => true,
					'file_upload' => true,
					'preflight'   => PPP_BPE_Preflight::is_enabled(),
					'production'  => true,
				),
				'timestamp'      => current_time( 'c' ),
			),
			200
		);
	}

	/**
	 * Expose local production capacity for marketplace routing.
	 */
	public function get_capacity( WP_REST_Request $request ): WP_REST_Response {
		$options = self::get_options();

		$active_orders = wc_get_orders( array(
			'status' => array( 'wc-processing', 'wc-on-hold' ),
			'limit'  => -1,
			'return' => 'ids',
		) );

		return new WP_REST_Response(
			array(
				'node_id'          => $options['node_id'] ?? '',
				'active_orders'    => count( $active_orders ),
				'accepting_orders' => true,
				'capabilities'     => $this->get_production_capabilities(),
				'timestamp'        => current_time( 'c' ),
			),
			200
		);
	}

	/**
	 * Handle incoming webhooks from Control Plane.
	 *
	 * Supported events:
	 * - dispatch_package: Receive a marketplace order dispatch.
	 * - production_status_update: Update production status from Control Plane.
	 * - order_cancelled: Cancel a dispatched order.
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$event = $body['event'] ?? '';

		if ( empty( $event ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Missing event type.' ),
				400
			);
		}

		$this->log( sprintf( 'Control Plane webhook received: %s', $event ) );

		return match ( $event ) {
			'dispatch_package'         => $this->handle_dispatch_package( $body ),
			'production_status_update' => $this->handle_production_status_update( $body ),
			'order_cancelled'          => $this->handle_order_cancelled( $body ),
			default                    => new WP_REST_Response(
				array( 'error' => 'Unknown event type.' ),
				400
			),
		};
	}

	/**
	 * Sync order to Control Plane when created (if federated_node mode).
	 */
	public function sync_order_on_create( WC_Order $order ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$license = PPP_BPE_Plugin::instance()->get_license();
		if ( null !== $license && ! $license->can_use_control_plane() ) {
			return;
		}

		if ( ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$result = $this->push_order( $order );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Order sync failed on create for order ' . $order->get_id() . ': ' . $result->get_error_message() );
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Control Plane sync failed: %s', 'printpricepro-bpe' ),
					$result->get_error_message()
				)
			);
			return;
		}

		$order->add_order_note( __( 'Order synced to Control Plane.', 'printpricepro-bpe' ) );
	}

	/**
	 * Sync order status changes to Control Plane.
	 */
	public function sync_order_on_status_change( int $order_id, string $old_status, string $new_status ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$cp_order_id = $order->get_meta( self::META_CP_ORDER_ID );
		if ( empty( $cp_order_id ) ) {
			return;
		}

		$result = $this->push_status_update( $order, $cp_order_id, $new_status );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Status sync failed for order ' . $order_id . ': ' . $result->get_error_message() );
		}
	}

	/**
	 * Manual order sync via admin REST endpoint.
	 */
	public function manual_sync_order( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::is_enabled() ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Node mode is not enabled.', 'printpricepro-bpe' ) ),
				400
			);
		}

		$order = wc_get_order( $request->get_param( 'order_id' ) );
		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order not found.', 'printpricepro-bpe' ) ),
				404
			);
		}

		$result = $this->push_order( $order );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				502
			);
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'cp_order_id' => $order->get_meta( self::META_CP_ORDER_ID ),
				'synced_at'   => $order->get_meta( self::META_CP_SYNCED_AT ),
			),
			200
		);
	}

	/**
	 * Manual file sync via admin REST endpoint.
	 */
	public function manual_sync_files( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::is_enabled() ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Node mode is not enabled.', 'printpricepro-bpe' ) ),
				400
			);
		}

		$order = wc_get_order( $request->get_param( 'order_id' ) );
		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order not found.', 'printpricepro-bpe' ) ),
				404
			);
		}

		$result = $this->push_files( $order );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				502
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'files_synced' => $order->get_meta( self::META_CP_FILES_SYNCED ),
			),
			200
		);
	}

	/**
	 * Get node connection status for admin.
	 */
	public function get_node_status( WP_REST_Request $request ): WP_REST_Response {
		$options = self::get_options();
		$enabled = self::is_enabled();

		$data = array(
			'enabled'          => $enabled,
			'mode'             => $options['mode'] ?? 'local',
			'control_plane_url' => $options['control_plane_url'] ?? '',
			'tenant_id'        => $options['tenant_id'] ?? '',
			'node_id'          => $options['node_id'] ?? '',
			'has_api_key'      => ! empty( $options['node_api_key'] ),
			'has_webhook_secret' => ! empty( $options['webhook_secret'] ),
		);

		if ( $enabled ) {
			$health = $this->check_control_plane_health();
			$data['connection'] = is_wp_error( $health )
				? array( 'status' => 'error', 'message' => $health->get_error_message() )
				: array( 'status' => 'connected', 'details' => $health );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Check if Control Plane integration is enabled.
	 */
	public static function is_enabled(): bool {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );

		if ( 'federated_node' !== ( $options['mode'] ?? 'local' ) ) {
			return false;
		}

		if ( empty( $options['control_plane_url'] ) ) {
			return false;
		}

		if ( empty( $options['node_id'] ) || empty( $options['tenant_id'] ) ) {
			return false;
		}

		return true;
	}

	public static function get_order_cp_id( WC_Order $order ): string {
		return $order->get_meta( self::META_CP_ORDER_ID ) ?: '';
	}

	public static function get_order_production_status( WC_Order $order ): string {
		return $order->get_meta( self::META_CP_PROD_STATUS ) ?: '';
	}

	/**
	 * Sync files to Control Plane after upload completes.
	 */
	public function sync_files_after_upload( WC_Order $order ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$result = $this->push_files( $order );

		if ( is_wp_error( $result ) ) {
			$this->log( 'File sync after upload failed for order ' . $order->get_id() . ': ' . $result->get_error_message() );
		}
	}

	/**
	 * Display Control Plane sync info in admin order view.
	 */
	public function display_admin_cp_status( WC_Order $order ): void {
		if ( ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$cp_order_id = $order->get_meta( self::META_CP_ORDER_ID );
		$synced_at   = $order->get_meta( self::META_CP_SYNCED_AT );
		$files_sync  = $order->get_meta( self::META_CP_FILES_SYNCED );
		$prod_status = $order->get_meta( self::META_CP_PROD_STATUS );
		$dispatch_id = $order->get_meta( self::META_CP_DISPATCH_ID );

		if ( ! self::is_enabled() && empty( $cp_order_id ) ) {
			return;
		}
		?>
		<div class="ppp-bpe-admin-cp" style="margin-top:16px;">
			<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Control Plane', 'printpricepro-bpe' ); ?></h3>

			<?php if ( $cp_order_id ) : ?>
				<table class="widefat fixed" style="max-width:500px;">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'CP Order ID', 'printpricepro-bpe' ); ?></strong></td>
							<td><code><?php echo esc_html( $cp_order_id ); ?></code></td>
						</tr>
						<?php if ( $dispatch_id ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Dispatch ID', 'printpricepro-bpe' ); ?></strong></td>
								<td><code><?php echo esc_html( $dispatch_id ); ?></code></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><strong><?php esc_html_e( 'Synced At', 'printpricepro-bpe' ); ?></strong></td>
							<td><?php echo esc_html( $synced_at ?: '—' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Files Synced', 'printpricepro-bpe' ); ?></strong></td>
							<td>
								<?php if ( $files_sync ) : ?>
									<span style="color:#16a34a;">&#10003; <?php echo esc_html( $files_sync ); ?></span>
								<?php else : ?>
									<span style="color:#d97706;"><?php esc_html_e( 'Not synced', 'printpricepro-bpe' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $prod_status ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Production Status', 'printpricepro-bpe' ); ?></strong></td>
								<td><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $prod_status ) ) ); ?></strong></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><em><?php esc_html_e( 'Order not yet synced to Control Plane.', 'printpricepro-bpe' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ──── Private: outbound sync ────

	private function push_order( WC_Order $order ): array|WP_Error {
		$options  = self::get_options();
		$endpoint = $this->get_api_url( '/api/nodes/orders' );

		$specs = $this->extract_order_specs( $order );
		$payload = array(
			'node_id'           => $options['node_id'],
			'tenant_id'         => $options['tenant_id'],
			'external_order_id' => (string) $order->get_id(),
			'status'            => $order->get_status(),
			'specs'             => $specs,
			'total'             => (float) $order->get_total(),
			'currency'          => $order->get_currency(),
			'customer'          => array(
				'email'      => $order->get_billing_email(),
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'country'    => $order->get_billing_country(),
			),
			'created_at'        => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : current_time( 'c' ),
		);

		$file_status = $order->get_meta( '_ppp_bpe_file_status' );
		if ( $file_status ) {
			$payload['file_status'] = $file_status;
		}

		$preflight_status = PPP_BPE_Preflight::get_order_preflight_status( $order );
		if ( 'none' !== $preflight_status ) {
			$payload['preflight_status'] = $preflight_status;
		}

		$response = $this->api_request( 'POST', $endpoint, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$cp_order_id = $response['order_id'] ?? $response['id'] ?? '';
		if ( $cp_order_id ) {
			$order->update_meta_data( self::META_CP_ORDER_ID, sanitize_text_field( $cp_order_id ) );
		}
		$order->update_meta_data( self::META_CP_SYNCED_AT, current_time( 'c' ) );
		$order->save();

		return $response;
	}

	private function push_files( WC_Order $order ): array|WP_Error {
		$cp_order_id = $order->get_meta( self::META_CP_ORDER_ID );
		if ( empty( $cp_order_id ) ) {
			$sync_result = $this->push_order( $order );
			if ( is_wp_error( $sync_result ) ) {
				return $sync_result;
			}
			$cp_order_id = $order->get_meta( self::META_CP_ORDER_ID );
		}

		$interior = $order->get_meta( '_ppp_bpe_interior_file' );
		$cover    = $order->get_meta( '_ppp_bpe_cover_file' );

		if ( ! $interior && ! $cover ) {
			return new WP_Error(
				'ppp_bpe_cp_no_files',
				__( 'No production files to sync.', 'printpricepro-bpe' )
			);
		}

		$endpoint = $this->get_api_url( '/api/nodes/orders/' . urlencode( $cp_order_id ) . '/files' );

		$boundary = wp_generate_password( 24, false );
		$body     = '';

		if ( $interior && ! empty( $interior['path'] ) && file_exists( $interior['path'] ) ) {
			$content = file_get_contents( $interior['path'] );
			if ( false !== $content ) {
				$body .= '--' . $boundary . "\r\n";
				$body .= 'Content-Disposition: form-data; name="interior_pdf"; filename="' . sanitize_file_name( $interior['original_name'] ?? 'interior.pdf' ) . "\"\r\n";
				$body .= "Content-Type: application/pdf\r\n\r\n";
				$body .= $content . "\r\n";
			}
		}

		if ( $cover && ! empty( $cover['path'] ) && file_exists( $cover['path'] ) ) {
			$content = file_get_contents( $cover['path'] );
			if ( false !== $content ) {
				$body .= '--' . $boundary . "\r\n";
				$body .= 'Content-Disposition: form-data; name="cover_pdf"; filename="' . sanitize_file_name( $cover['original_name'] ?? 'cover.pdf' ) . "\"\r\n";
				$body .= "Content-Type: application/pdf\r\n\r\n";
				$body .= $content . "\r\n";
			}
		}

		if ( '' === $body ) {
			return new WP_Error(
				'ppp_bpe_cp_files_unreadable',
				__( 'Could not read production files for sync.', 'printpricepro-bpe' )
			);
		}

		$body .= '--' . $boundary . "--\r\n";

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array_merge(
					$this->get_auth_headers(),
					array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary )
				),
				'body'        => $body,
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'File sync failed for order ' . $order->get_id() . ': ' . $response->get_error_message() );
			return new WP_Error(
				'ppp_bpe_cp_file_sync',
				__( 'Could not sync files to Control Plane.', 'printpricepro-bpe' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->log( sprintf( 'File sync returned HTTP %d for order %d', $code, $order->get_id() ) );
			return new WP_Error(
				'ppp_bpe_cp_file_sync_error',
				__( 'Control Plane returned an error during file sync.', 'printpricepro-bpe' )
			);
		}

		$order->update_meta_data( self::META_CP_FILES_SYNCED, current_time( 'c' ) );
		$order->save();

		$order->add_order_note( __( 'Production files synced to Control Plane.', 'printpricepro-bpe' ) );

		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: array( 'synced' => true );
	}

	private function push_status_update( WC_Order $order, string $cp_order_id, string $new_status ): array|WP_Error {
		$endpoint = $this->get_api_url( '/api/nodes/orders/' . urlencode( $cp_order_id ) . '/status' );

		$payload = array(
			'status'     => $new_status,
			'updated_at' => current_time( 'c' ),
		);

		return $this->api_request( 'PUT', $endpoint, $payload );
	}

	// ──── Private: inbound webhooks ────

	private function handle_dispatch_package( array $body ): WP_REST_Response {
		$dispatch_id = $body['dispatch_id'] ?? '';
		$specs       = $body['specs'] ?? array();
		$customer    = $body['customer'] ?? array();

		if ( empty( $dispatch_id ) || empty( $specs ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Missing dispatch_id or specs.' ),
				400
			);
		}

		$existing = wc_get_orders( array(
			'meta_key'   => self::META_CP_DISPATCH_ID,
			'meta_value' => $dispatch_id,
			'limit'      => 1,
		) );

		if ( ! empty( $existing ) ) {
			return new WP_REST_Response(
				array(
					'received'  => true,
					'order_id'  => $existing[0]->get_id(),
					'duplicate' => true,
				),
				200
			);
		}

		$product_id = PPP_BPE_WooCommerce::get_base_product_id();
		if ( ! $product_id ) {
			return new WP_REST_Response(
				array( 'error' => 'Base product not configured.' ),
				500
			);
		}

		$order = wc_create_order( array(
			'status' => 'on-hold',
		) );

		if ( is_wp_error( $order ) ) {
			$this->log( 'Failed to create order for dispatch ' . $dispatch_id . ': ' . $order->get_error_message() );
			return new WP_REST_Response(
				array( 'error' => 'Could not create order.' ),
				500
			);
		}

		$product = wc_get_product( $product_id );
		$item_id = $order->add_product( $product, 1 );
		$item    = $order->get_item( $item_id );

		if ( $item instanceof WC_Order_Item_Product ) {
			foreach ( $specs as $key => $value ) {
				$item->add_meta_data( 'ppp_bpe_' . sanitize_key( $key ), sanitize_text_field( $value ), true );
			}
			$item->save();
		}

		$total = floatval( $body['total'] ?? 0 );
		if ( $total > 0 ) {
			$order->set_total( $total );
		}

		if ( ! empty( $customer['email'] ) ) {
			$order->set_billing_email( sanitize_email( $customer['email'] ) );
		}
		if ( ! empty( $customer['first_name'] ) ) {
			$order->set_billing_first_name( sanitize_text_field( $customer['first_name'] ) );
		}
		if ( ! empty( $customer['last_name'] ) ) {
			$order->set_billing_last_name( sanitize_text_field( $customer['last_name'] ) );
		}
		if ( ! empty( $customer['country'] ) ) {
			$order->set_billing_country( sanitize_text_field( $customer['country'] ) );
		}

		$order->update_meta_data( self::META_CP_DISPATCH_ID, sanitize_text_field( $dispatch_id ) );
		$order->update_meta_data( self::META_CP_ORDER_ID, sanitize_text_field( $body['cp_order_id'] ?? $dispatch_id ) );
		$order->update_meta_data( self::META_CP_SYNCED_AT, current_time( 'c' ) );
		$order->update_meta_data( '_ppp_bpe_file_status', PPP_BPE_File_Upload::STATUS_FILES_REQUIRED );

		$order->add_order_note(
			sprintf(
				/* translators: %s: dispatch package ID */
				__( 'Order received from PrintPrice OS marketplace (dispatch: %s).', 'printpricepro-bpe' ),
				$dispatch_id
			)
		);

		$order->save();

		$this->log( sprintf( 'Dispatch package %s created as order %d', $dispatch_id, $order->get_id() ) );

		return new WP_REST_Response(
			array(
				'received' => true,
				'order_id' => $order->get_id(),
			),
			201
		);
	}

	private function handle_production_status_update( array $body ): WP_REST_Response {
		$cp_order_id = $body['cp_order_id'] ?? $body['order_id'] ?? '';
		$status      = $body['status'] ?? '';

		if ( empty( $cp_order_id ) || empty( $status ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Missing cp_order_id or status.' ),
				400
			);
		}

		$order = $this->find_order_by_cp_id( $cp_order_id );
		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => 'Order not found.' ),
				404
			);
		}

		$order->update_meta_data( self::META_CP_PROD_STATUS, sanitize_text_field( $status ) );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: %s: production status */
				__( 'Production status updated from Control Plane: %s', 'printpricepro-bpe' ),
				$status
			)
		);

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function handle_order_cancelled( array $body ): WP_REST_Response {
		$cp_order_id = $body['cp_order_id'] ?? $body['order_id'] ?? '';

		if ( empty( $cp_order_id ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Missing cp_order_id.' ),
				400
			);
		}

		$order = $this->find_order_by_cp_id( $cp_order_id );
		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => 'Order not found.' ),
				404
			);
		}

		$reason = sanitize_text_field( $body['reason'] ?? '' );
		$order->update_status(
			'cancelled',
			sprintf(
				/* translators: %s: cancellation reason */
				__( 'Cancelled by Control Plane. %s', 'printpricepro-bpe' ),
				$reason
			)
		);

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	// ──── Private: HTTP helpers ────

	private function api_request( string $method, string $url, array $payload = array() ): array|WP_Error {
		$args = array(
			'method'      => $method,
			'timeout'     => self::API_TIMEOUT,
			'redirection' => 0,
			'headers'     => array_merge(
				$this->get_auth_headers(),
				array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				)
			),
			'sslverify'   => true,
		);

		if ( ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Control Plane request failed: ' . $response->get_error_message() );
			return new WP_Error(
				'ppp_bpe_cp_connection',
				__( 'Could not connect to Control Plane.', 'printpricepro-bpe' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] )
				? sanitize_text_field( $body['message'] )
				: __( 'Control Plane returned an error.', 'printpricepro-bpe' );

			$this->log( sprintf( 'Control Plane returned HTTP %d: %s', $code, wp_remote_retrieve_body( $response ) ) );
			return new WP_Error( 'ppp_bpe_cp_error', $message );
		}

		return is_array( $body ) ? $body : array();
	}

	private function get_auth_headers(): array {
		$options = self::get_options();
		$headers = array(
			'User-Agent' => 'PrintPricePro-BPE-WooCommerce/' . PPP_BPE_VERSION,
		);

		if ( ! empty( $options['node_api_key'] ) ) {
			$headers['Authorization']  = 'Bearer ' . $options['node_api_key'];
		}
		if ( ! empty( $options['tenant_id'] ) ) {
			$headers['X-PPP-Tenant-ID'] = $options['tenant_id'];
		}
		if ( ! empty( $options['node_id'] ) ) {
			$headers['X-PPP-Node-ID'] = $options['node_id'];
		}

		return $headers;
	}

	private function get_api_url( string $path ): string {
		$options = self::get_options();
		return untrailingslashit( $options['control_plane_url'] ?? '' ) . $path;
	}

	private function check_control_plane_health(): array|WP_Error {
		$endpoint = $this->get_api_url( '/api/health' );
		return $this->api_request( 'GET', $endpoint );
	}

	// ──── Private: helpers ────

	private function extract_order_specs( WC_Order $order ): array {
		$specs            = array();
		$base_product_id  = PPP_BPE_WooCommerce::get_base_product_id();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			if ( $item->get_product_id() !== $base_product_id ) {
				continue;
			}

			$spec_fields = array(
				'book_size', 'pages', 'copies', 'interior_color',
				'cover_color', 'binding', 'paper', 'country',
			);

			foreach ( $spec_fields as $field ) {
				$value = $item->get_meta( 'ppp_bpe_' . $field );
				if ( '' !== $value ) {
					$specs[ $field ] = $value;
				}
			}
			break;
		}

		return $specs;
	}

	private function find_order_by_cp_id( string $cp_order_id ): ?WC_Order {
		$orders = wc_get_orders( array(
			'meta_key'   => self::META_CP_ORDER_ID,
			'meta_value' => $cp_order_id,
			'limit'      => 1,
		) );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	private function order_has_bpe_items( WC_Order $order ): bool {
		$base_product_id = PPP_BPE_WooCommerce::get_base_product_id();
		if ( ! $base_product_id ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && $item->get_product_id() === $base_product_id ) {
				return true;
			}
		}

		return false;
	}

	private function get_production_capabilities(): array {
		$calculator = new PPP_BPE_Calculator();
		$options    = $calculator->get_form_options();

		return array(
			'book_sizes' => array_keys( $options['book_sizes'] ?? array() ),
			'bindings'   => array_keys( $options['bindings'] ?? array() ),
			'papers'     => array_keys( $options['papers'] ?? array() ),
		);
	}

	private static function get_options(): array {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		return wp_parse_args( $options, array(
			'mode'              => 'local',
			'control_plane_url' => '',
			'node_api_key'      => '',
			'tenant_id'         => '',
			'node_id'           => '',
			'webhook_secret'    => '',
		) );
	}

	private function log( string $message ): void {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );

		if ( empty( $options['debug_mode'] ) ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'printpricepro-bpe-control-plane' ) );
		}
	}
}
