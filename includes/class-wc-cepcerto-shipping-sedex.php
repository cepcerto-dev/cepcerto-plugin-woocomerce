<?php
/**
 * CepCerto SEDEX Shipping Method.
 *
 * Handles SEDEX shipping calculations via CepCerto API.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	/**
	 * SEDEX Shipping Method Class.
	 *
	 * @since 1.0.0
	 */
	class WC_CepCerto_Shipping_Sedex extends WC_CepCerto_Shipping {
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @param int $instance_id Shipping instance ID.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_sedex';
			$this->method_title       = __( 'CepCerto - SEDEX', 'cepcerto' );
			$this->method_description = __( 'Cotação de frete SEDEX via CepCerto.', 'cepcerto' );
			$this->service            = 'SEDEX';

			parent::__construct( $instance_id );
		}
	}
}
