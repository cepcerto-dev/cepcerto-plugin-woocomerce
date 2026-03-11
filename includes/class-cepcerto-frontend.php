<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CepCerto_Frontend {
	const AJAX_ACTION = 'cepcerto_calculate_product_shipping';

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_calculator' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_calculate' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_calculate' ) );
	}

	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_register_style( 'cepcerto-product', plugins_url( 'assets/product-calculator.css', dirname( __FILE__ ) . '/../cepcerto.php' ), array(), '0.2.0' );
		wp_enqueue_style( 'cepcerto-product' );

		wp_register_script( 'cepcerto-product', plugins_url( 'assets/product-calculator.js', dirname( __FILE__ ) . '/../cepcerto.php' ), array(), '0.2.0', true );
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

	public function render_calculator() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( empty( $product ) ) {
			return;
		}

		?>
		<div class="cepcerto-calculator">
			<div class="cepcerto-calculator__header">
				<span class="cepcerto-calculator__icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
				</span>
				<span class="cepcerto-calculator__title">Calcular frete e prazo</span>
			</div>
			<div class="cepcerto-calculator__form">
				<input
					type="text"
					id="cepcerto-postcode"
					class="cepcerto-calculator__input"
					inputmode="numeric"
					autocomplete="postal-code"
					placeholder="00000-000"
					maxlength="9"
				/>
				<button
					type="button"
					id="cepcerto-calc-btn"
					class="cepcerto-calculator__btn"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
				>Calcular</button>
			</div>
			<span class="cepcerto-calculator__link">
				<a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener noreferrer">Não sei meu CEP</a>
			</span>
			<div id="cepcerto-result" class="cepcerto-result"></div>
		</div>
		<?php
	}

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
			wp_send_json_error( array( 'message' => 'Nonce inválido. Recarregue a página e tente novamente.' ), 400 );
		}

		$productId = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$cepDestino = isset( $_POST['postcode'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['postcode'] ) ) : '';

		if ( empty( $productId ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX produto inválido', array( 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => 'Produto inválido.' ), 400 );
		}

		if ( strlen( $cepDestino ) !== 8 ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX CEP inválido', array( 'postcode' => $cepDestino, 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => 'CEP inválido.' ), 400 );
		}

		$originCep = preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_origin_cep', '' ) );
		if ( strlen( $originCep ) !== 8 ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX CEP origem não configurado', array( 'origin' => $originCep, 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => 'CEP de origem não configurado.' ), 400 );
		}

		$product = wc_get_product( $productId );
		if ( ! $product ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX produto não encontrado', array( 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => 'Produto não encontrado.' ), 404 );
		}

		$dimensions = $this->get_product_dimensions( $product );
		if ( empty( $dimensions ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log( 'warning', 'AJAX sem dimensões/peso', array( 'product_id' => $productId ) );
			}
			wp_send_json_error( array( 'message' => 'Não foi possível obter peso/dimensões do produto.' ), 400 );
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

	private function to_float( $value ) {
		$value = (string) $value;
		$value = str_replace( ',', '.', $value );
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		if ( '' === $value || '-' === $value ) {
			return 0.0;
		}
		return (float) $value;
	}

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

	private function convert_weight_to_kg( $value ) {
		$unit  = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
		$value = (float) str_replace( ',', '.', (string) $value );

		if ( 'g' === $unit ) {
			return $value / 1000;
		}

		return $value;
	}

	private function format_number( $value ) {
		$value = (float) $value;
		return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
	}
}
