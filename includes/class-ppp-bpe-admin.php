<?php
/**
 * Admin menu and page rendering.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

class PPP_BPE_Admin {

	private PPP_BPE_Settings $settings;
	private array $page_hooks = array();

	public function __construct( PPP_BPE_Settings $settings ) {
		$this->settings = $settings;
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
			__( 'Print Orders', 'printpricepro-bpe' ),
			__( 'Orders', 'printpricepro-bpe' ),
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
		?>
		<div class="wrap ppp-bpe-admin-wrap">
			<h1><?php esc_html_e( 'Print Orders', 'printpricepro-bpe' ); ?></h1>
			<div class="ppp-bpe-placeholder-page">
				<h2><?php esc_html_e( 'Coming Soon', 'printpricepro-bpe' ); ?></h2>
				<p><?php esc_html_e( 'Print order management will be available in a future release. You will be able to track orders through quote, file upload, preflight, and production stages.', 'printpricepro-bpe' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function render_join_os_page(): void {
		?>
		<div class="wrap ppp-bpe-admin-wrap">
			<h1><?php esc_html_e( 'Join PrintPrice OS', 'printpricepro-bpe' ); ?></h1>
			<div class="ppp-bpe-placeholder-page">
				<h2><?php esc_html_e( 'Become a PrintPrice OS Node', 'printpricepro-bpe' ); ?></h2>
				<p><?php esc_html_e( 'Your calculator is already working. Upgrade to receive orders from the PrintPrice federated network, activate Preflight, connect production tracking, and more.', 'printpricepro-bpe' ); ?></p>
				<p><strong><?php esc_html_e( 'Node connection features will be available in a future release.', 'printpricepro-bpe' ); ?></strong></p>
			</div>
		</div>
		<?php
	}
}
