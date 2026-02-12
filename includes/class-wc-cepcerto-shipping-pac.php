<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	class WC_CepCerto_Shipping_Pac extends WC_CepCerto_Shipping {
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_pac';
			$this->method_title       = 'CepCerto - PAC';
			$this->method_description = 'Cotação de frete PAC via CepCerto.';
			$this->service            = 'PAC';

			parent::__construct( $instance_id );
		}
	}
}
