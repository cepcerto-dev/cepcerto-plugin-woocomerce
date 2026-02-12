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

			$api = new CepCerto_Api();
			$result = $api->quote_get(
				$originCep,
				$destinationCep,
				$dimensions['weight'],
				$dimensions['height'],
				$dimensions['width'],
				$dimensions['length']
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

			if ( 'PAC' === $service ) {
				$price = isset( $data['valorpac'] ) ? (float) str_replace( ',', '.', (string) $data['valorpac'] ) : null;
				$days  = isset( $data['prazopac'] ) ? (int) $data['prazopac'] : null;
			}

			if ( 'SEDEX' === $service ) {
				$price = isset( $data['valorsedex'] ) ? (float) str_replace( ',', '.', (string) $data['valorsedex'] ) : null;
				$days  = isset( $data['prazosedex'] ) ? (int) $data['prazosedex'] : null;
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

			if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
				return false;
			}

			foreach ( $package['contents'] as $item ) {
				if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
					continue;
				}

				/** @var WC_Product $product */
				$product  = $item['data'];
				$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;

				if ( $product->is_virtual() ) {
					continue;
				}

				$itemWeight = $product->get_weight();
				$itemWidth  = $product->get_width();
				$itemHeight = $product->get_height();
				$itemLength = $product->get_length();

				$itemWeight = $this->convert_weight_to_kg( ! empty( $itemWeight ) ? $itemWeight : $default['weight'] );
				$itemWidth  = $this->convert_dimension_to_cm( ! empty( $itemWidth ) ? $itemWidth : $default['width'] );
				$itemHeight = $this->convert_dimension_to_cm( ! empty( $itemHeight ) ? $itemHeight : $default['height'] );
				$itemLength = $this->convert_dimension_to_cm( ! empty( $itemLength ) ? $itemLength : $default['length'] );

				$weight += $itemWeight * $quantity;
				$width   = max( $width, $itemWidth );
				$height  = max( $height, $itemHeight );
				$length  = max( $length, $itemLength );
			}

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
				'width'  => (float) get_option( 'cepcerto_default_width', 10 ),
				'height' => (float) get_option( 'cepcerto_default_height', 10 ),
				'length' => (float) get_option( 'cepcerto_default_length', 10 ),
				'weight' => (float) get_option( 'cepcerto_default_weight', 1 ),
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
