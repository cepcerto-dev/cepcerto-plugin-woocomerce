<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	class WC_CepCerto_Shipping_Sedex extends WC_CepCerto_Shipping {
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_sedex';
			$this->method_title       = 'CepCerto - SEDEX';
			$this->method_description = 'Cotação de frete SEDEX via CepCerto.';
			$this->service            = 'SEDEX';

			parent::__construct( $instance_id );
		}
	}
}
