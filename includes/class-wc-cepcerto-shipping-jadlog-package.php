<?php
/**
 * CepCerto Jadlog Package Shipping Method.
 *
 * Handles Jadlog Package shipping calculations via CepCerto API.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WC_CepCerto_Shipping' ) ) {
	/**
	 * Jadlog Package Shipping Method Class.
	 *
	 * @since 1.0.0
	 */
	class WC_CepCerto_Shipping_Jadlog_Package extends WC_CepCerto_Shipping {
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @param int $instance_id Shipping instance ID.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcerto_jadlog_package';
			$this->method_title       = __( 'CepCerto - Jadlog Package', 'cepcerto' );
			$this->method_description = __( 'Cotação de frete Jadlog Package via CepCerto.', 'cepcerto' );
			$this->service            = 'JADLOG_PACKAGE';

			parent::__construct( $instance_id );
		}
	}
}
