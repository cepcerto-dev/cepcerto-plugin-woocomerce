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
 * Get the legacy option name used before the CEPCER/cepcer prefix migration.
 *
 * @since 1.0.0
 * @param string $option Option name.
 * @return string Legacy option name.
 */
function cepcer_get_legacy_option_name( $option ) {
	$option = (string) $option;
	if ( 0 === strpos( $option, 'cepcer_' ) ) {
		return 'cepcerto_' . substr( $option, strlen( 'cepcer_' ) );
	}
	return $option;
}

/**
 * Delete a plugin option and its legacy equivalent.
 *
 * @since 1.0.0
 * @param string $option Option name.
 * @return void
 */
function cepcer_delete_option( $option ) {
	delete_option( $option );
	$legacy = cepcer_get_legacy_option_name( $option );
	if ( $legacy !== $option ) {
		delete_option( $legacy );
	}
}

/**
 * Delete all plugin options.
 *
 * @since 1.0.0
 */
function cepcer_delete_options() {
	$options = array(
		'cepcer_token_cliente_postagem',
		'cepcer_origin_cep',
		'cepcer_nome_remetente',
		'cepcer_cpf_cnpj_remetente',
		'cepcer_whatsapp_remetente',
		'cepcer_email_remetente',
		'cepcer_logradouro_remetente',
		'cepcer_bairro_remetente',
		'cepcer_numero_endereco_remetente',
		'cepcer_complemento_remetente',
		'cepcer_debug',
		'cepcer_default_width',
		'cepcer_default_height',
		'cepcer_default_length',
		'cepcer_default_weight',
		'cepcer_min_order_value',
		'cepcer_display_locations',
		'cepcer_install_status',
		'cepcer_consent_given',
		'cepcer_consent_date',
		'cepcer_consent_email',
		'cepcer_shipping_method_migration_done',
	);

	foreach ( $options as $option ) {
		cepcer_delete_option( $option );
	}
}

/**
 * Delete all order metadata.
 *
 * @since 1.0.0
 */
function cepcer_delete_order_meta() {
	global $wpdb;

	// Delete from postmeta (legacy orders).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_cepcer_' ) . '%'
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_cepcerto_' ) . '%'
		)
	);

	// Delete from HPOS meta table if it exists.
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		$hpos_table = $wpdb->prefix . 'wc_orders_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking table existence during uninstall.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$hpos_table
			)
		);

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$wpdb->prefix}wc_orders_meta` WHERE meta_key LIKE %s",
					$wpdb->esc_like( '_cepcer_' ) . '%'
				)
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$wpdb->prefix}wc_orders_meta` WHERE meta_key LIKE %s",
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
function cepcer_delete_log_files() {
	$upload_dir = wp_upload_dir();
	$log_dirs   = array(
		trailingslashit( $upload_dir['basedir'] ) . 'cepcer-logs',
		trailingslashit( $upload_dir['basedir'] ) . 'cepcerto-logs',
	);

	foreach ( $log_dirs as $log_dir ) {
		if ( ! is_dir( $log_dir ) ) {
			continue;
		}
		$files = glob( trailingslashit( $log_dir ) . '*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->rmdir( $log_dir );
	}
}

/**
 * Delete transients.
 *
 * @since 1.0.0
 */
function cepcer_delete_transients() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_cepcer_' ) . '%'
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_cepcer_' ) . '%'
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_cepcerto_' ) . '%'
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cleanup during uninstall.
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
cepcer_delete_options();
cepcer_delete_order_meta();
cepcer_delete_log_files();
cepcer_delete_transients();

// Clear any cached data.
wp_cache_flush();
