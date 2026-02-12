<?php
/*
Plugin Name: CepCerto
Plugin URI: https://cepcerto.com/
Description: Plugin para cotação de fretes utilizando a API do CepCerto.
Version: 0.1.0
Author: CepCerto
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: cepcerto
Requires Plugins: woocommerce
Requires PHP: 7.2
WC requires at least: 4.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CepCerto_Plugin {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 9 );
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		require_once __DIR__ . '/includes/class-cepcerto-api.php';
		require_once __DIR__ . '/includes/class-cepcerto-logger.php';
		require_once __DIR__ . '/includes/class-cepcerto-admin.php';
		require_once __DIR__ . '/includes/class-cepcerto-frontend.php';
		require_once __DIR__ . '/includes/class-wc-cepcerto-shipping.php';
		require_once __DIR__ . '/includes/class-wc-cepcerto-shipping-pac.php';
		require_once __DIR__ . '/includes/class-wc-cepcerto-shipping-sedex.php';

		if ( is_admin() ) {
			( new CepCerto_Admin() )->init();
		}

		if ( ! is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			( new CepCerto_Frontend() )->init();
		}

		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'order_rates_by_price' ), 10, 2 );
	}

	public function register_shipping_methods( $methods ) {
		$methods['cepcerto_pac']   = 'WC_CepCerto_Shipping_Pac';
		$methods['cepcerto_sedex'] = 'WC_CepCerto_Shipping_Sedex';
		return $methods;
	}

	public function order_rates_by_price( $rates, $package ) {
		if ( empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		uasort(
			$rates,
			function ( $a, $b ) {
				if ( $a == $b ) {
					return 0;
				}
				return ( $a->cost < $b->cost ) ? -1 : 1;
			}
		);

		return $rates;
	}
}

CepCerto_Plugin::instance();
