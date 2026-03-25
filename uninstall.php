<?php
/**
 * CepCerto Uninstall
 *
 * Removes all plugin data when uninstalled.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin options.
 *
 * @since 1.0.0
 */
function cepcerto_delete_options() {
	$options = array(
		'cepcerto_token_cliente_postagem',
		'cepcerto_origin_cep',
		'cepcerto_nome_remetente',
		'cepcerto_cpf_cnpj_remetente',
		'cepcerto_whatsapp_remetente',
		'cepcerto_email_remetente',
		'cepcerto_logradouro_remetente',
		'cepcerto_bairro_remetente',
		'cepcerto_numero_endereco_remetente',
		'cepcerto_complemento_remetente',
		'cepcerto_debug',
		'cepcerto_default_width',
		'cepcerto_default_height',
		'cepcerto_default_length',
		'cepcerto_default_weight',
		'cepcerto_min_order_value',
		'cepcerto_display_locations',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Delete all order metadata.
 *
 * @since 1.0.0
 */
function cepcerto_delete_order_meta() {
	global $wpdb;

	// Delete from postmeta (legacy orders).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_cepcerto_' ) . '%'
		)
	);

	// Delete from HPOS meta table if it exists.
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		$hpos_table = $wpdb->prefix . 'wc_orders_meta';
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$hpos_table
			)
		);

		if ( $table_exists ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$hpos_table} WHERE meta_key LIKE %s",
					$wpdb->esc_like( '_cepcerto_' ) . '%'
				)
			);
		}
	}
}

/**
 * Delete log files.
 *
 * @since 1.0.0
 */
function cepcerto_delete_log_files() {
	$upload_dir = wp_upload_dir();
	$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'cepcerto-logs';

	if ( is_dir( $log_dir ) ) {
		$files = glob( trailingslashit( $log_dir ) . '*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file );
				}
			}
		}
		@rmdir( $log_dir );
	}
}

/**
 * Delete transients.
 *
 * @since 1.0.0
 */
function cepcerto_delete_transients() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_cepcerto_' ) . '%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_cepcerto_' ) . '%'
		)
	);
}

/**
 * Main uninstall routine.
 */
cepcerto_delete_options();
cepcerto_delete_order_meta();
cepcerto_delete_log_files();
cepcerto_delete_transients();

// Clear any cached data.
wp_cache_flush();
