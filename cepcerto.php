<?php
/**
 * Plugin Name: CepCerto
 * Plugin URI: https://cepcerto.com/
 * Description: Plugin para cotação de fretes utilizando a API do CepCerto.
 * Version: 1.0.0
 * Author: CepCerto
 * Author URI: https://cepcerto.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cepcerto
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Tested up to: 6.9
 * WC requires at least: 7.0
 * WC tested up to: 9.4
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
 * Sets up the plugin installation but does NOT automatically register with CepCerto API.
 * Registration requires user consent via setup page.
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

	// Set installation status to pending consent.
	$install_status = get_option( 'cepcerto_install_status', '' );
	if ( empty( $install_status ) ) {
		update_option( 'cepcerto_install_status', 'pending_consent' );
	}
}

register_activation_hook( CEPCERTO_PLUGIN_FILE, 'cepcerto_activate_plugin' );

/**
 * Plugin deactivation hook.
 *
 * Deletes all plugin data from WordPress when plugin is deactivated.
 *
 * @since 1.0.0
 * @return void
 */
function cepcerto_deactivate_plugin() {
	// Delete all plugin options.
	$options = array(
		'cepcerto_token_cliente_postagem',
		'cepcerto_origin_cep',
		'cepcerto_nome_remetente',
		'cepcerto_cpf_cnpj_remetente',
		'cepcerto_whatsapp_remetente',
		'cepcerto_email_remetente',
		'cepcerto_logradouro_remetente',
		'cepcerto_bairro_remetente',
		'cepcerto_numero_endereco_remetente',
		'cepcerto_complemento_remetente',
		'cepcerto_debug',
		'cepcerto_default_width',
		'cepcerto_default_height',
		'cepcerto_default_length',
		'cepcerto_default_weight',
		'cepcerto_min_order_value',
		'cepcerto_display_locations',
		'cepcerto_install_status',
		'cepcerto_consent_given',
		'cepcerto_consent_date',
		'cepcerto_consent_email',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during deactivation.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_cepcerto_' ) . '%'
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during deactivation.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_cepcerto_' ) . '%'
		)
	);
}

register_deactivation_hook( CEPCERTO_PLUGIN_FILE, 'cepcerto_deactivate_plugin' );

/**
 * Process consent form submission and register installation.
 *
 * @since 1.0.0
 * @return void
 */
function cepcerto_process_consent() {
	if ( ! isset( $_POST['cepcerto_consent_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cepcerto_consent_nonce'] ) ), 'cepcerto_consent_action' ) ) {
		return;
	}

	if ( ! isset( $_POST['cepcerto_consent'] ) || 'yes' !== sanitize_text_field( wp_unslash( $_POST['cepcerto_consent'] ) ) ) {
		add_settings_error(
			'cepcerto_setup',
			'cepcerto_consent_required',
			__( 'Você precisa concordar com os termos de uso para ativar o plugin.', 'cepcerto' ),
			'error'
		);
		return;
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
		add_settings_error(
			'cepcerto_setup',
			'cepcerto_api_error',
			$result->get_error_message(),
			'error'
		);
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log( 'error', 'Falha ao registrar instalação', array( 'error' => $result->get_error_message() ) );
		}
		return;
	}

	// Mark as activated.
	update_option( 'cepcerto_install_status', 'activated' );
	update_option( 'cepcerto_consent_given', true );
	update_option( 'cepcerto_consent_date', current_time( 'mysql' ) );
	update_option( 'cepcerto_consent_email', $email );

	// Redirect to reload the page and unlock all features.
	wp_safe_redirect( admin_url( 'admin.php?page=cepcerto&cc_activated=1' ) );
	exit;
}
add_action( 'admin_init', 'cepcerto_process_consent' );

/**
 * Check if plugin has consent to operate.
 *
 * @since 1.0.0
 * @return bool
 */
function cepcerto_has_consent() {
	return 'activated' === get_option( 'cepcerto_install_status', '' ) && get_option( 'cepcerto_consent_given', false );
}

/**
 * Display success notice after activation redirect.
 *
 * @since 1.0.0
 * @return void
 */
function cepcerto_activation_success_notice() {
	$activated = isset( $_GET['cc_activated'] ) ? sanitize_key( wp_unslash( $_GET['cc_activated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '1' === $activated ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'CepCerto ativado com sucesso! Token gerado e funcionalidades liberadas.', 'cepcerto' ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'cepcerto_activation_success_notice' );

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
	 * WordPress automatically loads translations for plugins hosted on WordPress.org.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		// Translations are automatically loaded by WordPress for plugins on WordPress.org.
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

		// Check for consent before loading full functionality.
		$has_consent = cepcerto_has_consent();

		// Load plugin classes.
		$this->load_classes();

		// Initialize admin features.
		if ( is_admin() ) {
			if ( $has_consent ) {
				( new CepCerto_Admin() )->init();
			} else {
				// Show limited admin with setup page.
				add_action( 'admin_menu', array( $this, 'register_menu_with_setup' ) );
				add_action( 'admin_notices', array( $this, 'consent_required_notice' ) );
			}
		}

		// Only enable frontend features if consent given.
		if ( $has_consent ) {
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
	}

	/**
	 * Register menu with setup page (shown when consent is pending).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menu_with_setup() {
		add_menu_page(
			'CepCerto',
			'CepCerto',
			'manage_woocommerce',
			'cepcerto',
			array( $this, 'render_setup_page' ),
			'dashicons-location-alt'
		);
	}

	/**
	 * Render the consent/setup page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_setup_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$email = sanitize_email( get_option( 'admin_email', '' ) );
		$nome  = sanitize_text_field( get_option( 'blogname', '' ) );

		settings_errors( 'cepcerto_setup' );
		?>
		<div class="wrap">
			<div style="max-width: 600px; margin-top: 30px;">
				<div style="display:flex; align-items:center; gap: 10px; margin-bottom: 20px;">
					<img src="<?php echo esc_url( plugins_url( 'cepcerto/assets/logo-cepcerto.svg', __DIR__ ) ); ?>" alt="CepCerto" style="height: 40px; width: auto;" />
					<h1 style="margin: 0;"><?php echo esc_html__( 'Bem-vindo ao CepCerto', 'cepcerto' ); ?></h1>
				</div>

				<div class="card" style="padding: 20px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Ativação do Plugin', 'cepcerto' ); ?></h2>

					<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 16px; margin: 20px 0;">
						<p style="margin: 0 0 8px 0; font-weight: 600;">
							<?php echo esc_html__( 'E-mail que será utilizado:', 'cepcerto' ); ?>
						</p>
						<code style="font-size: 14px; padding: 4px 8px; background: #fff;"><?php echo esc_html( $email ); ?></code>
					</div>

					<p style="font-size: 14px; line-height: 1.6;">
						<strong><?php echo esc_html__( 'Importante:', 'cepcerto' ); ?></strong>
						<?php echo esc_html__( 'Para que o plugin CepCerto funcione corretamente, é necessário coletar alguns dados da sua loja e gerar um token de acesso à API.', 'cepcerto' ); ?>
					</p>

					<p style="font-size: 14px; line-height: 1.6;">
						<?php echo esc_html__( 'Ao ativar o plugin, os seguintes dados serão compartilhados com a CepCerto:', 'cepcerto' ); ?>
					</p>

					<ul style="font-size: 14px; line-height: 1.6; list-style: disc; margin-left: 20px;">
						<li><?php echo esc_html__( 'E-mail do administrador', 'cepcerto' ); ?></li>
						<li><?php echo esc_html__( 'Nome da loja', 'cepcerto' ); ?></li>
						<li><?php echo esc_html__( 'URL do site', 'cepcerto' ); ?></li>
					</ul>

					<hr style="margin: 20px 0; border: none; border-top: 1px solid #c3c4c7;">

					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cepcerto' ) ); ?>">
						<?php wp_nonce_field( 'cepcerto_consent_action', 'cepcerto_consent_nonce' ); ?>

						<div style="margin: 20px 0;">
							<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="cepcerto_consent" value="yes" style="margin-top: 2px;" required />
								<span style="font-size: 14px; line-height: 1.5;">
									<?php
									echo wp_kses(
										__( 'Li e concordo com os <a href="https://cepcerto.com/termo-servico" target="_blank">Termos de Uso</a> e autorizo a coleta dos dados acima mencionados para geração do token de acesso e funcionamento do plugin.', 'cepcerto' ),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
											),
										)
									);
									?>
								</span>
							</label>
						</div>

						<p class="submit" style="margin-top: 20px;">
							<button type="submit" class="button button-primary button-hero" style="font-size: 16px; padding: 8px 24px;">
								<?php echo esc_html__( 'Ativar CepCerto', 'cepcerto' ); ?>
							</button>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display consent required notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function consent_required_notice() {
		// Don't show notice if user is already on the cepcerto setup page.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'cepcerto' === $page ) {
			return;
		}

		$setup_url = admin_url( 'admin.php?page=cepcerto' );
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Setup page URL */
						__( '<strong>CepCerto</strong> aguardando ativação. <a href="%s">Clique aqui para ativar</a> e gerar seu token de acesso.', 'cepcerto' ),
						esc_url( $setup_url )
					)
				);
				?>
			</p>
		</div>
		<?php
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
	 * @param array $package Package information (unused, required by filter signature).
	 * @return array Ordered shipping rates.
	 */
	public function order_rates_by_price( $rates, $package ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Package parameter is required by WooCommerce filter but not used in this implementation.
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
