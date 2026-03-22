<?php
/**
 * CepCerto PAC Shipping Method.
 *
 * Handles PAC shipping calculations via CepCerto API.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	/**
	 * PAC Shipping Method Class.
	 *
	 * @since 1.0.0
	 */
	class WC_CepCerto_Shipping_Pac extends WC_CepCerto_Shipping {
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @param int $instance_id Shipping instance ID.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_pac';
			$this->method_title       = __( 'CepCerto - PAC', 'cepcerto' );
			$this->method_description = __( 'Cotação de frete PAC via CepCerto.', 'cepcerto' );
			$this->service            = 'PAC';

			parent::__construct( $instance_id );
		}
	}
}
