<?php
/**
 * Admin menu and page rendering.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Admin {

	private PPP_BPE_Settings         $settings;
	private PPP_BPE_Production_Queue $production_queue;
	private PPP_BPE_License          $license;
	private array $page_hooks = array();

	public function __construct( PPP_BPE_Settings $settings, PPP_BPE_Production_Queue $production_queue, PPP_BPE_License $license ) {
		$this->settings         = $settings;
		$this->production_queue = $production_queue;
		$this->license          = $license;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	public function add_admin_menu(): void {
		$this->page_hooks[] = add_menu_page(
			__( 'PrintPricePro', 'printpricepro-bpe' ),
			__( 'PrintPricePro', 'printpricepro-bpe' ),
			'manage_woocommerce',
			'printpricepro-bpe',
			array( $this, 'render_settings_page' ),
			'dashicons-book',
			56
		);

		$this->page_hooks[] = add_submenu_page(
			'printpricepro-bpe',
			__( 'Settings', 'printpricepro-bpe' ),
			__( 'Settings', 'printpricepro-bpe' ),
			'manage_woocommerce',
			'printpricepro-bpe',
			array( $this, 'render_settings_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'printpricepro-bpe',
			__( 'Pricing Rules', 'printpricepro-bpe' ),
			__( 'Pricing Rules', 'printpricepro-bpe' ),
			'manage_woocommerce',
			'printpricepro-bpe-pricing',
			array( $this, 'render_pricing_rules_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'printpricepro-bpe',
			__( 'Production Queue', 'printpricepro-bpe' ),
			__( 'Production Queue', 'printpricepro-bpe' ),
			'manage_woocommerce',
			'printpricepro-bpe-orders',
			array( $this, 'render_orders_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'printpricepro-bpe',
			__( 'License', 'printpricepro-bpe' ),
			__( 'License', 'printpricepro-bpe' ),
			'manage_woocommerce',
			'printpricepro-bpe-license',
			array( $this, 'render_license_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'printpricepro-bpe',
			__( 'Join PrintPrice OS', 'printpricepro-bpe' ),
			__( 'Join PrintPrice OS', 'printpricepro-bpe' ),
			'manage_woocommerce',
			'printpricepro-bpe-join-os',
			array( $this, 'render_join_os_page' )
		);
	}

	public function get_page_hooks(): array {
		return array_filter( $this->page_hooks );
	}

	public function render_settings_page(): void {
		$this->settings->render_settings_form();
	}

	public function render_pricing_rules_page(): void {
		?>
		<div class="wrap ppp-bpe-admin-wrap">
			<h1><?php esc_html_e( 'Pricing Rules', 'printpricepro-bpe' ); ?></h1>
			<div class="ppp-bpe-placeholder-page">
				<h2><?php esc_html_e( 'Coming Soon', 'printpricepro-bpe' ); ?></h2>
				<p><?php esc_html_e( 'Custom pricing rules will be available in a future release. You will be able to configure paper costs, binding costs, margins, quantity breaks, and more.', 'printpricepro-bpe' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function render_orders_page(): void {
		$this->production_queue->render_queue_page();
	}

	public function render_license_page(): void {
		$license_data = $this->license->get_license_data();
		$plan         = $this->license->get_plan();
		$plan_label   = $this->license->get_plan_label();
		$is_active    = $this->license->is_active();
		$usage        = $this->license->get_quote_usage_summary();
		$all_plans    = PPP_BPE_License::get_all_plans();
		$triggers     = $this->license->get_upgrade_triggers();
		?>
		<div class="wrap ppp-bpe-admin-wrap ppp-bpe-license-wrap">
			<h1><?php esc_html_e( 'License & Plan', 'printpricepro-bpe' ); ?></h1>

			<!-- License activation -->
			<div class="ppp-bpe-license-card">
				<h2><?php esc_html_e( 'License Activation', 'printpricepro-bpe' ); ?></h2>

				<?php if ( $is_active ) : ?>
					<div class="ppp-bpe-license-status ppp-bpe-license-active">
						<span class="ppp-bpe-status-dot active"></span>
						<?php
						printf(
							/* translators: %s: license status */
							esc_html__( 'License status: %s', 'printpricepro-bpe' ),
							'<strong>' . esc_html( ucfirst( $license_data['status'] ) ) . '</strong>'
						);
						?>
					</div>
					<table class="form-table ppp-bpe-license-info">
						<tr>
							<th><?php esc_html_e( 'Plan', 'printpricepro-bpe' ); ?></th>
							<td><strong><?php echo esc_html( $plan_label ); ?></strong></td>
						</tr>
						<?php if ( '' !== $license_data['customer'] ) : ?>
						<tr>
							<th><?php esc_html_e( 'Customer', 'printpricepro-bpe' ); ?></th>
							<td><?php echo esc_html( $license_data['customer'] ); ?></td>
						</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Activated', 'printpricepro-bpe' ); ?></th>
							<td><?php echo esc_html( $license_data['activated_at'] ); ?></td>
						</tr>
						<?php if ( '' !== $license_data['expires_at'] ) : ?>
						<tr>
							<th><?php esc_html_e( 'Expires', 'printpricepro-bpe' ); ?></th>
							<td><?php echo esc_html( $license_data['expires_at'] ); ?></td>
						</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Key', 'printpricepro-bpe' ); ?></th>
							<td>
								<code><?php
									$key = $license_data['key'];
									echo esc_html( substr( $key, 0, 4 ) . str_repeat( '*', max( 0, strlen( $key ) - 8 ) ) . substr( $key, -4 ) );
								?></code>
							</td>
						</tr>
					</table>
					<p>
						<button type="button" class="button ppp-bpe-deactivate-license">
							<?php esc_html_e( 'Deactivate License', 'printpricepro-bpe' ); ?>
						</button>
					</p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Enter your PrintPricePro license key to activate Pro features. You are currently on the Free plan.', 'printpricepro-bpe' ); ?></p>
					<div class="ppp-bpe-license-form">
						<input type="text" id="ppp-bpe-license-key" class="regular-text" placeholder="<?php esc_attr_e( 'Enter license key…', 'printpricepro-bpe' ); ?>" />
						<button type="button" class="button button-primary ppp-bpe-activate-license">
							<?php esc_html_e( 'Activate', 'printpricepro-bpe' ); ?>
						</button>
					</div>
					<div id="ppp-bpe-license-message" class="hidden"></div>
				<?php endif; ?>
			</div>

			<!-- Usage -->
			<div class="ppp-bpe-license-card">
				<h2><?php esc_html_e( 'Monthly Usage', 'printpricepro-bpe' ); ?></h2>
				<div class="ppp-bpe-usage-meter">
					<div class="ppp-bpe-usage-bar-wrap">
						<div class="ppp-bpe-usage-bar" style="width:<?php echo esc_attr( $usage['unlimited'] ? 0 : $usage['percent'] ); ?>%"></div>
					</div>
					<p class="ppp-bpe-usage-text">
						<?php if ( $usage['unlimited'] ) : ?>
							<?php
							printf(
								/* translators: %d: number of quotes used */
								esc_html__( '%d quotes this month (unlimited)', 'printpricepro-bpe' ),
								$usage['used']
							);
							?>
						<?php else : ?>
							<?php
							printf(
								/* translators: 1: used quotes, 2: limit */
								esc_html__( '%1$d of %2$d quotes used this month', 'printpricepro-bpe' ),
								$usage['used'],
								$usage['limit']
							);
							?>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<!-- Plan comparison -->
			<div class="ppp-bpe-license-card ppp-bpe-plans-card">
				<h2><?php esc_html_e( 'Plans', 'printpricepro-bpe' ); ?></h2>
				<div class="ppp-bpe-plans-grid">
					<?php foreach ( $all_plans as $plan_key => $plan_name ) :
						$features = PPP_BPE_License::get_plan_features( $plan_key );
						$is_current = ( $plan_key === $plan );
					?>
					<div class="ppp-bpe-plan-col <?php echo $is_current ? 'current' : ''; ?>">
						<h3><?php echo esc_html( $plan_name ); ?></h3>
						<?php if ( $is_current ) : ?>
							<span class="ppp-bpe-plan-badge"><?php esc_html_e( 'Current Plan', 'printpricepro-bpe' ); ?></span>
						<?php endif; ?>
						<ul>
							<li>
								<?php if ( -1 === $features['monthly_quotes'] ) : ?>
									<?php esc_html_e( 'Unlimited quotes', 'printpricepro-bpe' ); ?>
								<?php else : ?>
									<?php
									printf(
										/* translators: %d: number of quotes */
										esc_html__( '%d quotes/month', 'printpricepro-bpe' ),
										$features['monthly_quotes']
									);
									?>
								<?php endif; ?>
							</li>
							<li class="<?php echo $features['api_pricing'] ? 'included' : 'excluded'; ?>">
								<?php esc_html_e( 'API pricing', 'printpricepro-bpe' ); ?>
							</li>
							<li class="<?php echo $features['custom_branding'] ? 'included' : 'excluded'; ?>">
								<?php esc_html_e( 'Custom branding', 'printpricepro-bpe' ); ?>
							</li>
							<li class="<?php echo $features['pdf_upload'] ? 'included' : 'excluded'; ?>">
								<?php esc_html_e( 'PDF upload', 'printpricepro-bpe' ); ?>
							</li>
							<li class="<?php echo $features['preflight'] ? 'included' : 'excluded'; ?>">
								<?php esc_html_e( 'Preflight', 'printpricepro-bpe' ); ?>
							</li>
							<li class="<?php echo $features['control_plane'] ? 'included' : 'excluded'; ?>">
								<?php esc_html_e( 'Control Plane', 'printpricepro-bpe' ); ?>
							</li>
							<li class="<?php echo $features['marketplace'] ? 'included' : 'excluded'; ?>">
								<?php esc_html_e( 'Marketplace', 'printpricepro-bpe' ); ?>
							</li>
						</ul>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( ! empty( $triggers ) ) : ?>
			<!-- Upgrade suggestions -->
			<div class="ppp-bpe-license-card">
				<h2><?php esc_html_e( 'Upgrade Suggestions', 'printpricepro-bpe' ); ?></h2>
				<?php foreach ( $triggers as $trigger ) : ?>
					<div class="ppp-bpe-upgrade-suggestion">
						<p><?php echo esc_html( $trigger['message'] ); ?></p>
						<a href="https://printpricepro.com/pricing" target="_blank" rel="noopener" class="button button-primary">
							<?php echo esc_html( $trigger['cta'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<script>
		(function() {
			var restUrl  = '<?php echo esc_js( rest_url( PPP_BPE_Rest::NAMESPACE ) ); ?>';
			var nonce    = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';

			var activateBtn = document.querySelector('.ppp-bpe-activate-license');
			if (activateBtn) {
				activateBtn.addEventListener('click', function() {
					var key = document.getElementById('ppp-bpe-license-key').value.trim();
					var msg = document.getElementById('ppp-bpe-license-message');
					if (!key) { return; }

					activateBtn.disabled = true;
					activateBtn.textContent = '<?php echo esc_js( __( 'Activating…', 'printpricepro-bpe' ) ); ?>';

					fetch(restUrl + '/license/activate', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
						body: JSON.stringify({ license_key: key })
					})
					.then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
					.then(function(res) {
						if (res.ok) {
							msg.className = 'notice notice-success';
							msg.innerHTML = '<p>' + '<?php echo esc_js( __( 'License activated! Reloading…', 'printpricepro-bpe' ) ); ?>' + '</p>';
							setTimeout(function() { location.reload(); }, 1000);
						} else {
							msg.className = 'notice notice-error';
							msg.innerHTML = '<p>' + (res.data.error || '<?php echo esc_js( __( 'Activation failed.', 'printpricepro-bpe' ) ); ?>') + '</p>';
							activateBtn.disabled = false;
							activateBtn.textContent = '<?php echo esc_js( __( 'Activate', 'printpricepro-bpe' ) ); ?>';
						}
					})
					.catch(function() {
						msg.className = 'notice notice-error';
						msg.innerHTML = '<p><?php echo esc_js( __( 'Network error. Please try again.', 'printpricepro-bpe' ) ); ?></p>';
						activateBtn.disabled = false;
						activateBtn.textContent = '<?php echo esc_js( __( 'Activate', 'printpricepro-bpe' ) ); ?>';
					});
				});
			}

			var deactivateBtn = document.querySelector('.ppp-bpe-deactivate-license');
			if (deactivateBtn) {
				deactivateBtn.addEventListener('click', function() {
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to deactivate your license? You will revert to the Free plan.', 'printpricepro-bpe' ) ); ?>')) {
						return;
					}
					deactivateBtn.disabled = true;
					fetch(restUrl + '/license/deactivate', {
						method: 'POST',
						headers: { 'X-WP-Nonce': nonce }
					}).then(function() { location.reload(); });
				});
			}
		})();
		</script>
		<?php
	}

	public function render_join_os_page(): void {
		$options    = $this->settings->get_all_options();
		$mode       = $options['mode'] ?? 'local';
		$is_node    = PPP_BPE_Control_Plane::is_enabled();
		$node_id    = $options['node_id'] ?? '';
		$tenant_id  = $options['tenant_id'] ?? '';
		?>
		<div class="wrap ppp-bpe-admin-wrap">
			<h1><?php esc_html_e( 'Join PrintPrice OS', 'printpricepro-bpe' ); ?></h1>

			<?php if ( $is_node ) : ?>
				<div class="ppp-bpe-node-status" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="color:#16a34a;margin-top:0;">
						&#10003; <?php esc_html_e( 'Connected to PrintPrice OS', 'printpricepro-bpe' ); ?>
					</h2>
					<table class="form-table" style="margin:0;">
						<tr>
							<th><?php esc_html_e( 'Node ID', 'printpricepro-bpe' ); ?></th>
							<td><code><?php echo esc_html( $node_id ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Tenant ID', 'printpricepro-bpe' ); ?></th>
							<td><code><?php echo esc_html( $tenant_id ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Mode', 'printpricepro-bpe' ); ?></th>
							<td><strong><?php esc_html_e( 'Federated Node', 'printpricepro-bpe' ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Preflight', 'printpricepro-bpe' ); ?></th>
							<td>
								<?php if ( PPP_BPE_Preflight::is_enabled() ) : ?>
									<span style="color:#16a34a;">&#10003; <?php esc_html_e( 'Enabled', 'printpricepro-bpe' ); ?></span>
								<?php else : ?>
									<span style="color:#d97706;"><?php esc_html_e( 'Disabled', 'printpricepro-bpe' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<p style="margin-top:16px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=printpricepro-bpe' ) ); ?>" class="button">
							<?php esc_html_e( 'Manage Settings', 'printpricepro-bpe' ); ?>
						</a>
					</p>
				</div>

				<div class="ppp-bpe-node-features" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;">
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
						<h3 style="margin-top:0;"><?php esc_html_e( 'Order Sync', 'printpricepro-bpe' ); ?></h3>
						<p><?php esc_html_e( 'Orders are automatically synced to the Control Plane when created.', 'printpricepro-bpe' ); ?></p>
					</div>
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
						<h3 style="margin-top:0;"><?php esc_html_e( 'File Dispatch', 'printpricepro-bpe' ); ?></h3>
						<p><?php esc_html_e( 'Production files are synced securely to the federated network.', 'printpricepro-bpe' ); ?></p>
					</div>
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
						<h3 style="margin-top:0;"><?php esc_html_e( 'Marketplace', 'printpricepro-bpe' ); ?></h3>
						<p><?php esc_html_e( 'Receive dispatch packages from the PrintPrice marketplace.', 'printpricepro-bpe' ); ?></p>
					</div>
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
						<h3 style="margin-top:0;"><?php esc_html_e( 'Production Tracking', 'printpricepro-bpe' ); ?></h3>
						<p><?php esc_html_e( 'Production status updates flow between your shop and the OS.', 'printpricepro-bpe' ); ?></p>
					</div>
				</div>

			<?php else : ?>
				<div class="ppp-bpe-join-cta" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;max-width:700px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Become a PrintPrice OS Node', 'printpricepro-bpe' ); ?></h2>
					<p><?php esc_html_e( 'Your calculator is already working. Take the next step and connect to the PrintPrice federated network.', 'printpricepro-bpe' ); ?></p>

					<ul style="list-style:none;padding:0;">
						<li style="padding:8px 0;">&#10003; <?php esc_html_e( 'Sync orders automatically with the Control Plane.', 'printpricepro-bpe' ); ?></li>
						<li style="padding:8px 0;">&#10003; <?php esc_html_e( 'Receive orders from the PrintPrice marketplace.', 'printpricepro-bpe' ); ?></li>
						<li style="padding:8px 0;">&#10003; <?php esc_html_e( 'Activate advanced Preflight file validation.', 'printpricepro-bpe' ); ?></li>
						<li style="padding:8px 0;">&#10003; <?php esc_html_e( 'Secure file dispatch and production tracking.', 'printpricepro-bpe' ); ?></li>
						<li style="padding:8px 0;">&#10003; <?php esc_html_e( 'Expose production capacity to the federated network.', 'printpricepro-bpe' ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'How to connect', 'printpricepro-bpe' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Request a node license from PrintPrice OS.', 'printpricepro-bpe' ); ?></li>
						<li><?php esc_html_e( 'Receive your Tenant ID, Node ID, and API Key.', 'printpricepro-bpe' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: link to settings page */
								esc_html__( 'Enter your credentials in %s and set mode to "Federated Node".', 'printpricepro-bpe' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=printpricepro-bpe' ) ) . '">' . esc_html__( 'Settings', 'printpricepro-bpe' ) . '</a>'
							);
							?>
						</li>
					</ol>

					<p style="margin-top:20px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=printpricepro-bpe' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Configure Node Settings', 'printpricepro-bpe' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
