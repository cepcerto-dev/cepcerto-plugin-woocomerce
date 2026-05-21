<?php
/**
 * CepCerto Logger Class.
 *
 * Handles logging for the CepCerto plugin.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Logger Class.
 *
 * @since 1.0.0
 */
class CEPCERTO_Logger {

	/**
	 * Maximum field length for logging.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_FIELD_LENGTH = 2000;

	/**
	 * Check if logging is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		$enabled = cepcerto_get_option( 'cepcerto_debug', false );
		return in_array( $enabled, array( true, 1, '1', 'yes', 'true' ), true );
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $level   Log level (info, warning, error).
	 * @param string $message Log message.
	 * @param array  $context Additional context data. Default empty array.
	 * @return void
	 */
	public static function log( $level, $message, $context = array() ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$context = self::sanitize_context( (array) $context );
		$line    = sprintf(
			"[%s] [%s] %s %s\n",
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( (string) $level ),
			(string) $message,
			! empty( $context ) ? wp_json_encode( $context ) : ''
		);

		self::write_line( $line );
	}

	/**
	 * Log an HTTP request.
	 *
	 * @since 1.0.0
	 * @param string     $method        HTTP method.
	 * @param string     $url           Request URL.
	 * @param int|null   $status        HTTP status code.
	 * @param int|null   $duration_ms   Request duration in milliseconds.
	 * @param mixed|null $request_body  Request body.
	 * @param mixed|null $response_body Response body.
	 * @param mixed|null $error         Error message if any.
	 * @return void
	 */
	public static function log_request( $method, $url, $status, $duration_ms, $request_body = null, $response_body = null, $error = null ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$context = array(
			'method'      => (string) $method,
			'url'         => (string) $url,
			'status'      => is_null( $status ) ? null : (int) $status,
			'duration_ms' => is_null( $duration_ms ) ? null : (int) $duration_ms,
		);

		if ( ! is_null( $error ) ) {
			$context['error'] = self::truncate( $error );
		}

		if ( ! is_null( $request_body ) ) {
			$context['request'] = self::truncate( self::sanitize_context_value( $request_body ) );
		}

		if ( ! is_null( $response_body ) ) {
			$context['response'] = self::truncate( self::sanitize_context_value( $response_body ) );
		}

		self::log( 'info', 'HTTP Request', $context );
	}

	/**
	 * Get the log file path.
	 *
	 * @since 1.0.0
	 * @return string Log file path.
	 */
	public static function get_log_file_path() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'cepcerto-logs';
		wp_mkdir_p( $dir );
		self::protect_log_dir( $dir );

		$filename = 'cepcerto-' . gmdate( 'Y-m-d' ) . '.log';
		return trailingslashit( $dir ) . $filename;
	}

	/**
	 * Get the log directory path.
	 *
	 * @since 1.0.0
	 * @return string Log directory path.
	 */
	public static function get_log_dir() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . 'cepcerto-logs';
	}

	/**
	 * Get the latest log file.
	 *
	 * @since 1.0.0
	 * @return string|false Latest log file path or false if not found.
	 */
	public static function get_latest_log_file() {
		$dir = self::get_log_dir();
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = glob( trailingslashit( $dir ) . 'cepcerto-*.log' );
		if ( empty( $files ) ) {
			return false;
		}

		rsort( $files );
		return $files[0];
	}

	/**
	 * Write a line to the log file.
	 *
	 * @since 1.0.0
	 * @param string $line Line to write.
	 * @return void
	 */
	private static function write_line( $line ) {
		$file   = self::get_log_file_path();
		$result = @file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback when file writing fails.
			@error_log( '[cepcerto] failed_write_log_file=' . $file . ' line=' . $line );
		}
	}

	/**
	 * Truncate a value to maximum field length.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to truncate.
	 * @return string Truncated value.
	 */
	private static function truncate( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		$value = (string) $value;
		if ( strlen( $value ) > self::MAX_FIELD_LENGTH ) {
			return substr( $value, 0, self::MAX_FIELD_LENGTH ) . '...';
		}

		return $value;
	}

	/**
	 * Sanitize context data to hide sensitive information.
	 *
	 * @since 1.0.0
	 * @param array $context Context data.
	 * @return array Sanitized context.
	 */
	private static function sanitize_context( $context ) {
		$sanitized = array();
		foreach ( $context as $k => $v ) {
			$key = strtolower( (string) $k );
			if ( self::is_sensitive_key( $key ) ) {
				$sanitized[ $k ] = '***';
				continue;
			}
			$sanitized[ $k ] = self::sanitize_context_value( $v );
		}
		return $sanitized;
	}

	/**
	 * Sanitize a context value recursively.
	 *
	 * @since 1.0.1
	 * @param mixed $value Context value.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_context_value( $value ) {
		if ( is_array( $value ) ) {
			return self::sanitize_context( $value );
		}

		if ( is_object( $value ) ) {
			return self::sanitize_context( get_object_vars( $value ) );
		}

		if ( is_string( $value ) ) {
			$trim        = trim( $value );
			$starts_json = '' !== $trim && ( '{' === substr( $trim, 0, 1 ) || '[' === substr( $trim, 0, 1 ) );
			if ( $starts_json ) {
				$decoded = json_decode( $trim, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					return self::sanitize_context_value( $decoded );
				}
			}
		}

		return $value;
	}

	/**
	 * Check whether a log key contains sensitive data.
	 *
	 * @since 1.0.1
	 * @param string $key Context key.
	 * @return bool True when sensitive.
	 */
	private static function is_sensitive_key( $key ) {
		$sensitive_keys = array(
			'api_key',
			'token',
			'authorization',
			'token_cliente_postagem',
			'cpf',
			'cnpj',
			'cpf_cnpj_destinatario',
			'cpf_cnpj_remetente',
			'email',
			'email_cliente',
			'email_destinatario',
			'email_remetente',
			'nome',
			'nome_cliente',
			'nome_destinatario',
			'nome_remetente',
			'phone',
			'telefone',
			'whatsapp',
			'whatsapp_destinatario',
			'whatsapp_remetente',
			'logradouro',
			'logradouro_destinatario',
			'logradouro_remetente',
			'bairro',
			'bairro_destinatario',
			'bairro_remetente',
			'numero_endereco_destinatario',
			'numero_endereco_remetente',
			'complemento_destinatario',
			'complemento_remetente',
			'pix',
			'copia_cola',
			'qr_code',
		);

		return in_array( $key, $sensitive_keys, true );
	}

	/**
	 * Add basic files to discourage direct browsing of log files.
	 *
	 * @since 1.0.1
	 * @param string $dir Log directory.
	 * @return void
	 */
	private static function protect_log_dir( $dir ) {
		$index_file = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small static protection file in plugin-owned uploads directory.
			@file_put_contents( $index_file, '' );
		}

		$htaccess_file = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small static protection file in plugin-owned uploads directory.
			@file_put_contents( $htaccess_file, "Deny from all\n" );
		}
	}
}
