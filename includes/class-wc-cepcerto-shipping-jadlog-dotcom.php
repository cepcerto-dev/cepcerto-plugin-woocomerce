<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	class WC_CepCerto_Shipping_Jadlog_Dotcom extends WC_CepCerto_Shipping {
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_jadlog_dotcom';
			$this->method_title       = 'CepCerto - Jadlog .Com';
			$this->method_description = 'Cotação de frete Jadlog .Com via CepCerto.';
			$this->service            = 'JADLOG_DOTCOM';

			parent::__construct( $instance_id );
		}
	}
}
