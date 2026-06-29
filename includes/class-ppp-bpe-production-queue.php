<?php
/**
 * Printhouse Mini Queue — simple production queue for small print houses.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Production_Queue {

	public const STATUS_NEW             = 'new';
	public const STATUS_REVIEWING       = 'reviewing';
	public const STATUS_ACCEPTED        = 'accepted';
	public const STATUS_IN_PREPRESS     = 'in_prepress';
	public const STATUS_IN_PRODUCTION   = 'in_production';
	public const STATUS_COMPLETED       = 'completed';
	public const STATUS_SHIPPED         = 'shipped';
	public const STATUS_ACTION_REQUIRED = 'action_required';

	private const META_PRODUCTION_STATUS = '_ppp_bpe_production_status';
	private const META_STATUS_HISTORY    = '_ppp_bpe_status_history';

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'set_initial_production_status' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'set_initial_production_status' ), 20 );
		add_action( 'woocommerce_view_order', array( $this, 'render_customer_tracking' ), 15 );
	}

	public function register_routes(): void {
		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/queue/(?P<order_id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'order_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'status'   => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => self::get_status_keys(),
					),
					'note'     => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/queue',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_queue' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'status'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);
	}

	public function check_admin_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function get_status_keys(): array {
		return array(
			self::STATUS_NEW,
			self::STATUS_REVIEWING,
			self::STATUS_ACCEPTED,
			self::STATUS_IN_PREPRESS,
			self::STATUS_IN_PRODUCTION,
			self::STATUS_COMPLETED,
			self::STATUS_SHIPPED,
			self::STATUS_ACTION_REQUIRED,
		);
	}

	public static function get_status_labels(): array {
		return array(
			self::STATUS_NEW             => __( 'New', 'printpricepro-bpe' ),
			self::STATUS_REVIEWING       => __( 'Reviewing', 'printpricepro-bpe' ),
			self::STATUS_ACCEPTED        => __( 'Accepted', 'printpricepro-bpe' ),
			self::STATUS_IN_PREPRESS     => __( 'In Prepress', 'printpricepro-bpe' ),
			self::STATUS_IN_PRODUCTION   => __( 'In Production', 'printpricepro-bpe' ),
			self::STATUS_COMPLETED       => __( 'Completed', 'printpricepro-bpe' ),
			self::STATUS_SHIPPED         => __( 'Shipped', 'printpricepro-bpe' ),
			self::STATUS_ACTION_REQUIRED => __( 'Action Required', 'printpricepro-bpe' ),
		);
	}

	public static function get_status_colors(): array {
		return array(
			self::STATUS_NEW             => '#6b7280',
			self::STATUS_REVIEWING       => '#2563eb',
			self::STATUS_ACCEPTED        => '#16a34a',
			self::STATUS_IN_PREPRESS     => '#7c3aed',
			self::STATUS_IN_PRODUCTION   => '#d97706',
			self::STATUS_COMPLETED       => '#16a34a',
			self::STATUS_SHIPPED         => '#059669',
			self::STATUS_ACTION_REQUIRED => '#dc2626',
		);
	}

	public function set_initial_production_status( WC_Order $order ): void {
		if ( ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$order->update_meta_data( self::META_PRODUCTION_STATUS, self::STATUS_NEW );
		$this->append_status_history( $order, self::STATUS_NEW, __( 'Order created.', 'printpricepro-bpe' ) );
		$order->save();
	}

	public function update_status( WP_REST_Request $request ): WP_REST_Response {
		$order_id   = $request->get_param( 'order_id' );
		$new_status = $request->get_param( 'status' );
		$note       = $request->get_param( 'note' );
		$order      = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order not found.', 'printpricepro-bpe' ) ),
				404
			);
		}

		if ( ! $this->order_has_bpe_items( $order ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'This order does not contain PrintPricePro items.', 'printpricepro-bpe' ) ),
				400
			);
		}

		$old_status = $order->get_meta( self::META_PRODUCTION_STATUS );
		$old_status = $old_status ? $old_status : self::STATUS_NEW;

		if ( $old_status === $new_status ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order is already in that status.', 'printpricepro-bpe' ) ),
				400
			);
		}

		$labels = self::get_status_labels();

		$order->update_meta_data( self::META_PRODUCTION_STATUS, $new_status );

		$note_text = sprintf(
			/* translators: 1: old status label, 2: new status label */
			__( 'Production status changed from "%1$s" to "%2$s".', 'printpricepro-bpe' ),
			$labels[ $old_status ] ?? $old_status,
			$labels[ $new_status ] ?? $new_status
		);

		if ( '' !== $note ) {
			$note_text .= ' ' . $note;
		}

		$this->append_status_history( $order, $new_status, $note_text );
		$order->save();

		$order->add_order_note( $note_text );

		if ( PPP_BPE_Control_Plane::is_enabled() ) {
			$this->sync_status_to_control_plane( $order, $new_status );
		}

		do_action( 'ppp_bpe_production_status_changed', $order, $new_status, $old_status );

		return new WP_REST_Response(
			array(
				'order_id'   => $order_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'label'      => $labels[ $new_status ] ?? $new_status,
			),
			200
		);
	}

	public function get_queue( WP_REST_Request $request ): WP_REST_Response {
		$filter_status = $request->get_param( 'status' );
		$per_page      = $request->get_param( 'per_page' );
		$page          = $request->get_param( 'page' );

		$orders = $this->query_bpe_orders( $filter_status, $per_page, $page );

		$items = array();
		foreach ( $orders as $order ) {
			$items[] = $this->format_queue_item( $order );
		}

		return new WP_REST_Response( $items, 200 );
	}

	public function query_bpe_orders( ?string $filter_status = null, int $per_page = 20, int $page = 1 ): array {
		$base_product_id = PPP_BPE_WooCommerce::get_base_product_id();
		if ( ! $base_product_id ) {
			return array();
		}

		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => array( 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-pending' ),
		);

		if ( null !== $filter_status && '' !== $filter_status ) {
			$args['meta_query'] = array(
				array(
					'key'   => self::META_PRODUCTION_STATUS,
					'value' => $filter_status,
				),
			);
		}

		$all_orders = wc_get_orders( $args );
		$bpe_orders = array();

		foreach ( $all_orders as $order ) {
			if ( $this->order_has_bpe_items( $order ) ) {
				$bpe_orders[] = $order;
			}
		}

		return $bpe_orders;
	}

	public function format_queue_item( WC_Order $order ): array {
		$production_status = $order->get_meta( self::META_PRODUCTION_STATUS );
		$production_status = $production_status ? $production_status : self::STATUS_NEW;
		$file_status       = $order->get_meta( '_ppp_bpe_file_status' );
		$file_status       = $file_status ? $file_status : 'none';
		$preflight_status  = $order->get_meta( '_ppp_bpe_preflight_status' );
		$preflight_status  = $preflight_status ? $preflight_status : 'none';
		$labels            = self::get_status_labels();

		$specs           = array();
		$base_product_id = PPP_BPE_WooCommerce::get_base_product_id();
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && $item->get_product_id() === $base_product_id ) {
				$specs = array(
					'book_size'      => $item->get_meta( '_ppp_bpe_book_size' ),
					'pages'          => $item->get_meta( '_ppp_bpe_pages' ),
					'copies'         => $item->get_meta( '_ppp_bpe_copies' ),
					'binding'        => $item->get_meta( '_ppp_bpe_binding' ),
					'interior_color' => $item->get_meta( '_ppp_bpe_interior_color' ),
					'paper'          => $item->get_meta( '_ppp_bpe_paper' ),
				);
				break;
			}
		}

		return array(
			'order_id'          => $order->get_id(),
			'order_number'      => $order->get_order_number(),
			'date_created'      => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i' ) : '',
			'customer'          => $this->get_customer_display( $order ),
			'specs'             => $specs,
			'total'             => $order->get_total(),
			'currency'          => $order->get_currency(),
			'payment_status'    => $order->get_status(),
			'file_status'       => $file_status,
			'preflight_status'  => $preflight_status,
			'production_status' => $production_status,
			'status_label'      => $labels[ $production_status ] ?? $production_status,
			'edit_url'          => $order->get_edit_order_url(),
		);
	}

	public function render_queue_page(): void {
		$filter_status = isset( $_GET['production_status'] ) ? sanitize_text_field( wp_unslash( $_GET['production_status'] ) ) : '';
		$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page      = 20;
		$orders        = $this->query_bpe_orders( $filter_status ? $filter_status : null, $per_page, $paged );
		$labels        = self::get_status_labels();
		$colors        = self::get_status_colors();

		$file_labels = array(
			'none'           => __( '—', 'printpricepro-bpe' ),
			'files_required' => __( 'Required', 'printpricepro-bpe' ),
			'files_uploaded' => __( 'Uploaded', 'printpricepro-bpe' ),
			'files_rejected' => __( 'Rejected', 'printpricepro-bpe' ),
		);

		$preflight_labels = array(
			'none'               => __( '—', 'printpricepro-bpe' ),
			'preflight_pending'  => __( 'Pending', 'printpricepro-bpe' ),
			'preflight_passed'   => __( 'Passed', 'printpricepro-bpe' ),
			'preflight_warnings' => __( 'Warnings', 'printpricepro-bpe' ),
			'preflight_blocked'  => __( 'Blocked', 'printpricepro-bpe' ),
		);

		$payment_labels = array(
			'pending'    => __( 'Pending', 'printpricepro-bpe' ),
			'processing' => __( 'Paid', 'printpricepro-bpe' ),
			'on-hold'    => __( 'On Hold', 'printpricepro-bpe' ),
			'completed'  => __( 'Completed', 'printpricepro-bpe' ),
		);

		$queue_data = wp_json_encode(
			array(
				'restUrl' => rest_url( PPP_BPE_Rest::NAMESPACE . '/queue' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					/* translators: %s: new production status name */
					'confirmChange' => __( 'Change production status to "%s"?', 'printpricepro-bpe' ),
					'updated'       => __( 'Status updated.', 'printpricepro-bpe' ),
					'error'         => __( 'Failed to update status.', 'printpricepro-bpe' ),
					'notePrompt'    => __( 'Add a note (optional):', 'printpricepro-bpe' ),
				),
			)
		);

		wp_add_inline_script(
			'ppp-bpe-admin',
			'window.pppBpeQueue = ' . $queue_data . ';',
			'before'
		);

		?>
		<div class="wrap ppp-bpe-admin-wrap ppp-bpe-queue-wrap">
			<h1><?php esc_html_e( 'Production Queue', 'printpricepro-bpe' ); ?></h1>

			<div class="ppp-bpe-queue-filters">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=printpricepro-bpe-orders' ) ); ?>"
					class="ppp-bpe-filter-link <?php echo '' === $filter_status ? 'active' : ''; ?>">
					<?php esc_html_e( 'All', 'printpricepro-bpe' ); ?>
				</a>
				<?php foreach ( $labels as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'production_status', $key, admin_url( 'admin.php?page=printpricepro-bpe-orders' ) ) ); ?>"
						class="ppp-bpe-filter-link <?php echo $filter_status === $key ? 'active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $orders ) ) : ?>
				<div class="ppp-bpe-queue-empty">
					<p><?php esc_html_e( 'No print orders found.', 'printpricepro-bpe' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped ppp-bpe-queue-table">
					<thead>
						<tr>
							<th class="column-order"><?php esc_html_e( 'Order', 'printpricepro-bpe' ); ?></th>
							<th class="column-customer"><?php esc_html_e( 'Customer', 'printpricepro-bpe' ); ?></th>
							<th class="column-specs"><?php esc_html_e( 'Book Specs', 'printpricepro-bpe' ); ?></th>
							<th class="column-files"><?php esc_html_e( 'Files', 'printpricepro-bpe' ); ?></th>
							<th class="column-preflight"><?php esc_html_e( 'Preflight', 'printpricepro-bpe' ); ?></th>
							<th class="column-payment"><?php esc_html_e( 'Payment', 'printpricepro-bpe' ); ?></th>
							<th class="column-production"><?php esc_html_e( 'Production', 'printpricepro-bpe' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'printpricepro-bpe' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $orders as $order ) :
							$item              = $this->format_queue_item( $order );
							$production_status = $item['production_status'];
							$status_color      = $colors[ $production_status ] ?? '#6b7280';
							?>
						<tr data-order-id="<?php echo esc_attr( $item['order_id'] ); ?>">
							<td class="column-order">
								<a href="<?php echo esc_url( $item['edit_url'] ); ?>">
									<strong>#<?php echo esc_html( $item['order_number'] ); ?></strong>
								</a>
								<br>
								<span class="ppp-bpe-queue-date"><?php echo esc_html( $item['date_created'] ); ?></span>
							</td>
							<td class="column-customer">
								<?php echo esc_html( $item['customer'] ); ?>
							</td>
							<td class="column-specs">
								<?php if ( ! empty( $item['specs']['book_size'] ) ) : ?>
									<span class="ppp-bpe-spec-tag"><?php echo esc_html( $item['specs']['book_size'] ); ?></span>
									<span class="ppp-bpe-spec-tag">
									<?php
										/* translators: %s: number of pages */
										echo esc_html( sprintf( __( '%s pp', 'printpricepro-bpe' ), $item['specs']['pages'] ) );
									?>
									</span>
									<span class="ppp-bpe-spec-tag">
									<?php
										/* translators: %s: number of copies */
										echo esc_html( sprintf( __( '%s copies', 'printpricepro-bpe' ), $item['specs']['copies'] ) );
									?>
									</span>
									<?php if ( ! empty( $item['specs']['binding'] ) ) : ?>
										<span class="ppp-bpe-spec-tag"><?php echo esc_html( $item['specs']['binding'] ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<em><?php esc_html_e( '—', 'printpricepro-bpe' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="column-files">
								<?php
								$file_label = $file_labels[ $item['file_status'] ] ?? $item['file_status'];
								$file_class = 'files_uploaded' === $item['file_status'] ? 'ppp-bpe-badge-success' : ( 'files_required' === $item['file_status'] ? 'ppp-bpe-badge-warning' : 'ppp-bpe-badge-default' );
								?>
								<span class="ppp-bpe-badge <?php echo esc_attr( $file_class ); ?>"><?php echo esc_html( $file_label ); ?></span>
							</td>
							<td class="column-preflight">
								<?php
								$pf_label = $preflight_labels[ $item['preflight_status'] ] ?? $item['preflight_status'];
								$pf_class = 'preflight_passed' === $item['preflight_status'] ? 'ppp-bpe-badge-success' : ( 'preflight_blocked' === $item['preflight_status'] ? 'ppp-bpe-badge-error' : 'ppp-bpe-badge-default' );
								?>
								<span class="ppp-bpe-badge <?php echo esc_attr( $pf_class ); ?>"><?php echo esc_html( $pf_label ); ?></span>
							</td>
							<td class="column-payment">
								<?php
								$pay_label = $payment_labels[ $item['payment_status'] ] ?? $item['payment_status'];
								$pay_class = 'processing' === $item['payment_status'] || 'completed' === $item['payment_status'] ? 'ppp-bpe-badge-success' : 'ppp-bpe-badge-warning';
								?>
								<span class="ppp-bpe-badge <?php echo esc_attr( $pay_class ); ?>"><?php echo esc_html( $pay_label ); ?></span>
							</td>
							<td class="column-production">
								<span class="ppp-bpe-production-badge" style="background:<?php echo esc_attr( $status_color ); ?>;">
									<?php echo esc_html( $item['status_label'] ); ?>
								</span>
							</td>
							<td class="column-actions">
								<select class="ppp-bpe-status-select" data-order-id="<?php echo esc_attr( $item['order_id'] ); ?>" data-current="<?php echo esc_attr( $production_status ); ?>">
									<?php foreach ( $labels as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $production_status, $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_customer_tracking( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		wp_enqueue_style(
			'ppp-bpe-tracking',
			PPP_BPE_PLUGIN_URL . 'public/css/ppp-bpe-tracking.css',
			array(),
			PPP_BPE_VERSION
		);

		$production_status = $order->get_meta( self::META_PRODUCTION_STATUS );
		$production_status = $production_status ? $production_status : self::STATUS_NEW;
		$labels            = self::get_status_labels();
		$colors            = self::get_status_colors();

		$steps = array(
			self::STATUS_NEW,
			self::STATUS_REVIEWING,
			self::STATUS_ACCEPTED,
			self::STATUS_IN_PREPRESS,
			self::STATUS_IN_PRODUCTION,
			self::STATUS_COMPLETED,
			self::STATUS_SHIPPED,
		);

		$current_index = array_search( $production_status, $steps, true );
		if ( false === $current_index ) {
			$current_index = -1;
		}
		?>
		<div class="ppp-bpe-tracking">
			<h2><?php esc_html_e( 'Production Status', 'printpricepro-bpe' ); ?></h2>

			<?php if ( self::STATUS_ACTION_REQUIRED === $production_status ) : ?>
				<div class="ppp-bpe-tracking-alert">
					<strong><?php esc_html_e( 'Action Required', 'printpricepro-bpe' ); ?></strong>
					<p><?php esc_html_e( 'There is an issue with your order. Please check your order notes or contact us.', 'printpricepro-bpe' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="ppp-bpe-tracking-steps">
				<?php
				foreach ( $steps as $i => $step ) :
					$is_done    = $i < $current_index;
					$is_current = $i === $current_index;
					$step_class = $is_done ? 'done' : ( $is_current ? 'current' : 'pending' );
					?>
					<div class="ppp-bpe-tracking-step <?php echo esc_attr( $step_class ); ?>">
						<div class="ppp-bpe-tracking-dot"></div>
						<span class="ppp-bpe-tracking-label"><?php echo esc_html( $labels[ $step ] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function append_status_history( WC_Order $order, string $status, string $note ): void {
		$history = $order->get_meta( self::META_STATUS_HISTORY );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = array(
			'status'    => $status,
			'note'      => $note,
			'timestamp' => current_time( 'c' ),
			'user_id'   => get_current_user_id(),
		);

		$order->update_meta_data( self::META_STATUS_HISTORY, $history );
	}

	private function sync_status_to_control_plane( WC_Order $order, string $status ): void {
		$cp_order_id = $order->get_meta( '_ppp_bpe_cp_order_id' );
		if ( ! $cp_order_id ) {
			return;
		}

		$options  = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$base_url = rtrim( $options['control_plane_url'] ?? '', '/' );
		$api_key  = $options['node_api_key'] ?? '';
		$node_id  = $options['node_id'] ?? '';

		if ( '' === $base_url || '' === $api_key ) {
			return;
		}

		wp_remote_post(
			$base_url . '/api/nodes/' . $node_id . '/orders/' . $cp_order_id . '/production-status',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'status'    => $status,
						'timestamp' => current_time( 'c' ),
					)
				),
				'timeout' => 15,
			)
		);
	}

	private function get_customer_display( WC_Order $order ): string {
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( '' === $name ) {
			$name = $order->get_billing_email();
		}
		if ( '' === $name ) {
			$name = sprintf(
				/* translators: %d: customer ID */
				__( 'Customer #%d', 'printpricepro-bpe' ),
				$order->get_customer_id()
			);
		}
		return $name;
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
}
