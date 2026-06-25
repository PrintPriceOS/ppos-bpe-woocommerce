<?php
/**
 * PDF file upload handling for print orders.
 *
 * Manages Interior PDF and Cover PDF uploads, secure storage,
 * validation, and order file status tracking.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_File_Upload {

	public const STATUS_FILES_REQUIRED = 'files_required';
	public const STATUS_FILES_UPLOADED = 'files_uploaded';
	public const STATUS_FILES_REJECTED = 'files_rejected';

	private const META_FILE_STATUS   = '_ppp_bpe_file_status';
	private const META_INTERIOR_FILE = '_ppp_bpe_interior_file';
	private const META_COVER_FILE    = '_ppp_bpe_cover_file';

	private const UPLOAD_DIR = 'ppp-bpe-files';

	private const DEFAULT_MAX_SIZE_MB = 100;

	private ?PPP_BPE_Preflight $preflight = null;

	public function set_preflight( PPP_BPE_Preflight $preflight ): void {
		$this->preflight = $preflight;
	}

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_action( 'woocommerce_checkout_order_created', array( $this, 'set_initial_file_status' ) );

		add_action( 'woocommerce_thankyou', array( $this, 'render_thankyou_upload' ), 5 );
		add_action( 'woocommerce_view_order', array( $this, 'render_order_upload' ), 5 );

		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'display_admin_file_info' ), 20, 3 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_files' ) );

		add_action( 'init', array( $this, 'protect_upload_directory' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/orders/(?P<order_id>\d+)/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_upload' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
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
			'/orders/(?P<order_id>\d+)/files',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_order_files' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
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
			'/orders/(?P<order_id>\d+)/files/(?P<file_type>interior|cover)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'serve_file' ),
				'permission_callback' => array( $this, 'check_file_download_permission' ),
				'args'                => array(
					'order_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'file_type' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'interior', 'cover' ),
					),
				),
			)
		);
	}

	public function check_upload_permission( WP_REST_Request $request ): bool {
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

	public function check_file_download_permission( WP_REST_Request $request ): bool {
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

	public function handle_upload( WP_REST_Request $request ): WP_REST_Response {
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

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

		$files   = $request->get_file_params();
		$results = array();
		$errors  = array();

		foreach ( array( 'interior_pdf', 'cover_pdf' ) as $field ) {
			if ( empty( $files[ $field ] ) ) {
				continue;
			}

			$file       = $files[ $field ];
			$validation = $this->validate_file( $file );

			if ( is_wp_error( $validation ) ) {
				$errors[ $field ] = $validation->get_error_message();
				continue;
			}

			$stored = $this->store_file( $file, $order_id, $field );

			if ( is_wp_error( $stored ) ) {
				$errors[ $field ] = $stored->get_error_message();
				continue;
			}

			$meta_key = 'interior_pdf' === $field ? self::META_INTERIOR_FILE : self::META_COVER_FILE;
			$order->update_meta_data( $meta_key, $stored );
			$results[ $field ] = array(
				'filename' => $stored['original_name'],
				'size'     => $stored['size'],
			);
		}

		if ( empty( $results ) && ! empty( $errors ) ) {
			return new WP_REST_Response(
				array( 'errors' => $errors ),
				400
			);
		}

		if ( ! empty( $errors ) ) {
			$order->save();
			$this->update_file_status( $order );
			return new WP_REST_Response(
				array(
					'uploaded' => $results,
					'errors'   => $errors,
					'status'   => $order->get_meta( self::META_FILE_STATUS ),
				),
				207
			);
		}

		$order->save();
		$this->update_file_status( $order );

		$order->add_order_note(
			sprintf(
				/* translators: %s: comma-separated list of uploaded file types */
				__( 'Production files uploaded: %s', 'printpricepro-bpe' ),
				implode( ', ', array_keys( $results ) )
			)
		);

		if ( $this->preflight ) {
			$this->preflight->trigger_after_upload( $order );
		}

		$preflight_status = $order->get_meta( '_ppp_bpe_preflight_status' );

		return new WP_REST_Response(
			array(
				'uploaded'         => $results,
				'status'           => $order->get_meta( self::META_FILE_STATUS ),
				'preflight_status' => $preflight_status ?: null,
			),
			200
		);
	}

	public function get_order_files( WP_REST_Request $request ): WP_REST_Response {
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Order not found.', 'printpricepro-bpe' ) ),
				404
			);
		}

		$status       = $order->get_meta( self::META_FILE_STATUS ) ?: self::STATUS_FILES_REQUIRED;
		$interior     = $order->get_meta( self::META_INTERIOR_FILE );
		$cover        = $order->get_meta( self::META_COVER_FILE );

		$data = array(
			'status'   => $status,
			'interior' => $interior ? array(
				'filename'    => $interior['original_name'] ?? '',
				'size'        => $interior['size'] ?? 0,
				'uploaded_at' => $interior['uploaded_at'] ?? '',
			) : null,
			'cover'    => $cover ? array(
				'filename'    => $cover['original_name'] ?? '',
				'size'        => $cover['size'] ?? 0,
				'uploaded_at' => $cover['uploaded_at'] ?? '',
			) : null,
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function serve_file( WP_REST_Request $request ): WP_REST_Response {
		$order_id  = $request->get_param( 'order_id' );
		$file_type = $request->get_param( 'file_type' );
		$order     = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Order not found.' ), 404 );
		}

		$meta_key  = 'interior' === $file_type ? self::META_INTERIOR_FILE : self::META_COVER_FILE;
		$file_data = $order->get_meta( $meta_key );

		if ( ! $file_data || empty( $file_data['path'] ) ) {
			return new WP_REST_Response( array( 'error' => 'File not found.' ), 404 );
		}

		$path = $file_data['path'];
		if ( ! file_exists( $path ) ) {
			return new WP_REST_Response( array( 'error' => 'File not found on disk.' ), 404 );
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file_data['original_name'] ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		readfile( $path );
		exit;
	}

	public function set_initial_file_status( WC_Order $order ): void {
		if ( ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$order->update_meta_data( self::META_FILE_STATUS, self::STATUS_FILES_REQUIRED );
		$order->save();
	}

	public function render_thankyou_upload( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$this->enqueue_upload_assets();
		$this->render_upload_section( $order );
	}

	public function render_order_upload( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$this->enqueue_upload_assets();
		$this->render_upload_section( $order );
	}

	public function display_admin_order_files( WC_Order $order ): void {
		if ( ! $this->order_has_bpe_items( $order ) ) {
			return;
		}

		$status   = $order->get_meta( self::META_FILE_STATUS ) ?: self::STATUS_FILES_REQUIRED;
		$interior = $order->get_meta( self::META_INTERIOR_FILE );
		$cover    = $order->get_meta( self::META_COVER_FILE );

		$status_labels = array(
			self::STATUS_FILES_REQUIRED         => __( 'Files Required', 'printpricepro-bpe' ),
			self::STATUS_FILES_UPLOADED         => __( 'Files Uploaded', 'printpricepro-bpe' ),
			self::STATUS_FILES_REJECTED         => __( 'Files Rejected', 'printpricepro-bpe' ),
			PPP_BPE_Preflight::STATUS_PENDING   => __( 'Preflight Checking…', 'printpricepro-bpe' ),
			PPP_BPE_Preflight::STATUS_PASSED    => __( 'Preflight Passed', 'printpricepro-bpe' ),
			PPP_BPE_Preflight::STATUS_WARNINGS  => __( 'Preflight Warnings', 'printpricepro-bpe' ),
			PPP_BPE_Preflight::STATUS_BLOCKED   => __( 'Preflight Blocked', 'printpricepro-bpe' ),
		);

		$status_colors = array(
			self::STATUS_FILES_REQUIRED         => '#d97706',
			self::STATUS_FILES_UPLOADED         => '#16a34a',
			self::STATUS_FILES_REJECTED         => '#dc2626',
			PPP_BPE_Preflight::STATUS_PENDING   => '#2563eb',
			PPP_BPE_Preflight::STATUS_PASSED    => '#16a34a',
			PPP_BPE_Preflight::STATUS_WARNINGS  => '#d97706',
			PPP_BPE_Preflight::STATUS_BLOCKED   => '#dc2626',
		);
		?>
		<div class="ppp-bpe-admin-files" style="margin-top:16px;">
			<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Production Files', 'printpricepro-bpe' ); ?></h3>
			<p>
				<strong><?php esc_html_e( 'Status:', 'printpricepro-bpe' ); ?></strong>
				<span style="color:<?php echo esc_attr( $status_colors[ $status ] ?? '#6b7280' ); ?>;font-weight:600;">
					<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
				</span>
			</p>
			<table class="widefat fixed" style="max-width:500px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File', 'printpricepro-bpe' ); ?></th>
						<th><?php esc_html_e( 'Filename', 'printpricepro-bpe' ); ?></th>
						<th><?php esc_html_e( 'Size', 'printpricepro-bpe' ); ?></th>
						<th><?php esc_html_e( 'Action', 'printpricepro-bpe' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Interior PDF', 'printpricepro-bpe' ); ?></td>
						<?php if ( $interior && ! empty( $interior['original_name'] ) ) : ?>
							<td><?php echo esc_html( $interior['original_name'] ); ?></td>
							<td><?php echo esc_html( size_format( $interior['size'] ?? 0 ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( rest_url( PPP_BPE_Rest::NAMESPACE . '/orders/' . $order->get_id() . '/files/interior' ) ); ?>"
								   class="button button-small"><?php esc_html_e( 'Download', 'printpricepro-bpe' ); ?></a>
							</td>
						<?php else : ?>
							<td colspan="3"><em><?php esc_html_e( 'Not uploaded', 'printpricepro-bpe' ); ?></em></td>
						<?php endif; ?>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Cover PDF', 'printpricepro-bpe' ); ?></td>
						<?php if ( $cover && ! empty( $cover['original_name'] ) ) : ?>
							<td><?php echo esc_html( $cover['original_name'] ); ?></td>
							<td><?php echo esc_html( size_format( $cover['size'] ?? 0 ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( rest_url( PPP_BPE_Rest::NAMESPACE . '/orders/' . $order->get_id() . '/files/cover' ) ); ?>"
								   class="button button-small"><?php esc_html_e( 'Download', 'printpricepro-bpe' ); ?></a>
							</td>
						<?php else : ?>
							<td colspan="3"><em><?php esc_html_e( 'Not uploaded', 'printpricepro-bpe' ); ?></em></td>
						<?php endif; ?>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function display_admin_file_info( int $item_id, WC_Order_Item $item, ?WC_Product $product ): void {
		// Handled at order level via display_admin_order_files.
	}

	public function protect_upload_directory(): void {
		$upload_dir = $this->get_upload_dir();

		if ( ! is_dir( $upload_dir ) ) {
			return;
		}

		$htaccess = $upload_dir . '/.htaccess';
		if ( file_exists( $htaccess ) ) {
			return;
		}

		file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all\n" );

		$index = $upload_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	private function validate_file( array $file ): true|WP_Error {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'upload_error', __( 'File upload failed.', 'printpricepro-bpe' ) );
		}

		$finfo    = finfo_open( FILEINFO_MIME_TYPE );
		$mimetype = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( 'application/pdf' !== $mimetype ) {
			return new WP_Error(
				'invalid_type',
				__( 'Only PDF files are accepted.', 'printpricepro-bpe' )
			);
		}

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'pdf' !== $extension ) {
			return new WP_Error(
				'invalid_extension',
				__( 'Only PDF files are accepted.', 'printpricepro-bpe' )
			);
		}

		$max_size = $this->get_max_upload_size();
		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					__( 'File exceeds the maximum upload size of %s.', 'printpricepro-bpe' ),
					size_format( $max_size )
				)
			);
		}

		return true;
	}

	private function store_file( array $file, int $order_id, string $field ): array|WP_Error {
		$upload_dir = $this->get_upload_dir();
		$order_dir  = $upload_dir . '/' . $order_id;

		if ( ! wp_mkdir_p( $order_dir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', 'printpricepro-bpe' ) );
		}

		$index_file = $order_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$safe_name = sanitize_file_name( $file['name'] );
		$filename  = $field . '_' . wp_generate_password( 8, false ) . '_' . $safe_name;
		$dest      = $order_dir . '/' . $filename;

		$existing_meta_key = 'interior_pdf' === $field ? self::META_INTERIOR_FILE : self::META_COVER_FILE;
		$order             = wc_get_order( $order_id );
		if ( $order ) {
			$existing = $order->get_meta( $existing_meta_key );
			if ( $existing && ! empty( $existing['path'] ) && file_exists( $existing['path'] ) ) {
				wp_delete_file( $existing['path'] );
			}
		}

		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'move_failed', __( 'Could not save uploaded file.', 'printpricepro-bpe' ) );
		}

		return array(
			'path'          => $dest,
			'original_name' => $safe_name,
			'size'          => $file['size'],
			'uploaded_at'   => current_time( 'c' ),
		);
	}

	private function update_file_status( WC_Order $order ): void {
		$interior = $order->get_meta( self::META_INTERIOR_FILE );
		$cover    = $order->get_meta( self::META_COVER_FILE );

		if ( $interior && $cover ) {
			$status = self::STATUS_FILES_UPLOADED;
		} else {
			$status = self::STATUS_FILES_REQUIRED;
		}

		$order->update_meta_data( self::META_FILE_STATUS, $status );
		$order->save();
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

	private function get_upload_dir(): string {
		$wp_upload = wp_upload_dir();
		return $wp_upload['basedir'] . '/' . self::UPLOAD_DIR;
	}

	private function get_max_upload_size(): int {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$mb      = absint( $options['max_upload_size_mb'] ?? self::DEFAULT_MAX_SIZE_MB );
		if ( 0 === $mb ) {
			$mb = self::DEFAULT_MAX_SIZE_MB;
		}
		return $mb * 1024 * 1024;
	}

	private function enqueue_upload_assets(): void {
		wp_enqueue_style(
			'ppp-bpe-upload',
			PPP_BPE_PLUGIN_URL . 'public/css/ppp-bpe-upload.css',
			array(),
			PPP_BPE_VERSION
		);

		wp_enqueue_script(
			'ppp-bpe-upload',
			PPP_BPE_PLUGIN_URL . 'public/js/ppp-bpe-upload.js',
			array(),
			PPP_BPE_VERSION,
			true
		);
	}

	private function render_upload_section( WC_Order $order ): void {
		$order_id  = $order->get_id();
		$status    = $order->get_meta( self::META_FILE_STATUS ) ?: self::STATUS_FILES_REQUIRED;
		$interior  = $order->get_meta( self::META_INTERIOR_FILE );
		$cover     = $order->get_meta( self::META_COVER_FILE );
		$max_size  = $this->get_max_upload_size();

		$upload_data = wp_json_encode( array(
			'orderId'    => $order_id,
			'uploadUrl'  => rest_url( PPP_BPE_Rest::NAMESPACE . '/orders/' . $order_id . '/upload' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'maxSize'    => $max_size,
			'maxSizeStr' => size_format( $max_size ),
			'status'     => $status,
			'interior'   => $interior ? array(
				'filename' => $interior['original_name'] ?? '',
				'size'     => size_format( $interior['size'] ?? 0 ),
			) : null,
			'cover'      => $cover ? array(
				'filename' => $cover['original_name'] ?? '',
				'size'     => size_format( $cover['size'] ?? 0 ),
			) : null,
			'i18n'       => array(
				'title'           => __( 'Upload Production Files', 'printpricepro-bpe' ),
				'description'     => __( 'Please upload your Interior PDF and Cover PDF to proceed with production.', 'printpricepro-bpe' ),
				'interiorLabel'   => __( 'Interior PDF', 'printpricepro-bpe' ),
				'coverLabel'      => __( 'Cover PDF', 'printpricepro-bpe' ),
				'dragDrop'        => __( 'Drag & drop or click to select', 'printpricepro-bpe' ),
				'maxSize'         => sprintf(
					/* translators: %s: maximum file size */
					__( 'Max file size: %s', 'printpricepro-bpe' ),
					size_format( $max_size )
				),
				'pdfOnly'         => __( 'PDF files only', 'printpricepro-bpe' ),
				'upload'          => __( 'Upload Files', 'printpricepro-bpe' ),
				'uploading'       => __( 'Uploading…', 'printpricepro-bpe' ),
				'uploaded'        => __( 'Uploaded', 'printpricepro-bpe' ),
				'replace'         => __( 'Replace', 'printpricepro-bpe' ),
				'success'         => __( 'Files uploaded successfully!', 'printpricepro-bpe' ),
				'errorGeneric'    => __( 'Upload failed. Please try again.', 'printpricepro-bpe' ),
				'errorType'       => __( 'Only PDF files are accepted.', 'printpricepro-bpe' ),
				'errorSize'       => sprintf(
					/* translators: %s: maximum file size */
					__( 'File exceeds maximum size of %s.', 'printpricepro-bpe' ),
					size_format( $max_size )
				),
				'statusRequired'  => __( 'Files Required', 'printpricepro-bpe' ),
				'statusUploaded'  => __( 'Files Uploaded', 'printpricepro-bpe' ),
				'statusRejected'  => __( 'Files Rejected', 'printpricepro-bpe' ),
				'allUploaded'     => __( 'All production files have been uploaded. We will begin processing your order.', 'printpricepro-bpe' ),
				'preflightStarted' => __( 'Preflight check has been started automatically.', 'printpricepro-bpe' ),
			),
		) );

		wp_add_inline_script(
			'ppp-bpe-upload',
			'window.pppBpeUpload = ' . $upload_data . ';',
			'before'
		);

		include PPP_BPE_PLUGIN_DIR . 'templates/upload-files.php';
	}
}
