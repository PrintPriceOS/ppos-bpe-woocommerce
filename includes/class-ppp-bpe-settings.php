<?php
/**
 * Plugin settings management via WordPress Settings API.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Settings {

	public const OPTION_GROUP = 'ppp_bpe_settings';
	public const OPTION_NAME  = 'ppp_bpe_options';

	private const DEFAULTS = array(
		'mode'                 => 'local',
		'bpe_api_url'          => '',
		'license_key'          => '',
		'tenant_id'            => '',
		'node_id'              => '',
		'webhook_secret'       => '',
		'control_plane_url'    => '',
		'node_api_key'         => '',
		'default_currency'     => 'EUR',
		'default_country'      => 'ES',
		'max_upload_size_mb'   => 100,
		'preflight_enabled'    => false,
		'preflight_api_url'    => '',
		'preflight_auto_start' => false,
		'debug_mode'           => false,
	);

	private const VALID_MODES = array( 'local', 'api', 'federated_node' );

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::DEFAULTS,
			)
		);

		add_settings_section(
			'ppp_bpe_general',
			__( 'General Settings', 'printpricepro-bpe' ),
			null,
			'printpricepro-bpe'
		);

		add_settings_field(
			'ppp_bpe_mode',
			__( 'Mode', 'printpricepro-bpe' ),
			array( $this, 'render_mode_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_api_url',
			__( 'BPE API URL', 'printpricepro-bpe' ),
			array( $this, 'render_api_url_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_license_key',
			__( 'License Key', 'printpricepro-bpe' ),
			array( $this, 'render_license_key_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_tenant_id',
			__( 'Tenant ID', 'printpricepro-bpe' ),
			array( $this, 'render_tenant_id_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_node_id',
			__( 'Node ID', 'printpricepro-bpe' ),
			array( $this, 'render_node_id_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_currency',
			__( 'Default Currency', 'printpricepro-bpe' ),
			array( $this, 'render_currency_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_country',
			__( 'Default Country', 'printpricepro-bpe' ),
			array( $this, 'render_country_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_max_upload_size',
			__( 'Max Upload Size (MB)', 'printpricepro-bpe' ),
			array( $this, 'render_max_upload_size_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_field(
			'ppp_bpe_webhook_secret',
			__( 'Webhook Secret', 'printpricepro-bpe' ),
			array( $this, 'render_webhook_secret_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);

		add_settings_section(
			'ppp_bpe_control_plane',
			__( 'Control Plane / Node Settings', 'printpricepro-bpe' ),
			array( $this, 'render_control_plane_section' ),
			'printpricepro-bpe'
		);

		add_settings_field(
			'ppp_bpe_control_plane_url',
			__( 'Control Plane URL', 'printpricepro-bpe' ),
			array( $this, 'render_control_plane_url_field' ),
			'printpricepro-bpe',
			'ppp_bpe_control_plane'
		);

		add_settings_field(
			'ppp_bpe_node_api_key',
			__( 'Node API Key', 'printpricepro-bpe' ),
			array( $this, 'render_node_api_key_field' ),
			'printpricepro-bpe',
			'ppp_bpe_control_plane'
		);

		add_settings_section(
			'ppp_bpe_preflight',
			__( 'Preflight Settings', 'printpricepro-bpe' ),
			null,
			'printpricepro-bpe'
		);

		add_settings_field(
			'ppp_bpe_preflight_enabled',
			__( 'Enable Preflight', 'printpricepro-bpe' ),
			array( $this, 'render_preflight_enabled_field' ),
			'printpricepro-bpe',
			'ppp_bpe_preflight'
		);

		add_settings_field(
			'ppp_bpe_preflight_api_url',
			__( 'Preflight API URL', 'printpricepro-bpe' ),
			array( $this, 'render_preflight_api_url_field' ),
			'printpricepro-bpe',
			'ppp_bpe_preflight'
		);

		add_settings_field(
			'ppp_bpe_preflight_auto_start',
			__( 'Auto-start Preflight', 'printpricepro-bpe' ),
			array( $this, 'render_preflight_auto_start_field' ),
			'printpricepro-bpe',
			'ppp_bpe_preflight'
		);

		add_settings_field(
			'ppp_bpe_debug',
			__( 'Debug Mode', 'printpricepro-bpe' ),
			array( $this, 'render_debug_field' ),
			'printpricepro-bpe',
			'ppp_bpe_general'
		);
	}

	public function sanitize_options( array $input ): array {
		$sanitized = array();

		$sanitized['mode'] = isset( $input['mode'] ) && in_array( $input['mode'], self::VALID_MODES, true )
			? $input['mode']
			: 'local';

		$sanitized['bpe_api_url'] = isset( $input['bpe_api_url'] )
			? esc_url_raw( trim( $input['bpe_api_url'] ) )
			: '';

		$sanitized['license_key'] = isset( $input['license_key'] )
			? substr( sanitize_text_field( $input['license_key'] ), 0, 128 )
			: '';

		$sanitized['tenant_id'] = isset( $input['tenant_id'] )
			? substr( sanitize_text_field( $input['tenant_id'] ), 0, 64 )
			: '';

		$sanitized['node_id'] = isset( $input['node_id'] )
			? substr( sanitize_text_field( $input['node_id'] ), 0, 64 )
			: '';

		$sanitized['default_currency'] = isset( $input['default_currency'] )
			? strtoupper( substr( sanitize_text_field( $input['default_currency'] ), 0, 3 ) )
			: 'EUR';

		$sanitized['default_country'] = isset( $input['default_country'] )
			? strtoupper( substr( sanitize_text_field( $input['default_country'] ), 0, 2 ) )
			: 'ES';

		$sanitized['max_upload_size_mb'] = isset( $input['max_upload_size_mb'] )
			? max( 1, min( 500, absint( $input['max_upload_size_mb'] ) ) )
			: 100;

		$sanitized['webhook_secret'] = isset( $input['webhook_secret'] )
			? substr( sanitize_text_field( $input['webhook_secret'] ), 0, 128 )
			: '';

		$sanitized['control_plane_url'] = isset( $input['control_plane_url'] )
			? esc_url_raw( trim( $input['control_plane_url'] ) )
			: '';

		$sanitized['node_api_key'] = isset( $input['node_api_key'] )
			? substr( sanitize_text_field( $input['node_api_key'] ), 0, 128 )
			: '';

		$sanitized['preflight_enabled'] = ! empty( $input['preflight_enabled'] );

		$sanitized['preflight_api_url'] = isset( $input['preflight_api_url'] )
			? esc_url_raw( trim( $input['preflight_api_url'] ) )
			: '';

		$sanitized['preflight_auto_start'] = ! empty( $input['preflight_auto_start'] );

		$sanitized['debug_mode'] = ! empty( $input['debug_mode'] );

		return $sanitized;
	}

	public function render_settings_form(): void {
		?>
		<div class="wrap ppp-bpe-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'printpricepro-bpe' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_mode_field(): void {
		$options = $this->get_all_options();
		$mode    = $options['mode'];
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode]">
			<option value="local" <?php selected( $mode, 'local' ); ?>>
				<?php esc_html_e( 'Local', 'printpricepro-bpe' ); ?>
			</option>
			<option value="api" <?php selected( $mode, 'api' ); ?>>
				<?php esc_html_e( 'API', 'printpricepro-bpe' ); ?>
			</option>
			<option value="federated_node" <?php selected( $mode, 'federated_node' ); ?>>
				<?php esc_html_e( 'Federated Node', 'printpricepro-bpe' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Local: standalone calculator. API: connect to BPE service. Federated Node: full PrintPrice OS integration.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_api_url_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="url"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[bpe_api_url]"
			value="<?php echo esc_attr( $options['bpe_api_url'] ); ?>"
			class="regular-text"
			placeholder="https://api.printpricepro.com" />
		<p class="description">
			<?php esc_html_e( 'Base URL of the PrintPricePro BPE API. Required for API and Federated Node modes.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_license_key_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[license_key]"
			value="<?php echo esc_attr( $options['license_key'] ); ?>"
			class="regular-text"
			autocomplete="off" />
		<p class="description">
			<?php esc_html_e( 'Your PrintPricePro license key. Never exposed to the frontend.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_tenant_id_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[tenant_id]"
			value="<?php echo esc_attr( $options['tenant_id'] ); ?>"
			class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'PrintPrice OS tenant identifier. Required for Federated Node mode.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_node_id_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[node_id]"
			value="<?php echo esc_attr( $options['node_id'] ); ?>"
			class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'Node identifier assigned by PrintPrice OS. Required for Federated Node mode.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_currency_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_currency]"
			value="<?php echo esc_attr( $options['default_currency'] ); ?>"
			class="small-text"
			maxlength="3"
			placeholder="EUR" />
		<p class="description">
			<?php esc_html_e( 'ISO 4217 currency code (e.g., EUR, USD, GBP).', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_country_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_country]"
			value="<?php echo esc_attr( $options['default_country'] ); ?>"
			class="small-text"
			maxlength="2"
			placeholder="ES" />
		<p class="description">
			<?php esc_html_e( 'ISO 3166-1 alpha-2 country code (e.g., ES, DE, US).', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_max_upload_size_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_upload_size_mb]"
			value="<?php echo esc_attr( $options['max_upload_size_mb'] ); ?>"
			class="small-text"
			min="1"
			max="500"
			step="1" />
		<p class="description">
			<?php esc_html_e( 'Maximum file size in megabytes for PDF uploads (1–500 MB). Ensure your server php.ini upload_max_filesize and post_max_size allow this.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_webhook_secret_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[webhook_secret]"
			value="<?php echo esc_attr( $options['webhook_secret'] ); ?>"
			class="regular-text"
			autocomplete="off" />
		<p class="description">
			<?php esc_html_e( 'Shared secret for verifying incoming webhook signatures (HMAC-SHA256). Used by Preflight and Control Plane callbacks.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_preflight_enabled_field(): void {
		$options = $this->get_all_options();
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[preflight_enabled]"
				value="1"
				<?php checked( $options['preflight_enabled'] ); ?> />
			<?php esc_html_e( 'Enable preflight file validation', 'printpricepro-bpe' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Requires API or Federated Node mode. Sends uploaded PDFs to the Preflight service for QA checks before production.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_preflight_api_url_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="url"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[preflight_api_url]"
			value="<?php echo esc_attr( $options['preflight_api_url'] ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( $options['bpe_api_url'] ); ?>" />
		<p class="description">
			<?php esc_html_e( 'Base URL for the Preflight service. Leave empty to use the BPE API URL.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_preflight_auto_start_field(): void {
		$options = $this->get_all_options();
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[preflight_auto_start]"
				value="1"
				<?php checked( $options['preflight_auto_start'] ); ?> />
			<?php esc_html_e( 'Automatically start preflight when both files are uploaded', 'printpricepro-bpe' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, preflight starts immediately after the customer uploads both Interior and Cover PDFs. Otherwise, preflight must be triggered manually.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_control_plane_section(): void {
		?>
		<p class="description">
			<?php esc_html_e( 'These settings are required for Federated Node mode. They connect your print house to the PrintPrice OS Control Plane for order sync, file dispatch, and production tracking.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_control_plane_url_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="url"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[control_plane_url]"
			value="<?php echo esc_attr( $options['control_plane_url'] ); ?>"
			class="regular-text"
			placeholder="https://cp.printpricepro.com" />
		<p class="description">
			<?php esc_html_e( 'Base URL of the PrintPrice OS Control Plane. Required for Federated Node mode.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_node_api_key_field(): void {
		$options = $this->get_all_options();
		?>
		<input type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[node_api_key]"
			value="<?php echo esc_attr( $options['node_api_key'] ); ?>"
			class="regular-text"
			autocomplete="off" />
		<p class="description">
			<?php esc_html_e( 'API key for authenticating with the Control Plane. Provided during node onboarding. Never exposed to the frontend.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function render_debug_field(): void {
		$options = $this->get_all_options();
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[debug_mode]"
				value="1"
				<?php checked( $options['debug_mode'] ); ?> />
			<?php esc_html_e( 'Enable debug logging', 'printpricepro-bpe' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Writes diagnostic information to the WooCommerce log.', 'printpricepro-bpe' ); ?>
		</p>
		<?php
	}

	public function get_option( string $key, mixed $default = '' ): mixed {
		$options = $this->get_all_options();
		return $options[ $key ] ?? $default;
	}

	public function get_all_options(): array {
		$options = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $options, self::DEFAULTS );
	}
}
