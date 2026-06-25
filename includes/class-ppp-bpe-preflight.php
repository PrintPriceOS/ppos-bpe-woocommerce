<?php
/**
 * Preflight Bridge — optional PDF validation integration.
 *
 * Sends uploaded PDFs to a Preflight service for QA checks,
 * tracks status, and displays humanized results to customers and admins.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Preflight {

	public const STATUS_PENDING  = 'preflight_pending';
	public const STATUS_PASSED   = 'preflight_passed';
	public const STATUS_WARNINGS = 'preflight_warnings';
	public const STATUS_BLOCKED  = 'preflight_blocked';

	private const META_PREFLIGHT_STATUS  = '_ppp_bpe_preflight_status';
	private const META_PREFLIGHT_REPORT  = '_ppp_bpe_preflight_report';
	private const META_PREFLIGHT_JOB_ID  = '_ppp_bpe_preflight_job_id';
	private const META_PREFLIGHT_STARTED = '_ppp_bpe_preflight_started_at';

	private const API_TIMEOUT     = 30;
	private const CONNECT_TIMEOUT = 5;

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_action( 'woocommerce_thankyou', array( $this, 'render_preflight_section' ), 6 );
		add_action( 'woocommerce_view_order', array( $this, 'render_preflight_section' ), 6 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_preflight' ), 11 );
	}

	public function register_routes(): void {
		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/orders/(?P<order_id>\d+)/preflight/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_preflight' ),
				'permission_callback' => array( $this, 'check_permission' ),
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
			'/orders/(?P<order_id>\d+)/preflight/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_preflight_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
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
			'/webhooks/preflight',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_webhook' ),
			)
		);
	}

	public function check_permission( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		return (int) $order->get_customer_id() === get_current_user_id();
	}

	public function verify_webhook( WP_REST_Request $request ): bool {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$secret  = $options['webhook_secret'] ?? '';

		if ( '' === $secret ) {
			return false;
		}

		$signature = $request->get_header( 'X-PPP-Signature' );
		if ( empty( $signature ) ) {
			return false;
		}

		$body    = $request->get_body();
		$expected = hash_hmac( 'sha256', $body, $secret );

		return hash_equals( $expected, $signature );
	}

	public function start_preflight( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::is_enabled() ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Preflight is not enabled.', 'printpricepro-bpe' ) ),
				400
			);
		}

		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order not found.', 'printpricepro-bpe' ) ),
				404
			);
		}

		$file_status = $order->get_meta( '_ppp_bpe_file_status' );
		if ( PPP_BPE_File_Upload::STATUS_FILES_UPLOADED !== $file_status ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Files must be uploaded before starting preflight.', 'printpricepro-bpe' ) ),
				400
			);
		}

		$current_status = $order->get_meta( self::META_PREFLIGHT_STATUS );
		if ( self::STATUS_PENDING === $current_status ) {
			return new WP_REST_Response(
				array(
					'status'  => self::STATUS_PENDING,
					'message' => __( 'Preflight check is already in progress.', 'printpricepro-bpe' ),
				),
				200
			);
		}

		$result = $this->send_to_preflight( $order );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				502
			);
		}

		$order->update_meta_data( self::META_PREFLIGHT_STATUS, self::STATUS_PENDING );
		$order->update_meta_data( self::META_PREFLIGHT_JOB_ID, $result['job_id'] );
		$order->update_meta_data( self::META_PREFLIGHT_STARTED, current_time( 'c' ) );
		$order->update_meta_data( self::META_PREFLIGHT_REPORT, null );
		$order->save();

		$order->add_order_note(
			__( 'Preflight check started.', 'printpricepro-bpe' )
		);

		return new WP_REST_Response(
			array(
				'status'  => self::STATUS_PENDING,
				'job_id'  => $result['job_id'],
				'message' => __( 'Preflight check started. Results will appear shortly.', 'printpricepro-bpe' ),
			),
			200
		);
	}

	public function get_preflight_status( WP_REST_Request $request ): WP_REST_Response {
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order not found.', 'printpricepro-bpe' ) ),
				404
			);
		}

		$status = $order->get_meta( self::META_PREFLIGHT_STATUS );
		$report = $order->get_meta( self::META_PREFLIGHT_REPORT );
		$job_id = $order->get_meta( self::META_PREFLIGHT_JOB_ID );

		if ( empty( $status ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'none',
					'enabled' => self::is_enabled(),
				),
				200
			);
		}

		if ( self::STATUS_PENDING === $status && ! empty( $job_id ) ) {
			$polled = $this->poll_preflight_status( $order, $job_id );
			if ( ! is_wp_error( $polled ) && 'pending' !== ( $polled['status'] ?? 'pending' ) ) {
				$this->apply_preflight_result( $order, $polled );
				$status = $order->get_meta( self::META_PREFLIGHT_STATUS );
				$report = $order->get_meta( self::META_PREFLIGHT_REPORT );
			}
		}

		$data = array(
			'status'     => $status,
			'report'     => $report ? $this->humanize_report( $report ) : null,
			'started_at' => $order->get_meta( self::META_PREFLIGHT_STARTED ) ?: null,
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		if ( empty( $body['job_id'] ) || empty( $body['status'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Missing job_id or status.' ),
				400
			);
		}

		$order = $this->find_order_by_job_id( $body['job_id'] );
		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => 'Order not found for job.' ),
				404
			);
		}

		$this->apply_preflight_result( $order, $body );

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public function trigger_after_upload( WC_Order $order ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		if ( empty( $options['preflight_auto_start'] ) ) {
			return;
		}

		$file_status = $order->get_meta( '_ppp_bpe_file_status' );
		if ( PPP_BPE_File_Upload::STATUS_FILES_UPLOADED !== $file_status ) {
			return;
		}

		$current = $order->get_meta( self::META_PREFLIGHT_STATUS );
		if ( self::STATUS_PENDING === $current ) {
			return;
		}

		$result = $this->send_to_preflight( $order );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Auto-preflight failed for order ' . $order->get_id() . ': ' . $result->get_error_message() );
			return;
		}

		$order->update_meta_data( self::META_PREFLIGHT_STATUS, self::STATUS_PENDING );
		$order->update_meta_data( self::META_PREFLIGHT_JOB_ID, $result['job_id'] );
		$order->update_meta_data( self::META_PREFLIGHT_STARTED, current_time( 'c' ) );
		$order->update_meta_data( self::META_PREFLIGHT_REPORT, null );
		$order->save();

		$order->add_order_note(
			__( 'Preflight check started automatically after file upload.', 'printpricepro-bpe' )
		);
	}

	public function render_preflight_section( int $order_id ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$file_status = $order->get_meta( '_ppp_bpe_file_status' );
		if ( PPP_BPE_File_Upload::STATUS_FILES_UPLOADED !== $file_status ) {
			return;
		}

		$this->enqueue_preflight_assets();

		$status  = $order->get_meta( self::META_PREFLIGHT_STATUS );
		$report  = $order->get_meta( self::META_PREFLIGHT_REPORT );

		$preflight_data = wp_json_encode( array(
			'orderId'       => $order_id,
			'startUrl'      => rest_url( PPP_BPE_Rest::NAMESPACE . '/orders/' . $order_id . '/preflight/start' ),
			'statusUrl'     => rest_url( PPP_BPE_Rest::NAMESPACE . '/orders/' . $order_id . '/preflight/status' ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'status'        => $status ?: 'none',
			'report'        => $report ? $this->humanize_report( $report ) : null,
			'pollInterval'  => 5000,
			'i18n'          => array(
				'title'                => __( 'Preflight Check', 'printpricepro-bpe' ),
				'description'          => __( 'Validate your production files before printing to catch errors early.', 'printpricepro-bpe' ),
				'startButton'          => __( 'Run Preflight Check', 'printpricepro-bpe' ),
				'startingButton'       => __( 'Starting…', 'printpricepro-bpe' ),
				'rerunButton'          => __( 'Re-run Preflight', 'printpricepro-bpe' ),
				'statusPending'        => __( 'Checking…', 'printpricepro-bpe' ),
				'statusPassed'         => __( 'Passed', 'printpricepro-bpe' ),
				'statusWarnings'       => __( 'Passed with Warnings', 'printpricepro-bpe' ),
				'statusBlocked'        => __( 'Blocked', 'printpricepro-bpe' ),
				'messagePending'       => __( 'Your files are being checked. This usually takes a few moments.', 'printpricepro-bpe' ),
				'messagePassed'        => __( 'Your files passed all checks and are ready for production.', 'printpricepro-bpe' ),
				'messageWarnings'      => __( 'Your files passed but have some warnings. Production can proceed, but you may want to review.', 'printpricepro-bpe' ),
				'messageBlocked'       => __( 'Your files have issues that must be fixed before production. Please upload corrected files and run preflight again.', 'printpricepro-bpe' ),
				'reportTitle'          => __( 'Preflight Report', 'printpricepro-bpe' ),
				'interiorFile'         => __( 'Interior PDF', 'printpricepro-bpe' ),
				'coverFile'            => __( 'Cover PDF', 'printpricepro-bpe' ),
				'errorGeneric'         => __( 'Could not start preflight check. Please try again.', 'printpricepro-bpe' ),
				'severityError'        => __( 'Error', 'printpricepro-bpe' ),
				'severityWarning'      => __( 'Warning', 'printpricepro-bpe' ),
				'severityInfo'         => __( 'Info', 'printpricepro-bpe' ),
			),
		) );

		wp_add_inline_script(
			'ppp-bpe-preflight',
			'window.pppBpePreflight = ' . $preflight_data . ';',
			'before'
		);

		include PPP_BPE_PLUGIN_DIR . 'templates/preflight-status.php';
	}

	public function display_admin_preflight( WC_Order $order ): void {
		if ( ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$status = $order->get_meta( self::META_PREFLIGHT_STATUS );
		if ( empty( $status ) ) {
			if ( ! self::is_enabled() ) {
				return;
			}
			?>
			<div class="ppp-bpe-admin-preflight" style="margin-top:16px;">
				<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Preflight Check', 'printpricepro-bpe' ); ?></h3>
				<p><em><?php esc_html_e( 'Preflight not yet started for this order.', 'printpricepro-bpe' ); ?></em></p>
			</div>
			<?php
			return;
		}

		$report     = $order->get_meta( self::META_PREFLIGHT_REPORT );
		$started_at = $order->get_meta( self::META_PREFLIGHT_STARTED );
		$job_id     = $order->get_meta( self::META_PREFLIGHT_JOB_ID );

		$status_labels = array(
			self::STATUS_PENDING  => __( 'Checking…', 'printpricepro-bpe' ),
			self::STATUS_PASSED   => __( 'Passed', 'printpricepro-bpe' ),
			self::STATUS_WARNINGS => __( 'Passed with Warnings', 'printpricepro-bpe' ),
			self::STATUS_BLOCKED  => __( 'Blocked', 'printpricepro-bpe' ),
		);

		$status_colors = array(
			self::STATUS_PENDING  => '#2563eb',
			self::STATUS_PASSED   => '#16a34a',
			self::STATUS_WARNINGS => '#d97706',
			self::STATUS_BLOCKED  => '#dc2626',
		);
		?>
		<div class="ppp-bpe-admin-preflight" style="margin-top:16px;">
			<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Preflight Check', 'printpricepro-bpe' ); ?></h3>
			<p>
				<strong><?php esc_html_e( 'Status:', 'printpricepro-bpe' ); ?></strong>
				<span style="color:<?php echo esc_attr( $status_colors[ $status ] ?? '#6b7280' ); ?>;font-weight:600;">
					<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
				</span>
			</p>
			<?php if ( $started_at ) : ?>
				<p>
					<strong><?php esc_html_e( 'Started:', 'printpricepro-bpe' ); ?></strong>
					<?php echo esc_html( $started_at ); ?>
				</p>
			<?php endif; ?>
			<?php if ( $job_id ) : ?>
				<p>
					<strong><?php esc_html_e( 'Job ID:', 'printpricepro-bpe' ); ?></strong>
					<code><?php echo esc_html( $job_id ); ?></code>
				</p>
			<?php endif; ?>
			<?php if ( $report && is_array( $report ) ) : ?>
				<?php $humanized = $this->humanize_report( $report ); ?>
				<?php if ( ! empty( $humanized['checks'] ) ) : ?>
					<table class="widefat fixed" style="max-width:600px;margin-top:8px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'File', 'printpricepro-bpe' ); ?></th>
								<th><?php esc_html_e( 'Check', 'printpricepro-bpe' ); ?></th>
								<th><?php esc_html_e( 'Severity', 'printpricepro-bpe' ); ?></th>
								<th><?php esc_html_e( 'Message', 'printpricepro-bpe' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $humanized['checks'] as $check ) : ?>
								<tr>
									<td><?php echo esc_html( $check['file'] ?? '—' ); ?></td>
									<td><?php echo esc_html( $check['name'] ?? '—' ); ?></td>
									<td>
										<?php
										$sev_color = 'info' === $check['severity'] ? '#6b7280' : ( 'warning' === $check['severity'] ? '#d97706' : '#dc2626' );
										?>
										<span style="color:<?php echo esc_attr( $sev_color ); ?>;font-weight:600;">
											<?php echo esc_html( ucfirst( $check['severity'] ?? 'info' ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $check['message'] ?? '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( ! empty( $humanized['summary'] ) ) : ?>
					<p style="margin-top:8px;"><em><?php echo esc_html( $humanized['summary'] ); ?></em></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function is_enabled(): bool {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$mode    = $options['mode'] ?? 'local';

		if ( 'local' === $mode ) {
			return false;
		}

		return ! empty( $options['preflight_enabled'] );
	}

	public static function get_order_preflight_status( WC_Order $order ): string {
		return $order->get_meta( self::META_PREFLIGHT_STATUS ) ?: 'none';
	}

	private function send_to_preflight( WC_Order $order ): array|WP_Error {
		$options     = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$api_url     = $options['preflight_api_url'] ?? ( $options['bpe_api_url'] ?? '' );
		$license_key = $options['license_key'] ?? '';
		$tenant_id   = $options['tenant_id'] ?? '';

		if ( '' === $api_url ) {
			return new WP_Error(
				'ppp_bpe_preflight_no_api',
				__( 'Preflight API URL is not configured.', 'printpricepro-bpe' )
			);
		}

		$interior = $order->get_meta( '_ppp_bpe_interior_file' );
		$cover    = $order->get_meta( '_ppp_bpe_cover_file' );

		if ( ! $interior || ! $cover ) {
			return new WP_Error(
				'ppp_bpe_preflight_missing_files',
				__( 'Both interior and cover files are required for preflight.', 'printpricepro-bpe' )
			);
		}

		$specs = $this->get_order_book_specs( $order );

		$webhook_url = rest_url( PPP_BPE_Rest::NAMESPACE . '/webhooks/preflight' );

		$boundary = wp_generate_password( 24, false );
		$body     = $this->build_multipart_body( $boundary, $order, $interior, $cover, $specs, $webhook_url );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$headers = array(
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			'Accept'       => 'application/json',
			'User-Agent'   => 'PrintPricePro-BPE-WooCommerce/' . PPP_BPE_VERSION,
		);

		if ( '' !== $license_key ) {
			$headers['Authorization'] = 'Bearer ' . $license_key;
		}
		if ( '' !== $tenant_id ) {
			$headers['X-PPP-Tenant-ID'] = $tenant_id;
		}

		$endpoint = untrailingslashit( $api_url ) . '/api/preflight/start';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => self::API_TIMEOUT,
				'redirection' => 0,
				'headers'     => $headers,
				'body'        => $body,
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Preflight API connection failed: ' . $response->get_error_message() );
			return new WP_Error(
				'ppp_bpe_preflight_connection',
				__( 'Could not connect to the preflight service. Please try again later.', 'printpricepro-bpe' )
			);
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$res_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $res_body ) && ! empty( $res_body['message'] )
				? sanitize_text_field( $res_body['message'] )
				: __( 'Preflight service returned an error.', 'printpricepro-bpe' );

			$this->log( sprintf( 'Preflight API returned HTTP %d: %s', $code, wp_remote_retrieve_body( $response ) ) );
			return new WP_Error( 'ppp_bpe_preflight_api_error', $message );
		}

		if ( ! is_array( $res_body ) || empty( $res_body['job_id'] ) ) {
			$this->log( 'Preflight API returned invalid response — missing job_id.' );
			return new WP_Error(
				'ppp_bpe_preflight_invalid',
				__( 'Invalid response from preflight service.', 'printpricepro-bpe' )
			);
		}

		return $res_body;
	}

	private function poll_preflight_status( WC_Order $order, string $job_id ): array|WP_Error {
		$options     = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$api_url     = $options['preflight_api_url'] ?? ( $options['bpe_api_url'] ?? '' );
		$license_key = $options['license_key'] ?? '';
		$tenant_id   = $options['tenant_id'] ?? '';

		if ( '' === $api_url ) {
			return new WP_Error( 'no_api', 'No API URL configured.' );
		}

		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'PrintPricePro-BPE-WooCommerce/' . PPP_BPE_VERSION,
		);

		if ( '' !== $license_key ) {
			$headers['Authorization'] = 'Bearer ' . $license_key;
		}
		if ( '' !== $tenant_id ) {
			$headers['X-PPP-Tenant-ID'] = $tenant_id;
		}

		$endpoint = untrailingslashit( $api_url ) . '/api/preflight/status/' . urlencode( $job_id );

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'   => self::CONNECT_TIMEOUT,
				'headers'   => $headers,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			return new WP_Error( 'poll_failed', 'Could not retrieve preflight status.' );
		}

		return $data;
	}

	private function apply_preflight_result( WC_Order $order, array $result ): void {
		$api_status = $result['status'] ?? 'unknown';

		$status_map = array(
			'passed'   => self::STATUS_PASSED,
			'warnings' => self::STATUS_WARNINGS,
			'blocked'  => self::STATUS_BLOCKED,
			'failed'   => self::STATUS_BLOCKED,
			'error'    => self::STATUS_BLOCKED,
		);

		$new_status = $status_map[ $api_status ] ?? self::STATUS_PENDING;

		if ( self::STATUS_PENDING === $new_status ) {
			return;
		}

		$report = array(
			'status'     => $api_status,
			'checks'     => $result['checks'] ?? array(),
			'summary'    => $result['summary'] ?? '',
			'received_at' => current_time( 'c' ),
		);

		$order->update_meta_data( self::META_PREFLIGHT_STATUS, $new_status );
		$order->update_meta_data( self::META_PREFLIGHT_REPORT, $report );
		$order->save();

		$status_labels = array(
			self::STATUS_PASSED   => __( 'Passed', 'printpricepro-bpe' ),
			self::STATUS_WARNINGS => __( 'Passed with Warnings', 'printpricepro-bpe' ),
			self::STATUS_BLOCKED  => __( 'Blocked', 'printpricepro-bpe' ),
		);

		$order->add_order_note(
			sprintf(
				/* translators: %s: preflight result status */
				__( 'Preflight check completed: %s', 'printpricepro-bpe' ),
				$status_labels[ $new_status ] ?? $new_status
			)
		);
	}

	private function humanize_report( array $report ): array {
		$checks    = $report['checks'] ?? array();
		$humanized = array(
			'status'  => $report['status'] ?? 'unknown',
			'summary' => $report['summary'] ?? '',
			'checks'  => array(),
		);

		$check_labels = array(
			'page_count'       => __( 'Page Count', 'printpricepro-bpe' ),
			'page_size'        => __( 'Page Size', 'printpricepro-bpe' ),
			'bleed'            => __( 'Bleed Area', 'printpricepro-bpe' ),
			'resolution'       => __( 'Image Resolution', 'printpricepro-bpe' ),
			'color_space'      => __( 'Color Space', 'printpricepro-bpe' ),
			'fonts'            => __( 'Font Embedding', 'printpricepro-bpe' ),
			'transparency'     => __( 'Transparency', 'printpricepro-bpe' ),
			'spine_width'      => __( 'Spine Width', 'printpricepro-bpe' ),
			'overprint'        => __( 'Overprint Settings', 'printpricepro-bpe' ),
			'pdf_version'      => __( 'PDF Version', 'printpricepro-bpe' ),
			'trim_box'         => __( 'Trim Box', 'printpricepro-bpe' ),
			'ink_coverage'     => __( 'Ink Coverage', 'printpricepro-bpe' ),
		);

		$file_labels = array(
			'interior' => __( 'Interior PDF', 'printpricepro-bpe' ),
			'cover'    => __( 'Cover PDF', 'printpricepro-bpe' ),
		);

		foreach ( $checks as $check ) {
			$name     = $check['name'] ?? $check['check'] ?? 'unknown';
			$file     = $check['file'] ?? 'unknown';
			$severity = $check['severity'] ?? 'info';
			$message  = $check['message'] ?? '';

			$humanized['checks'][] = array(
				'name'     => $check_labels[ $name ] ?? ucwords( str_replace( '_', ' ', $name ) ),
				'file'     => $file_labels[ $file ] ?? ucfirst( $file ),
				'severity' => $severity,
				'message'  => $message,
			);
		}

		return $humanized;
	}

	private function build_multipart_body(
		string $boundary,
		WC_Order $order,
		array $interior,
		array $cover,
		array $specs,
		string $webhook_url
	): string|WP_Error {
		$body = '';

		$body .= '--' . $boundary . "\r\n";
		$body .= "Content-Disposition: form-data; name=\"order_id\"\r\n\r\n";
		$body .= $order->get_id() . "\r\n";

		$body .= '--' . $boundary . "\r\n";
		$body .= "Content-Disposition: form-data; name=\"webhook_url\"\r\n\r\n";
		$body .= $webhook_url . "\r\n";

		$body .= '--' . $boundary . "\r\n";
		$body .= "Content-Disposition: form-data; name=\"specs\"\r\n";
		$body .= "Content-Type: application/json\r\n\r\n";
		$body .= wp_json_encode( $specs ) . "\r\n";

		if ( ! empty( $interior['path'] ) && file_exists( $interior['path'] ) ) {
			$content = file_get_contents( $interior['path'] );
			if ( false === $content ) {
				return new WP_Error( 'read_failed', __( 'Could not read interior PDF.', 'printpricepro-bpe' ) );
			}
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="interior_pdf"; filename="' . sanitize_file_name( $interior['original_name'] ?? 'interior.pdf' ) . "\"\r\n";
			$body .= "Content-Type: application/pdf\r\n\r\n";
			$body .= $content . "\r\n";
		}

		if ( ! empty( $cover['path'] ) && file_exists( $cover['path'] ) ) {
			$content = file_get_contents( $cover['path'] );
			if ( false === $content ) {
				return new WP_Error( 'read_failed', __( 'Could not read cover PDF.', 'printpricepro-bpe' ) );
			}
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="cover_pdf"; filename="' . sanitize_file_name( $cover['original_name'] ?? 'cover.pdf' ) . "\"\r\n";
			$body .= "Content-Type: application/pdf\r\n\r\n";
			$body .= $content . "\r\n";
		}

		$body .= '--' . $boundary . "--\r\n";

		return $body;
	}

	private function get_order_book_specs( WC_Order $order ): array {
		$specs = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$base_product_id = PPP_BPE_WooCommerce::get_base_product_id();
			if ( $item->get_product_id() !== $base_product_id ) {
				continue;
			}

			$specs = array(
				'book_size'      => $item->get_meta( 'ppp_bpe_book_size' ),
				'pages'          => (int) $item->get_meta( 'ppp_bpe_pages' ),
				'copies'         => (int) $item->get_meta( 'ppp_bpe_copies' ),
				'interior_color' => $item->get_meta( 'ppp_bpe_interior_color' ),
				'cover_color'    => $item->get_meta( 'ppp_bpe_cover_color' ),
				'binding'        => $item->get_meta( 'ppp_bpe_binding' ),
				'paper'          => $item->get_meta( 'ppp_bpe_paper' ),
				'country'        => $item->get_meta( 'ppp_bpe_country' ),
			);
			break;
		}

		return $specs;
	}

	private function find_order_by_job_id( string $job_id ): ?WC_Order {
		$orders = wc_get_orders( array(
			'meta_key'   => self::META_PREFLIGHT_JOB_ID,
			'meta_value' => $job_id,
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

	private function enqueue_preflight_assets(): void {
		wp_enqueue_style(
			'ppp-bpe-preflight',
			PPP_BPE_PLUGIN_URL . 'public/css/ppp-bpe-preflight.css',
			array(),
			PPP_BPE_VERSION
		);

		wp_enqueue_script(
			'ppp-bpe-preflight',
			PPP_BPE_PLUGIN_URL . 'public/js/ppp-bpe-preflight.js',
			array(),
			PPP_BPE_VERSION,
			true
		);
	}

	private function log( string $message ): void {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );

		if ( empty( $options['debug_mode'] ) ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'printpricepro-bpe-preflight' ) );
		}
	}
}
