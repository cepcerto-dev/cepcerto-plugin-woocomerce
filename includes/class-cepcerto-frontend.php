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

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_style(
			'cepcerto-product',
			CEPCERTO_PLUGIN_URL . 'assets/product-calculator' . $suffix . '.css',
			array(),
			CEPCERTO_VERSION
		);
		wp_enqueue_style( 'cepcerto-product' );

		wp_register_script(
			'cepcerto-product',
			CEPCERTO_PLUGIN_URL . 'assets/product-calculator' . $suffix . '.js',
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
					'action'     => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
					'nonce'      => isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '',
					'product_id' => isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '',
					'postcode'   => isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '',
				)
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
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

		$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$cep_destino = isset( $_POST['postcode'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) ) : '';

		if ( empty( $product_id ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX produto inválido', array( 'product_id' => $product_id ) );
			}
			wp_send_json_error( array( 'message' => __( 'Produto inválido.', 'cepcerto' ) ), 400 );
		}

		if ( 8 !== strlen( $cep_destino ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log(
					'warning',
					'AJAX CEP inválido',
					array(
						'postcode'   => $cep_destino,
						'product_id' => $product_id,
					)
				);
			}
			wp_send_json_error( array( 'message' => __( 'CEP inválido.', 'cepcerto' ) ), 400 );
		}

		$origin_cep = preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_origin_cep', '' ) );
		if ( 8 !== strlen( $origin_cep ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log(
					'warning',
					'AJAX CEP origem não configurado',
					array(
						'origin'     => $origin_cep,
						'product_id' => $product_id,
					)
				);
			}
			wp_send_json_error( array( 'message' => __( 'CEP de origem não configurado.', 'cepcerto' ) ), 400 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX produto não encontrado', array( 'product_id' => $product_id ) );
			}
			wp_send_json_error( array( 'message' => __( 'Produto não encontrado.', 'cepcerto' ) ), 404 );
		}

		$dimensions = $this->get_product_dimensions( $product );
		if ( empty( $dimensions ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX sem dimensões/peso', array( 'product_id' => $product_id ) );
			}
			wp_send_json_error( array( 'message' => __( 'Não foi possível obter peso/dimensões do produto.', 'cepcerto' ) ), 400 );
		}

		$product_price        = (float) wc_get_price_to_display( $product );
		$min_order_value      = (float) get_option( 'cepcerto_min_order_value', 50 );
		$base_valor_encomenda = $product_price > 0 ? $product_price : $min_order_value;
		$valor_encomenda      = max( $min_order_value, min( 35000, $base_valor_encomenda ) );

		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log(
				'info',
				'AJAX calcular frete produto',
				array(
					'product_id'  => $product_id,
					'origin'      => $origin_cep,
					'destination' => $cep_destino,
					'dimensions'  => $dimensions,
				)
			);
		}

		$api    = new CepCerto_Api();
		$result = $api->quote_get(
			$origin_cep,
			$cep_destino,
			$dimensions['weight'],
			$dimensions['height'],
			$dimensions['width'],
			$dimensions['length'],
			$valor_encomenda
		);

		if ( is_wp_error( $result ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log(
					'error',
					'AJAX erro na cotação',
					array(
						'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
					)
				);
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'origin'      => $origin_cep,
				'destination' => $cep_destino,
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
		$default_width  = $this->to_float( get_option( 'cepcerto_default_width', 10 ) );
		$default_height = $this->to_float( get_option( 'cepcerto_default_height', 10 ) );
		$default_length = $this->to_float( get_option( 'cepcerto_default_length', 10 ) );
		$default_weight = $this->to_float( get_option( 'cepcerto_default_weight', 1 ) );

		$product_weight = 0.0;
		if ( $product instanceof WC_Product ) {
			$product_weight = $this->to_float( $product->get_weight() );
		}
		$weight = $product_weight > 0 ? $this->convert_weight_to_kg( $product_weight ) : $this->convert_weight_to_kg( $default_weight );
		$width  = $this->convert_dimension_to_cm( $default_width );
		$height = $this->convert_dimension_to_cm( $default_height );
		$length = $this->convert_dimension_to_cm( $default_length );

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
