<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Shipping_Method' ) ) {
	abstract class WC_CepCerto_Shipping extends WC_Shipping_Method {
		public $service = '';
		public $title = '';
		public $additional_time = 0;
		public $additional_tax = 0;
		public $percent_tax = 0;

		public function __construct( $instance_id = 0 ) {
			$this->instance_id = absint( $instance_id );

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled         = $this->get_option( 'enabled' );
			$this->title           = $this->get_option( 'title', $this->method_title );
			$this->additional_tax  = $this->get_option( 'additional_tax', '0' );
			$this->percent_tax     = $this->get_option( 'percent_tax', '0' );
			$this->additional_time = $this->get_option( 'additional_time', '0' );

			$this->supports = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);

			add_action(
				'woocommerce_update_options_shipping_' . $this->id,
				array( $this, 'process_admin_options' )
			);
		}

		public function init_form_fields() {
			$this->instance_form_fields = array(
				'enabled'         => array(
					'title'   => 'Ativar',
					'type'    => 'checkbox',
					'label'   => 'Ativar este método de envio',
					'default' => 'yes',
				),
				'title'           => array(
					'title'   => 'Título',
					'type'    => 'text',
					'default' => $this->method_title,
				),
				'additional_tax'  => array(
					'title'       => 'Taxa adicional',
					'type'        => 'text',
					'description' => 'Valor adicional sobre o valor do frete cobrado ao cliente final',
					'desc_tip'    => true,
					'default'     => '0',
					'placeholder' => '0',
				),
				'percent_tax'     => array(
					'title'       => 'Percentual de Taxa adicional',
					'type'        => 'text',
					'description' => 'Adiciona um percentual sobre o valor do frete cobrado ao cliente final',
					'desc_tip'    => true,
					'default'     => '0',
					'placeholder' => '0',
				),
				'additional_time' => array(
					'title'       => 'Dias extras',
					'type'        => 'text',
					'description' => 'Adicional de dias no prazo final do frete',
					'desc_tip'    => true,
					'default'     => '0',
					'placeholder' => '0',
				),
			);
		}

		public function calculate_shipping( $package = array() ) {
			$destinationCep = isset( $package['destination']['postcode'] )
				? preg_replace( '/\D+/', '', (string) $package['destination']['postcode'] )
				: '';

			if ( strlen( $destinationCep ) !== 8 ) {
				return;
			}

			$originCep = preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_origin_cep', '' ) );
			if ( strlen( $originCep ) !== 8 ) {
				return;
			}

			$dimensions = $this->get_package_dimensions( $package );
			if ( empty( $dimensions ) ) {
				return;
			}

			$token = get_option( 'cepcerto_token_cliente_postagem', '' );
			if ( empty( $token ) ) {
				$this->log_error( 'Token de cliente postagem não configurado.' );
				return;
			}

			$cartTotal = WC()->cart ? WC()->cart->get_cart_contents_total() : 0;
			$minOrderValue = (float) get_option( 'cepcerto_min_order_value', 50 );
			$valorEncomenda = max( $minOrderValue, min( 35000, (float) $cartTotal ) );

			$api = new CepCerto_Api();
			$result = $api->quote_frete(
				$token,
				$originCep,
				$destinationCep,
				$dimensions['weight'],
				$dimensions['height'],
				$dimensions['width'],
				$dimensions['length'],
				$valorEncomenda
			);

			if ( is_wp_error( $result ) ) {
				$this->log_error( $result->get_error_message(), array( 'code' => $result->get_error_code() ) );
				return;
			}

			$rate = $this->build_rate_from_response( $result );
			if ( ! $rate ) {
				$this->log_error( 'Resposta da cotação não contém dados para o serviço.', array( 'service' => $this->service, 'response' => $result ) );
				return;
			}

			$this->add_rate( $rate );
		}

		protected function build_rate_from_response( $data ) {
			$service = strtoupper( (string) $this->service );

			if ( is_array( $data ) && isset( $data['response'] ) && is_string( $data['response'] ) ) {
				$decoded = json_decode( $data['response'], true );
				if ( is_array( $decoded ) ) {
					$data = $decoded;
				}
			}

			if ( is_array( $data ) && isset( $data['frete'] ) && is_array( $data['frete'] ) ) {
				$frete = $data['frete'];
				if ( 'PAC' === $service ) {
					$price = isset( $frete['valor_pac'] ) ? (float) str_replace( ',', '.', (string) $frete['valor_pac'] ) : null;
					$days  = isset( $frete['prazo_pac'] ) ? (int) preg_replace( '/\D+/', '', (string) $frete['prazo_pac'] ) : null;
				}
				if ( 'SEDEX' === $service ) {
					$price = isset( $frete['valor_sedex'] ) ? (float) str_replace( ',', '.', (string) $frete['valor_sedex'] ) : null;
					$days  = isset( $frete['prazo_sedex'] ) ? (int) preg_replace( '/\D+/', '', (string) $frete['prazo_sedex'] ) : null;
				}
				if ( 'JADLOG_PACKAGE' === $service ) {
					$price = isset( $frete['valor_jadlog_package'] ) ? (float) str_replace( ',', '.', (string) $frete['valor_jadlog_package'] ) : null;
					$days  = isset( $frete['prazo_jadlog_package'] ) ? (int) preg_replace( '/\D+/', '', (string) $frete['prazo_jadlog_package'] ) : null;
				}
				if ( 'JADLOG_DOTCOM' === $service ) {
					$price = isset( $frete['valor_jadlog_dotcom'] ) ? (float) str_replace( ',', '.', (string) $frete['valor_jadlog_dotcom'] ) : null;
					$days  = isset( $frete['prazo_jadlog_dotcom'] ) ? (int) preg_replace( '/\D+/', '', (string) $frete['prazo_jadlog_dotcom'] ) : null;
				}
			}

			if ( empty( $price ) ) {
				return false;
			}

			$additionalTax = $this->to_float( $this->additional_tax );
			$percentTax    = $this->to_float( $this->percent_tax );
			$extraDays     = (int) $this->additional_time;

			$cost = $price + $additionalTax;
			if ( $percentTax != 0 ) {
				$cost += ( $price * ( $percentTax / 100 ) );
			}

			$label = $this->title;
			if ( ! empty( $days ) ) {
				$totalDays = $days + $extraDays;
				$label    .= sprintf( ' (%d dia(s))', $totalDays );
			}

			return array(
				'id'       => $this->instance_id,
				'label'    => $label,
				'cost'     => max( 0, $cost ),
				'calc_tax' => 'per_item',
				'meta_data' => array(
					'service' => $service,
					'days'    => isset( $days ) ? (int) $days : null,
					'price'   => $price,
				),
			);
		}

		protected function get_package_dimensions( $package ) {
			$default = $this->get_default_dimensions();

			$weight = 0.0;
			$width  = 0.0;
			$height = 0.0;
			$length = 0.0;
			$totalQuantity = 0;
			$defaultWeightKg = $this->convert_weight_to_kg( $default['weight'] );

			if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
				return false;
			}

			foreach ( $package['contents'] as $item ) {
				if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
					continue;
				}

				$product  = $item['data'];
				$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;

				if ( $product->is_virtual() ) {
					continue;
				}

				$totalQuantity += max( 0, $quantity );
				$productWeight = $this->to_float( $product->get_weight() );
				$productWeightKg = $productWeight > 0 ? $this->convert_weight_to_kg( $productWeight ) : $defaultWeightKg;
				$weight += $productWeightKg * max( 0, $quantity );
			}

			if ( $weight <= 0 && $totalQuantity > 0 ) {
				$weight = $defaultWeightKg * $totalQuantity;
			}
			$width  = $this->convert_dimension_to_cm( $default['width'] );
			$height = $this->convert_dimension_to_cm( $default['height'] );
			$length = $this->convert_dimension_to_cm( $default['length'] );

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

		protected function get_default_dimensions() {
			return array(
				'width'  => $this->to_float( get_option( 'cepcerto_default_width', 10 ) ),
				'height' => $this->to_float( get_option( 'cepcerto_default_height', 10 ) ),
				'length' => $this->to_float( get_option( 'cepcerto_default_length', 10 ) ),
				'weight' => $this->to_float( get_option( 'cepcerto_default_weight', 1 ) ),
			);
		}

		protected function convert_dimension_to_cm( $value ) {
			$unit = strtolower( (string) get_option( 'woocommerce_dimension_unit', 'cm' ) );
			$value = (float) str_replace( ',', '.', (string) $value );

			if ( 'm' === $unit ) {
				return $value * 100;
			}
			if ( 'mm' === $unit ) {
				return $value / 10;
			}

			return $value;
		}

		protected function convert_weight_to_kg( $value ) {
			$unit = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
			$value = (float) str_replace( ',', '.', (string) $value );

			if ( 'g' === $unit ) {
				return $value / 1000;
			}

			return $value;
		}

		protected function format_number( $value ) {
			$value = (float) $value;
			return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
		}

		protected function to_float( $value ) {
			$value = (string) $value;
			$value = str_replace( ',', '.', $value );
			$value = preg_replace( '/[^0-9.\-]/', '', $value );
			if ( '' === $value || '-' === $value ) {
				return 0.0;
			}
			return (float) $value;
		}

		protected function log_error( $message, $context = array() ) {
			if ( ! class_exists( 'WC_Logger' ) ) {
				return;
			}

			$enabled = get_option( 'cepcerto_debug', 'no' );
			if ( 'yes' !== $enabled ) {
				return;
			}

			$logger = wc_get_logger();
			$logger->error( $message, array_merge( array( 'source' => 'cepcerto' ), (array) $context ) );
		}
	}
}
