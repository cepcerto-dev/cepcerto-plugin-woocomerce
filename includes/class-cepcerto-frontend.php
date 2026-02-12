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

		wp_register_script( 'cepcerto-product', plugins_url( 'assets/product-calculator.js', dirname( __FILE__ ) . '/../cepcerto.php' ), array(), '0.1.0', true );
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
		<div class="cepcerto-calculator" style="margin-top: 16px;">
			<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
				<label for="cepcerto-postcode" style="margin: 0;">Calcular frete</label>
				<input type="text" id="cepcerto-postcode" inputmode="numeric" autocomplete="postal-code" placeholder="Digite seu CEP" style="max-width: 180px;" />
				<button type="button" class="button" id="cepcerto-calc-btn" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">Calcular</button>
			</div>
			<div id="cepcerto-result" style="margin-top: 12px;"></div>
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
			$dimensions['length']
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
		$defaultWidth  = (float) get_option( 'cepcerto_default_width', 10 );
		$defaultHeight = (float) get_option( 'cepcerto_default_height', 10 );
		$defaultLength = (float) get_option( 'cepcerto_default_length', 10 );
		$defaultWeight = (float) get_option( 'cepcerto_default_weight', 1 );

		$weight = $product->get_weight();
		$width  = $product->get_width();
		$height = $product->get_height();
		$length = $product->get_length();

		$weight = $this->convert_weight_to_kg( ! empty( $weight ) ? $weight : $defaultWeight );
		$width  = $this->convert_dimension_to_cm( ! empty( $width ) ? $width : $defaultWidth );
		$height = $this->convert_dimension_to_cm( ! empty( $height ) ? $height : $defaultHeight );
		$length = $this->convert_dimension_to_cm( ! empty( $length ) ? $length : $defaultLength );

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
