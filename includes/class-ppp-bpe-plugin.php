<?php
/**
 * Main plugin orchestrator — singleton.
 *
 * @package PrintPricePro_BPE
 */

defined( 'ABSPATH' ) || exit;

final class PPP_BPE_Plugin {

	private static ?self $instance = null;

	private ?PPP_BPE_Settings $settings   = null;
	private ?PPP_BPE_Admin    $admin      = null;
	private ?PPP_BPE_Rest        $rest        = null;
	private ?PPP_BPE_Cart        $cart        = null;
	private ?PPP_BPE_File_Upload $file_upload = null;
	private ?PPP_BPE_Preflight      $preflight      = null;
	private ?PPP_BPE_Control_Plane    $control_plane    = null;
	private ?PPP_BPE_Production_Queue $production_queue = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function init(): void {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_wc_missing' ) );
			return;
		}

		$this->load_classes();

		$this->settings = new PPP_BPE_Settings();
		$this->settings->register_hooks();

		$this->production_queue = new PPP_BPE_Production_Queue();
		$this->production_queue->register_hooks();

		$this->admin = new PPP_BPE_Admin( $this->settings, $this->production_queue );
		$this->admin->register_hooks();

		$this->rest = new PPP_BPE_Rest();
		$this->rest->register_hooks();

		$this->cart = new PPP_BPE_Cart();
		$this->cart->register_hooks();

		$this->file_upload = new PPP_BPE_File_Upload();
		$this->file_upload->register_hooks();

		$this->preflight = new PPP_BPE_Preflight();
		$this->preflight->register_hooks();

		$this->file_upload->set_preflight( $this->preflight );

		$this->control_plane = new PPP_BPE_Control_Plane();
		$this->control_plane->register_hooks();

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	private function load_classes(): void {
		$includes = PPP_BPE_PLUGIN_DIR . 'includes/';

		require_once $includes . 'class-ppp-bpe-settings.php';
		require_once $includes . 'class-ppp-bpe-admin.php';
		require_once $includes . 'class-ppp-bpe-woocommerce.php';
		require_once $includes . 'class-ppp-bpe-calculator.php';
		require_once $includes . 'class-ppp-bpe-offer-signer.php';
		require_once $includes . 'class-ppp-bpe-api-client.php';
		require_once $includes . 'class-ppp-bpe-rest.php';
		require_once $includes . 'class-ppp-bpe-cart.php';
		require_once $includes . 'class-ppp-bpe-file-upload.php';
		require_once $includes . 'class-ppp-bpe-preflight.php';
		require_once $includes . 'class-ppp-bpe-control-plane.php';
		require_once $includes . 'class-ppp-bpe-production-queue.php';
	}

	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		require_once PPP_BPE_PLUGIN_DIR . 'includes/class-ppp-bpe-woocommerce.php';
		PPP_BPE_WooCommerce::create_base_product();
	}

	public static function deactivate(): void {
		// Intentionally empty — do not delete data on deactivation.
	}

	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function admin_notice_wc_missing(): void {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: WooCommerce version requirement */
					esc_html__( 'PrintPricePro BPE for WooCommerce requires WooCommerce %s or later. Please install and activate WooCommerce.', 'printpricepro-bpe' ),
					'8.0'
				);
				?>
			</p>
		</div>
		<?php
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'printpricepro-bpe',
			false,
			dirname( plugin_basename( PPP_BPE_PLUGIN_FILE ) ) . '/languages'
		);
	}

	public function register_shortcode(): void {
		add_shortcode( 'printpricepro_bpe_calculator', array( $this, 'render_calculator' ) );
	}

	public function render_calculator( $atts ): string {
		$atts = shortcode_atts(
			array(
				'product_type'   => 'paperback',
				'mode'           => 'full',
				'default_copies' => 1,
				'country'        => '',
			),
			$atts,
			'printpricepro_bpe_calculator'
		);

		$this->register_public_assets();

		wp_enqueue_style( 'ppp-bpe-public' );
		wp_enqueue_script( 'ppp-bpe-public' );

		$calculator   = new PPP_BPE_Calculator();
		$form_options = $calculator->get_form_options();
		$options      = get_option( PPP_BPE_Settings::OPTION_NAME, array() );

		$default_country  = ! empty( $atts['country'] )
			? strtoupper( sanitize_text_field( $atts['country'] ) )
			: ( $options['default_country'] ?? 'ES' );
		$default_currency = $options['default_currency'] ?? 'EUR';

		$calc_data = wp_json_encode( array(
			'restUrl'        => rest_url( PPP_BPE_Rest::NAMESPACE . '/calculate' ),
			'addToCartUrl'   => rest_url( PPP_BPE_Rest::NAMESPACE . '/add-to-cart' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'currency'       => $default_currency,
			'mode'           => $options['mode'] ?? 'local',
			'defaultCountry' => $default_country,
			'defaultCopies'  => absint( $atts['default_copies'] ),
			'i18n'           => array(
				'calculating'    => __( 'Calculating…', 'printpricepro-bpe' ),
				'calculate'      => __( 'Calculate Price', 'printpricepro-bpe' ),
				'errorGeneric'   => __( 'An error occurred. Please try again.', 'printpricepro-bpe' ),
				'perCopy'        => __( 'per copy', 'printpricepro-bpe' ),
				'total'          => __( 'Total', 'printpricepro-bpe' ),
				'addToCart'      => __( 'Add to Cart', 'printpricepro-bpe' ),
				'addingToCart'   => __( 'Adding…', 'printpricepro-bpe' ),
				'addedToCart'    => __( 'Added to Cart!', 'printpricepro-bpe' ),
				'viewCart'       => __( 'View Cart', 'printpricepro-bpe' ),
				'cartError'      => __( 'Could not add to cart. Please try again.', 'printpricepro-bpe' ),
				'fallbackNotice' => __( 'Estimated price (service temporarily unavailable)', 'printpricepro-bpe' ),
			),
		) );

		wp_add_inline_script(
			'ppp-bpe-public',
			'window.pppBpeCalc = ' . $calc_data . ';',
			'before'
		);

		ob_start();
		include PPP_BPE_PLUGIN_DIR . 'templates/calculator-placeholder.php';
		return ob_get_clean();
	}

	public function register_public_assets(): void {
		wp_register_style(
			'ppp-bpe-public',
			PPP_BPE_PLUGIN_URL . 'public/css/ppp-bpe-public.css',
			array(),
			PPP_BPE_VERSION
		);

		wp_register_script(
			'ppp-bpe-public',
			PPP_BPE_PLUGIN_URL . 'public/js/ppp-bpe-public.js',
			array(),
			PPP_BPE_VERSION,
			true
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( null === $this->admin ) {
			return;
		}

		if ( ! in_array( $hook, $this->admin->get_page_hooks(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'ppp-bpe-admin',
			PPP_BPE_PLUGIN_URL . 'admin/css/ppp-bpe-admin.css',
			array(),
			PPP_BPE_VERSION
		);

		wp_enqueue_script(
			'ppp-bpe-admin',
			PPP_BPE_PLUGIN_URL . 'admin/js/ppp-bpe-admin.js',
			array(),
			PPP_BPE_VERSION,
			true
		);
	}
}
