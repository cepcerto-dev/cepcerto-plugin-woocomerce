<?php
/**
 * Plugin Name: CepCerto
 * Plugin URI: https://cepcerto.com/
 * Description: Plugin para cotação de fretes utilizando a API do CepCerto.
 * Version: 1.0.0
 * Author: CepCerto
 * Author URI: https://cepcerto.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cepcerto
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * WC requires at least: 7.0
 * WC tested up to: 9.2
 * WC requires HPOS: yes
 * WC compatible blocks: yes
 *
 * @package CepCerto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'CEPCERTO_VERSION' ) ) {
	define( 'CEPCERTO_VERSION', '1.0.0' );
}

if ( ! defined( 'CEPCERTO_PLUGIN_FILE' ) ) {
	define( 'CEPCERTO_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'CEPCERTO_PLUGIN_DIR' ) ) {
	define( 'CEPCERTO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CEPCERTO_PLUGIN_URL' ) ) {
	define( 'CEPCERTO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'CEPCERTO_PLUGIN_BASENAME' ) ) {
	define( 'CEPCERTO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Plugin activation hook.
 *
 * Registers the plugin installation with CepCerto API.
 *
 * @since 1.0.0
 * @return void
 */
function cepcerto_activate_plugin() {
	// Check if WooCommerce is active.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		return;
	}

	// Load required classes.
	require_once CEPCERTO_PLUGIN_DIR . 'includes/class-cepcerto-api.php';

	// Get site information.
	$email = sanitize_email( get_option( 'admin_email', '' ) );
	$nome  = sanitize_text_field( get_option( 'blogname', '' ) );

	if ( empty( $nome ) ) {
		$nome = __( 'Loja Usuario', 'cepcerto' );
	}

	// Ensure name has at least two parts.
	$nome_parts = preg_split( '/\s+/', trim( $nome ) );
	$nome_parts = array_values( array_filter( (array) $nome_parts ) );

	if ( count( $nome_parts ) < 2 ) {
		$nome = trim( $nome . ' ' . __( 'Usuario', 'cepcerto' ) );
	}

	// Register installation with CepCerto API.
	$api    = new CepCerto_Api();
	$result = $api->registrar_instalacao( $email, $nome );

	if ( is_wp_error( $result ) ) {
		// Log error if needed.
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log( 'error', 'Falha ao registrar instalação', array( 'error' => $result->get_error_message() ) );
		}
		return;
	}
}

register_activation_hook( CEPCERTO_PLUGIN_FILE, 'cepcerto_activate_plugin' );

/**
 * Main CepCerto Plugin Class.
 *
 * @since 1.0.0
 */
final class CepCerto_Plugin {

	/**
 * Single instance of the class.
	 *
	 * @since 1.0.0
	 * @var CepCerto_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return CepCerto_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 9 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin textdomain for translations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cepcerto', false, dirname( CEPCERTO_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load plugin classes.
		$this->load_classes();

		// Initialize admin features.
		if ( is_admin() ) {
			( new CepCerto_Admin() )->init();
		}

		// Get display locations.
		$display_locations = get_option( 'cepcerto_display_locations', array( 'product', 'checkout' ) );
		if ( ! is_array( $display_locations ) ) {
			$display_locations = array( 'product', 'checkout' );
		}

		// Initialize frontend calculator.
		if ( in_array( 'product', $display_locations, true ) ) {
			if ( ! is_admin() || wp_doing_ajax() ) {
				( new CepCerto_Frontend() )->init();
			}
		}

		// Register shipping methods.
		if ( in_array( 'checkout', $display_locations, true ) ) {
			add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );
			add_filter( 'woocommerce_package_rates', array( $this, 'order_rates_by_price' ), 10, 2 );
		}
	}

	/**
	 * Load plugin classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_classes() {
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-cepcerto-api.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-cepcerto-logger.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-cepcerto-admin.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-cepcerto-frontend.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-wc-cepcerto-shipping.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-wc-cepcerto-shipping-pac.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-wc-cepcerto-shipping-sedex.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-wc-cepcerto-shipping-jadlog-package.php';
		require_once CEPCERTO_PLUGIN_DIR . 'includes/class-wc-cepcerto-shipping-jadlog-dotcom.php';
	}

	/**
	 * Display WooCommerce missing notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: WooCommerce plugin link */
						__( '<strong>CepCerto</strong> requer o %s para funcionar.', 'cepcerto' ),
						'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register CepCerto shipping methods.
	 *
	 * @since 1.0.0
	 * @param array $methods Existing shipping methods.
	 * @return array Modified shipping methods.
	 */
	public function register_shipping_methods( $methods ) {
		$methods['cepcerto_pac']            = 'WC_CepCerto_Shipping_Pac';
		$methods['cepcerto_sedex']          = 'WC_CepCerto_Shipping_Sedex';
		$methods['cepcerto_jadlog_package'] = 'WC_CepCerto_Shipping_Jadlog_Package';
		$methods['cepcerto_jadlog_dotcom']  = 'WC_CepCerto_Shipping_Jadlog_Dotcom';
		return $methods;
	}

	/**
	 * Order shipping rates by price.
	 *
	 * @since 1.0.0
	 * @param array $rates   Shipping rates.
	 * @param array $package Package information.
	 * @return array Ordered shipping rates.
	 */
	public function order_rates_by_price( $rates, $package ) {
		if ( empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		uasort(
			$rates,
			function ( $a, $b ) {
				if ( $a->cost === $b->cost ) {
					return 0;
				}
				return ( $a->cost < $b->cost ) ? -1 : 1;
			}
		);

		return $rates;
	}
}

/**
 * Get the main instance of CepCerto_Plugin.
 *
 * @since 1.0.0
 * @return CepCerto_Plugin
 */
function cepcerto() {
	return CepCerto_Plugin::instance();
}

// Initialize the plugin.
cepcerto();

/**
 * Declare compatibility with WooCommerce features.
 *
 * @since 1.0.0
 * @return void
 */
function cepcerto_declare_wc_compatibility() {
	// Declare compatibility with High-Performance Order Storage (HPOS)
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
	
	// Declare compatibility with WooCommerce Cart & Checkout blocks
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
	
	// Declare compatibility with Product Blocks
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'cepcerto_declare_wc_compatibility' );
