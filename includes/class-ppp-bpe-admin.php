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
	private array $page_hooks = array();

	public function __construct( PPP_BPE_Settings $settings, PPP_BPE_Production_Queue $production_queue ) {
		$this->settings         = $settings;
		$this->production_queue = $production_queue;
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
