<?php
/**
 * CepCerto Frontend Class.
 *
 * Handles frontend shipping calculator functionality.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend Class.
 *
 * @since 1.0.0
 */
class CepCerto_Frontend {

	/**
	 * AJAX action for shipping calculator.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const AJAX_ACTION = 'cepcerto_calculate_product_shipping';

	/**
	 * Initialize frontend features.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_calculator' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_calculate' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_calculate' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_register_style(
			'cepcerto-product',
			CEPCERTO_PLUGIN_URL . 'assets/product-calculator.css',
			array(),
			CEPCERTO_VERSION
		);
		wp_enqueue_style( 'cepcerto-product' );

		wp_register_script(
			'cepcerto-product',
			CEPCERTO_PLUGIN_URL . 'assets/product-calculator.js',
			array(),
			CEPCERTO_VERSION,
			true
		);
		wp_enqueue_script( 'cepcerto-product' );

		wp_localize_script(
			'cepcerto-product',
			'CepCertoCalculator',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( 'cepcerto_calculator' ),
			)
		);
	}

	/**
	 * Render shipping calculator on product page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_calculator() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( empty( $product ) || ! $product instanceof WC_Product ) {
			return;
		}

		?>
		<div class="cepcerto-calculator">
			<div class="cepcerto-calculator__header">
				<span class="cepcerto-calculator__icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
				</span>
				<span class="cepcerto-calculator__title"><?php esc_html_e( 'Calcular frete e prazo', 'cepcerto' ); ?></span>
			</div>
			<div class="cepcerto-calculator__form">
				<input
					type="text"
					id="cepcerto-postcode"
					class="cepcerto-calculator__input"
					inputmode="numeric"
					autocomplete="postal-code"
					placeholder="<?php esc_attr_e( '00000-000', 'cepcerto' ); ?>"
					maxlength="9"
				/>
				<button
					type="button"
					id="cepcerto-calc-btn"
					class="cepcerto-calculator__btn"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
				><?php esc_html_e( 'Calcular', 'cepcerto' ); ?></button>
			</div>
			<span class="cepcerto-calculator__link">
				<a href="<?php echo esc_url( CepCerto_Api::URL_BUSCA_CEP_CORREIOS ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Não sei meu CEP', 'cepcerto' ); ?>
				</a>
			</span>
			<div id="cepcerto-result" class="cepcerto-result"></div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX shipping calculation request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_calculate() {
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log(
				'info',
				'AJAX request recebido',
				array(
					'action'  => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
					'nonce'   => isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '',
					'product_id' => isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '',
					'postcode'   => isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '',
				)
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? (string) wp_unslash( $_POST['nonce'] ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_calculator' ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX nonce inválido', array( 'action' => self::AJAX_ACTION ) );
			}
			wp_send_json_error(
				array(
					'message' => __( 'Nonce inválido. Recarregue a página e tente novamente.', 'cepcerto' ),
				),
				400
			);
		}

		$productId = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$cepDestino = isset( $_POST['postcode'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['postcode'] ) ) : '';

		if ( empty( $productId ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX produto inválido', array( 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => __( 'Produto inválido.', 'cepcerto' ) ), 400 );
		}

		if ( 8 !== strlen( $cepDestino ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX CEP inválido', array( 'postcode' => $cepDestino, 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => __( 'CEP inválido.', 'cepcerto' ) ), 400 );
		}

		$originCep = preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_origin_cep', '' ) );
		if ( 8 !== strlen( $originCep ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX CEP origem não configurado', array( 'origin' => $originCep, 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => __( 'CEP de origem não configurado.', 'cepcerto' ) ), 400 );
		}

		$product = wc_get_product( $productId );
		if ( ! $product ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX produto não encontrado', array( 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => __( 'Produto não encontrado.', 'cepcerto' ) ), 404 );
		}

		$dimensions = $this->get_product_dimensions( $product );
		if ( empty( $dimensions ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX sem dimensões/peso', array( 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => __( 'Não foi possível obter peso/dimensões do produto.', 'cepcerto' ) ), 400 );
		}

		$productPrice = (float) wc_get_price_to_display( $product );
		$minOrderValue = (float) get_option( 'cepcerto_min_order_value', 50 );
		$baseValorEncomenda = $productPrice > 0 ? $productPrice : $minOrderValue;
		$valorEncomenda = max( $minOrderValue, min( 35000, $baseValorEncomenda ) );

		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log(
				'info',
				'AJAX calcular frete produto',
				array(
					'product_id'   => $productId,
					'origin'       => $originCep,
					'destination'  => $cepDestino,
					'dimensions'   => $dimensions,
				)
			);
		}

		$api = new CepCerto_Api();
		$result = $api->quote_get(
			$originCep,
			$cepDestino,
			$dimensions['weight'],
			$dimensions['height'],
			$dimensions['width'],
			$dimensions['length'],
			$valorEncomenda
		);

		if ( is_wp_error( $result ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'error', 'AJAX erro na cotação', array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'origin'      => $originCep,
				'destination' => $cepDestino,
				'quote'       => $result,
			),
			200
		);
	}

	/**
	 * Get product dimensions and weight.
	 *
	 * @since 1.0.0
	 * @param WC_Product $product Product object.
	 * @return array|false Product dimensions or false on failure.
	 */
	private function get_product_dimensions( $product ) {
		$defaultWidth  = $this->to_float( get_option( 'cepcerto_default_width', 10 ) );
		$defaultHeight = $this->to_float( get_option( 'cepcerto_default_height', 10 ) );
		$defaultLength = $this->to_float( get_option( 'cepcerto_default_length', 10 ) );
		$defaultWeight = $this->to_float( get_option( 'cepcerto_default_weight', 1 ) );

		$productWeight = 0.0;
		if ( $product instanceof WC_Product ) {
			$productWeight = $this->to_float( $product->get_weight() );
		}
		$weight = $productWeight > 0 ? $this->convert_weight_to_kg( $productWeight ) : $this->convert_weight_to_kg( $defaultWeight );
		$width  = $this->convert_dimension_to_cm( $defaultWidth );
		$height = $this->convert_dimension_to_cm( $defaultHeight );
		$length = $this->convert_dimension_to_cm( $defaultLength );

		if ( $weight <= 0 || $width <= 0 || $height <= 0 || $length <= 0 ) {
			return false;
		}

		return array(
			'weight' => $this->format_number( $weight ),
			'width'  => $this->format_number( $width ),
			'height' => $this->format_number( $height ),
			'length' => $this->format_number( $length ),
		);
	}

	/**
	 * Convert value to float.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to convert.
	 * @return float Converted value.
	 */
	private function to_float( $value ) {
		$value = (string) $value;
		$value = str_replace( ',', '.', $value );
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		if ( '' === $value || '-' === $value ) {
			return 0.0;
		}
		return (float) $value;
	}

	/**
	 * Convert dimension to centimeters.
	 *
	 * @since 1.0.0
	 * @param float $value Dimension value.
	 * @return float Converted value in cm.
	 */
	private function convert_dimension_to_cm( $value ) {
		$unit  = strtolower( (string) get_option( 'woocommerce_dimension_unit', 'cm' ) );
		$value = (float) str_replace( ',', '.', (string) $value );

		if ( 'm' === $unit ) {
			return $value * 100;
		}
		if ( 'mm' === $unit ) {
			return $value / 10;
		}

		return $value;
	}

	/**
	 * Convert weight to kilograms.
	 *
	 * @since 1.0.0
	 * @param float $value Weight value.
	 * @return float Converted value in kg.
	 */
	private function convert_weight_to_kg( $value ) {
		$unit  = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
		$value = (float) str_replace( ',', '.', (string) $value );

		if ( 'g' === $unit ) {
			return $value / 1000;
		}

		return $value;
	}

	/**
	 * Format number for API request.
	 *
	 * @since 1.0.0
	 * @param float $value Value to format.
	 * @return string Formatted number.
	 */
	private function format_number( $value ) {
		$value = (float) $value;
		return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
	}
}
