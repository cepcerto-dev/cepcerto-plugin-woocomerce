<?php
/**
 * CepCerto Jadlog .Com Shipping Method.
 *
 * Handles Jadlog .Com shipping calculations via CepCerto API.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'CEPCER_Shipping' ) ) {
	/**
	 * Jadlog .Com Shipping Method Class.
	 *
	 * @since 1.0.0
	 */
	class CEPCER_Shipping_Jadlog_Dotcom extends CEPCER_Shipping {
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @param int $instance_id Shipping instance ID.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cepcer_jadlog_dotcom';
			$this->method_title       = __( 'CepCerto - Jadlog .Com', 'cepcerto' );
			$this->method_description = __( 'Cotação de frete Jadlog .Com via CepCerto.', 'cepcerto' );
			$this->service            = 'JADLOG_DOTCOM';

			parent::__construct( $instance_id );
		}
	}
}
