<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	class WC_CepCerto_Shipping_Jadlog_Package extends WC_CepCerto_Shipping {
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_jadlog_package';
			$this->method_title       = 'CepCerto - Jadlog Package';
			$this->method_description = 'Cotação de frete Jadlog Package via CepCerto.';
			$this->service            = 'JADLOG_PACKAGE';

			parent::__construct( $instance_id );
		}
	}
}
