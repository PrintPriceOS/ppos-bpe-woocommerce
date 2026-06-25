<?php
/**
 * Licensing, feature gating, usage metering, and SaaS conversion layer.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_License {

	public const PLAN_FREE            = 'free';
	public const PLAN_PRO             = 'pro_calculator';
	public const PLAN_PREFLIGHT       = 'preflight_addon';
	public const PLAN_CONNECTED_NODE  = 'connected_node';
	public const PLAN_MARKETPLACE     = 'marketplace_node';

	public const OPTION_LICENSE_DATA = 'ppp_bpe_license_data';
	public const OPTION_USAGE_DATA   = 'ppp_bpe_usage_data';

	private const LICENSE_DEFAULTS = array(
		'key'          => '',
		'plan'         => self::PLAN_FREE,
		'status'       => 'inactive',
		'activated_at' => '',
		'expires_at'   => '',
		'last_checked' => '',
		'customer'     => '',
	);

	private const PLAN_HIERARCHY = array(
		self::PLAN_FREE            => 0,
		self::PLAN_PRO             => 1,
		self::PLAN_PREFLIGHT       => 2,
		self::PLAN_CONNECTED_NODE  => 3,
		self::PLAN_MARKETPLACE     => 4,
	);

	private const PLAN_LABELS = array(
		self::PLAN_FREE            => 'Free',
		self::PLAN_PRO             => 'Pro Calculator',
		self::PLAN_PREFLIGHT       => 'Preflight Add-on',
		self::PLAN_CONNECTED_NODE  => 'Connected Node',
		self::PLAN_MARKETPLACE     => 'Marketplace Node',
	);

	private const PLAN_LIMITS = array(
		self::PLAN_FREE => array(
			'monthly_quotes'     => 50,
			'api_pricing'        => false,
			'custom_branding'    => false,
			'pdf_upload'         => false,
			'preflight'          => false,
			'control_plane'      => false,
			'marketplace'        => false,
			'book_templates'     => 1,
		),
		self::PLAN_PRO => array(
			'monthly_quotes'     => -1,
			'api_pricing'        => true,
			'custom_branding'    => true,
			'pdf_upload'         => true,
			'preflight'          => false,
			'control_plane'      => false,
			'marketplace'        => false,
			'book_templates'     => -1,
		),
		self::PLAN_PREFLIGHT => array(
			'monthly_quotes'     => -1,
			'api_pricing'        => true,
			'custom_branding'    => true,
			'pdf_upload'         => true,
			'preflight'          => true,
			'control_plane'      => false,
			'marketplace'        => false,
			'book_templates'     => -1,
		),
		self::PLAN_CONNECTED_NODE => array(
			'monthly_quotes'     => -1,
			'api_pricing'        => true,
			'custom_branding'    => true,
			'pdf_upload'         => true,
			'preflight'          => true,
			'control_plane'      => true,
			'marketplace'        => false,
			'book_templates'     => -1,
		),
		self::PLAN_MARKETPLACE => array(
			'monthly_quotes'     => -1,
			'api_pricing'        => true,
			'custom_branding'    => true,
			'pdf_upload'         => true,
			'preflight'          => true,
			'control_plane'      => true,
			'marketplace'        => true,
			'book_templates'     => -1,
		),
	);

	private const RECHECK_INTERVAL = DAY_IN_SECONDS;

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_notices', array( $this, 'render_upgrade_notices' ) );
		add_action( 'ppp_bpe_daily_license_check', array( $this, 'scheduled_license_check' ) );

		if ( ! wp_next_scheduled( 'ppp_bpe_daily_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'ppp_bpe_daily_license_check' );
		}
	}

	public function register_routes(): void {
		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/license/activate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_activate' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'license_key' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/license/deactivate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_deactivate' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);

		register_rest_route(
			PPP_BPE_Rest::NAMESPACE,
			'/license/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);
	}

	// -------------------------------------------------------------------------
	// License activation / deactivation
	// -------------------------------------------------------------------------

	public function activate( string $license_key ): array|WP_Error {
		if ( '' === $license_key ) {
			return new WP_Error( 'empty_key', __( 'Please enter a license key.', 'printpricepro-bpe' ) );
		}

		$options     = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$api_url     = $options['bpe_api_url'] ?? '';
		$site_url    = get_site_url();

		if ( '' === $api_url ) {
			return $this->activate_offline( $license_key );
		}

		$response = wp_remote_post(
			trailingslashit( $api_url ) . 'api/licenses/activate',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'license_key' => $license_key,
					'site_url'    => $site_url,
					'plugin_version' => PPP_BPE_VERSION,
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'License activation HTTP error: ' . $response->get_error_message() );
			return $this->activate_offline( $license_key );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['plan'] ) ) {
			$message = $body['message'] ?? __( 'License activation failed.', 'printpricepro-bpe' );
			return new WP_Error( 'activation_failed', sanitize_text_field( $message ) );
		}

		$plan = $this->normalize_plan( $body['plan'] ?? '' );

		$license_data = array(
			'key'          => $license_key,
			'plan'         => $plan,
			'status'       => 'active',
			'activated_at' => current_time( 'c' ),
			'expires_at'   => sanitize_text_field( $body['expires_at'] ?? '' ),
			'last_checked' => current_time( 'c' ),
			'customer'     => sanitize_text_field( $body['customer'] ?? '' ),
		);

		update_option( self::OPTION_LICENSE_DATA, $license_data );

		$this->sync_settings_from_plan( $plan, $options );

		return $license_data;
	}

	private function activate_offline( string $license_key ): array {
		$license_data = array(
			'key'          => $license_key,
			'plan'         => self::PLAN_PRO,
			'status'       => 'unverified',
			'activated_at' => current_time( 'c' ),
			'expires_at'   => '',
			'last_checked' => current_time( 'c' ),
			'customer'     => '',
		);

		update_option( self::OPTION_LICENSE_DATA, $license_data );

		$this->log( 'License activated offline (unverified). Will verify on next API check.' );

		return $license_data;
	}

	public function deactivate(): bool {
		$license_data = $this->get_license_data();

		if ( 'inactive' === $license_data['status'] ) {
			return true;
		}

		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$api_url = $options['bpe_api_url'] ?? '';

		if ( '' !== $api_url && '' !== $license_data['key'] ) {
			wp_remote_post(
				trailingslashit( $api_url ) . 'api/licenses/deactivate',
				array(
					'timeout' => 10,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array(
						'license_key' => $license_data['key'],
						'site_url'    => get_site_url(),
					) ),
				)
			);
		}

		update_option( self::OPTION_LICENSE_DATA, self::LICENSE_DEFAULTS );

		return true;
	}

	public function scheduled_license_check(): void {
		$license_data = $this->get_license_data();

		if ( 'inactive' === $license_data['status'] || '' === $license_data['key'] ) {
			return;
		}

		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );
		$api_url = $options['bpe_api_url'] ?? '';

		if ( '' === $api_url ) {
			return;
		}

		$response = wp_remote_post(
			trailingslashit( $api_url ) . 'api/licenses/verify',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'license_key' => $license_data['key'],
					'site_url'    => get_site_url(),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'License verification HTTP error: ' . $response->get_error_message() );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && ! empty( $body['plan'] ) ) {
			$license_data['plan']         = $this->normalize_plan( $body['plan'] );
			$license_data['status']       = 'active';
			$license_data['expires_at']   = sanitize_text_field( $body['expires_at'] ?? $license_data['expires_at'] );
			$license_data['last_checked'] = current_time( 'c' );

			update_option( self::OPTION_LICENSE_DATA, $license_data );
			$this->sync_settings_from_plan( $license_data['plan'], $options );
		} elseif ( 403 === $code || 404 === $code ) {
			$license_data['status']       = 'expired';
			$license_data['last_checked'] = current_time( 'c' );
			update_option( self::OPTION_LICENSE_DATA, $license_data );

			$this->log( 'License verification failed with status ' . $code . '. License marked as expired.' );
		}
	}

	// -------------------------------------------------------------------------
	// Plan & feature gating
	// -------------------------------------------------------------------------

	public function get_plan(): string {
		$data = $this->get_license_data();

		if ( 'active' !== $data['status'] && 'unverified' !== $data['status'] ) {
			return self::PLAN_FREE;
		}

		return $data['plan'];
	}

	public function get_plan_label(): string {
		$plan = $this->get_plan();
		return self::PLAN_LABELS[ $plan ] ?? self::PLAN_LABELS[ self::PLAN_FREE ];
	}

	public function get_plan_limits(): array {
		$plan = $this->get_plan();
		return self::PLAN_LIMITS[ $plan ] ?? self::PLAN_LIMITS[ self::PLAN_FREE ];
	}

	public function has_feature( string $feature ): bool {
		$limits = $this->get_plan_limits();
		return ! empty( $limits[ $feature ] );
	}

	public function plan_at_least( string $minimum_plan ): bool {
		$current_level = self::PLAN_HIERARCHY[ $this->get_plan() ] ?? 0;
		$required_level = self::PLAN_HIERARCHY[ $minimum_plan ] ?? 0;
		return $current_level >= $required_level;
	}

	public function can_use_api_pricing(): bool {
		return $this->has_feature( 'api_pricing' );
	}

	public function can_remove_branding(): bool {
		return $this->has_feature( 'custom_branding' );
	}

	public function can_upload_files(): bool {
		return $this->has_feature( 'pdf_upload' );
	}

	public function can_use_preflight(): bool {
		return $this->has_feature( 'preflight' );
	}

	public function can_use_control_plane(): bool {
		return $this->has_feature( 'control_plane' );
	}

	public function can_use_marketplace(): bool {
		return $this->has_feature( 'marketplace' );
	}

	public function is_active(): bool {
		$data = $this->get_license_data();
		return 'active' === $data['status'] || 'unverified' === $data['status'];
	}

	// -------------------------------------------------------------------------
	// Usage metering
	// -------------------------------------------------------------------------

	public function record_event( string $event ): void {
		$usage = $this->get_usage_data();
		$month = gmdate( 'Y-m' );

		if ( ( $usage['month'] ?? '' ) !== $month ) {
			$usage = array(
				'month'  => $month,
				'events' => array(),
			);
		}

		if ( ! isset( $usage['events'][ $event ] ) ) {
			$usage['events'][ $event ] = 0;
		}

		++$usage['events'][ $event ];

		update_option( self::OPTION_USAGE_DATA, $usage );
	}

	public function get_monthly_event_count( string $event ): int {
		$usage = $this->get_usage_data();
		$month = gmdate( 'Y-m' );

		if ( ( $usage['month'] ?? '' ) !== $month ) {
			return 0;
		}

		return (int) ( $usage['events'][ $event ] ?? 0 );
	}

	public function is_quote_limit_reached(): bool {
		$limits = $this->get_plan_limits();
		$max    = $limits['monthly_quotes'] ?? 50;

		if ( -1 === $max ) {
			return false;
		}

		return $this->get_monthly_event_count( 'quote_calculated' ) >= $max;
	}

	public function get_quote_usage_summary(): array {
		$limits = $this->get_plan_limits();
		$max    = $limits['monthly_quotes'] ?? 50;
		$used   = $this->get_monthly_event_count( 'quote_calculated' );

		return array(
			'used'      => $used,
			'limit'     => $max,
			'unlimited' => -1 === $max,
			'remaining' => -1 === $max ? -1 : max( 0, $max - $used ),
			'percent'   => -1 === $max ? 0 : ( $max > 0 ? min( 100, round( ( $used / $max ) * 100 ) ) : 100 ),
		);
	}

	// -------------------------------------------------------------------------
	// Upgrade prompts
	// -------------------------------------------------------------------------

	public function get_upgrade_triggers(): array {
		$triggers = array();
		$plan     = $this->get_plan();
		$usage    = $this->get_quote_usage_summary();

		if ( self::PLAN_FREE === $plan && $usage['percent'] >= 80 ) {
			$triggers[] = array(
				'type'    => 'volume',
				'message' => sprintf(
					/* translators: 1: used quotes, 2: limit */
					__( 'You have used %1$d of %2$d free quotes this month. Upgrade to Pro for unlimited quotes.', 'printpricepro-bpe' ),
					$usage['used'],
					$usage['limit']
				),
				'cta'     => __( 'Upgrade to Pro', 'printpricepro-bpe' ),
				'plan'    => self::PLAN_PRO,
			);
		}

		if ( self::PLAN_FREE === $plan && $usage['percent'] >= 100 ) {
			$triggers[] = array(
				'type'    => 'limit_reached',
				'message' => __( 'Monthly quote limit reached. Upgrade to Pro for unlimited quotes and API pricing.', 'printpricepro-bpe' ),
				'cta'     => __( 'Upgrade Now', 'printpricepro-bpe' ),
				'plan'    => self::PLAN_PRO,
			);
		}

		if ( $this->plan_at_least( self::PLAN_PRO ) && ! $this->can_use_preflight() ) {
			$file_uploads = $this->get_monthly_event_count( 'files_uploaded' );
			if ( $file_uploads > 5 ) {
				$triggers[] = array(
					'type'    => 'preflight',
					'message' => __( 'Your customers are uploading PDFs. Activate Preflight to detect file errors before production.', 'printpricepro-bpe' ),
					'cta'     => __( 'Add Preflight', 'printpricepro-bpe' ),
					'plan'    => self::PLAN_PREFLIGHT,
				);
			}
		}

		if ( $this->plan_at_least( self::PLAN_PRO ) && ! $this->can_use_control_plane() ) {
			$orders = $this->get_monthly_event_count( 'order_created' );
			if ( $orders > 10 ) {
				$triggers[] = array(
					'type'    => 'node',
					'message' => __( 'Manage production queue, sync orders, and receive marketplace requests. Connect to PrintPrice OS.', 'printpricepro-bpe' ),
					'cta'     => __( 'Become a Node', 'printpricepro-bpe' ),
					'plan'    => self::PLAN_CONNECTED_NODE,
				);
			}
		}

		if ( $this->plan_at_least( self::PLAN_CONNECTED_NODE ) && ! $this->can_use_marketplace() ) {
			$triggers[] = array(
				'type'    => 'marketplace',
				'message' => __( 'Your print house can receive external orders from the PrintPrice federated network. Activate Marketplace Node.', 'printpricepro-bpe' ),
				'cta'     => __( 'Enable Marketplace', 'printpricepro-bpe' ),
				'plan'    => self::PLAN_MARKETPLACE,
			);
		}

		return $triggers;
	}

	public function render_upgrade_notices(): void {
		$screen = get_current_screen();
		if ( null === $screen || false === strpos( $screen->id, 'printpricepro' ) ) {
			return;
		}

		$triggers = $this->get_upgrade_triggers();

		foreach ( $triggers as $trigger ) {
			$settings_url = admin_url( 'admin.php?page=printpricepro-bpe-license' );
			?>
			<div class="notice notice-info is-dismissible ppp-bpe-upgrade-notice" data-trigger="<?php echo esc_attr( $trigger['type'] ); ?>">
				<p>
					<strong>PrintPricePro:</strong>
					<?php echo esc_html( $trigger['message'] ); ?>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary" style="margin-left:10px;vertical-align:baseline;">
						<?php echo esc_html( $trigger['cta'] ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	// -------------------------------------------------------------------------
	// REST callbacks
	// -------------------------------------------------------------------------

	public function rest_activate( WP_REST_Request $request ): WP_REST_Response {
		$license_key = $request->get_param( 'license_key' );
		$result      = $this->activate( $license_key );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		$result['key'] = substr( $result['key'], 0, 4 ) . str_repeat( '*', max( 0, strlen( $result['key'] ) - 8 ) ) . substr( $result['key'], -4 );

		return new WP_REST_Response( $result, 200 );
	}

	public function rest_deactivate( WP_REST_Request $request ): WP_REST_Response {
		$this->deactivate();
		return new WP_REST_Response( array( 'status' => 'deactivated' ), 200 );
	}

	public function rest_status( WP_REST_Request $request ): WP_REST_Response {
		$data  = $this->get_license_data();
		$usage = $this->get_quote_usage_summary();

		$data['key'] = '' !== $data['key']
			? substr( $data['key'], 0, 4 ) . str_repeat( '*', max( 0, strlen( $data['key'] ) - 8 ) ) . substr( $data['key'], -4 )
			: '';

		return new WP_REST_Response(
			array(
				'license'  => $data,
				'plan'     => $this->get_plan(),
				'label'    => $this->get_plan_label(),
				'limits'   => $this->get_plan_limits(),
				'usage'    => $usage,
				'triggers' => $this->get_upgrade_triggers(),
			),
			200
		);
	}

	// -------------------------------------------------------------------------
	// Data access
	// -------------------------------------------------------------------------

	public function get_license_data(): array {
		$data = get_option( self::OPTION_LICENSE_DATA, array() );
		return wp_parse_args( $data, self::LICENSE_DEFAULTS );
	}

	private function get_usage_data(): array {
		return get_option( self::OPTION_USAGE_DATA, array(
			'month'  => gmdate( 'Y-m' ),
			'events' => array(),
		) );
	}

	private function normalize_plan( string $plan ): string {
		if ( isset( self::PLAN_HIERARCHY[ $plan ] ) ) {
			return $plan;
		}

		$map = array(
			'free'             => self::PLAN_FREE,
			'pro'              => self::PLAN_PRO,
			'pro_calculator'   => self::PLAN_PRO,
			'preflight'        => self::PLAN_PREFLIGHT,
			'preflight_addon'  => self::PLAN_PREFLIGHT,
			'connected_node'   => self::PLAN_CONNECTED_NODE,
			'node'             => self::PLAN_CONNECTED_NODE,
			'marketplace'      => self::PLAN_MARKETPLACE,
			'marketplace_node' => self::PLAN_MARKETPLACE,
		);

		return $map[ strtolower( $plan ) ] ?? self::PLAN_FREE;
	}

	private function sync_settings_from_plan( string $plan, array $options ): void {
		$changed = false;

		if ( $this->plan_at_least( self::PLAN_CONNECTED_NODE ) && 'federated_node' !== ( $options['mode'] ?? 'local' ) ) {
			$options['mode'] = 'federated_node';
			$changed         = true;
		} elseif ( $this->plan_at_least( self::PLAN_PRO ) && 'local' === ( $options['mode'] ?? 'local' ) ) {
			$options['mode'] = 'api';
			$changed         = true;
		}

		if ( $this->can_use_preflight() && empty( $options['preflight_enabled'] ) ) {
			$options['preflight_enabled'] = true;
			$changed                      = true;
		}

		if ( $changed ) {
			update_option( PPP_BPE_Settings::OPTION_NAME, $options );
		}
	}

	private function log( string $message ): void {
		$options = get_option( PPP_BPE_Settings::OPTION_NAME, array() );

		if ( empty( $options['debug_mode'] ) ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( '[License] ' . $message, array( 'source' => 'printpricepro-bpe' ) );
		}
	}

	public static function cleanup_on_deactivation(): void {
		wp_clear_scheduled_hook( 'ppp_bpe_daily_license_check' );
	}

	public static function get_all_plans(): array {
		return self::PLAN_LABELS;
	}

	public static function get_plan_features( string $plan ): array {
		return self::PLAN_LIMITS[ $plan ] ?? self::PLAN_LIMITS[ self::PLAN_FREE ];
	}
}
