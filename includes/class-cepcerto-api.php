<?php
/**
 * CepCerto API Class.
 *
 * Handles all API requests to CepCerto service.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CepCerto API Class.
 *
 * @since 1.0.0
 */
class CepCerto_Api {
	/**
	 * CepCerto base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_BASE_URL = 'https://cepcerto.com/';

	/**
	 * CepCerto CEP lookup URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const URL_BUSCA_CEP = self::DEFAULT_BASE_URL . 'ws/json/';

	/**
	 * CepCerto API endpoints.
	 *
	 * @since 1.0.0
	 */
	const URL_CADASTRO           = self::DEFAULT_BASE_URL . 'api-cadastro/';
	const URL_VALIDAR_TOKEN      = self::DEFAULT_BASE_URL . 'api-validar-token/';
	const URL_CREDITO            = self::DEFAULT_BASE_URL . 'api-credito/';
	const URL_SALDO              = self::DEFAULT_BASE_URL . 'api-saldo/';
	const URL_FINANCEIRO         = self::DEFAULT_BASE_URL . 'api-financeiro/';
	const URL_COTACAO_POST       = self::DEFAULT_BASE_URL . 'api-cotacao/';
	const URL_COTACAO_FRETE      = self::DEFAULT_BASE_URL . 'api-cotacao-frete/';
	const URL_POSTAGEM           = self::DEFAULT_BASE_URL . 'api-postagem/';
	const URL_ETIQUETA           = self::DEFAULT_BASE_URL . 'api-etiqueta/';
	const URL_CANCELA            = self::DEFAULT_BASE_URL . 'api-cancela/';
	const URL_POSTAGEM_FRETE     = self::DEFAULT_BASE_URL . 'api-postagem-frete/';
	const URL_CANCELA_POSTAGEM   = self::DEFAULT_BASE_URL . 'api-cancela-postagem';
	const URL_REGISTRO           = self::DEFAULT_BASE_URL . 'api-cadastro-wordpress/';
	const URL_RASTREIO           = self::DEFAULT_BASE_URL . 'api-rastreio/';
	const URL_RASTREIO_ENCOMENDA = self::DEFAULT_BASE_URL . 'encomenda-rastreio/';

	/**
	 * Correios CEP search URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const URL_BUSCA_CEP_CORREIOS = 'https://buscacepinter.correios.com.br/app/endereco/index.php';

	/**
	 * API request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TIMEOUT = 10;

	/**
	 * CEP lookup timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TIMEOUT_CEP = 10;

	/**
	 * Get the CepCerto client token.
	 *
	 * @since 1.0.0
	 * @return string The client token.
	 */
	public function get_token_cliente_postagem() {
		return (string) get_option( 'cepcerto_token_cliente_postagem', '' );
	}

	/**
	 * Get shipping quote.
	 *
	 * @since 1.0.0
	 * @param string $cep_origem      Origin postal code.
	 * @param string $cep_destino     Destination postal code.
	 * @param float  $peso            Package weight in kg.
	 * @param float  $altura          Package height in cm.
	 * @param float  $largura         Package width in cm.
	 * @param float  $comprimento     Package length in cm.
	 * @param float  $valor_encomenda Package value. Default 0.
	 * @return array|WP_Error Quote data or error.
	 */
	public function quote_get( $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor_encomenda = 0 ) {
		$token = $this->get_token_cliente_postagem();
		return $this->quote_frete( $token, $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor_encomenda );
	}

	/**
	 * Lookup postal code information via CepCerto API.
	 *
	 * @since 1.0.0
	 * @param string $cep Postal code to lookup.
	 * @return array|WP_Error Address data or error.
	 */
	public function consultar_cep( $cep ) {
		$cep = preg_replace( '/\D+/', '', (string) $cep );
		if ( 8 !== strlen( $cep ) ) {
			return new WP_Error( 'cepcerto_invalid_cep', __( 'CEP inválido.', 'cepcerto' ) );
		}

		$token    = 'aadsp5522334455@@';
		$url      = trailingslashit( self::URL_BUSCA_CEP ) . rawurlencode( $cep ) . '/' . ( $token );
		$start    = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT_CEP,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);
		$duration = ( microtime( true ) - $start ) * 1000;

		if ( is_wp_error( $response ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log_request( 'GET', $url, null, (int) $duration, null, null, $response->get_error_message() );
			}
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log_request( 'GET', $url, $status, (int) $duration, null, $body, null );
		}

		if ( null === $data || ! is_array( $data ) ) {
			return new WP_Error( 'cepcerto_invalid_json', __( 'Resposta inválida (não-JSON).', 'cepcerto' ) );
		}

		if ( isset( $data['erro'] ) && $data['erro'] ) {
			return new WP_Error( 'cepcerto_cep_not_found', __( 'CEP não encontrado.', 'cepcerto' ) );
		}

		return $data;
	}

	/**
	 * Register plugin installation with CepCerto.
	 *
	 * @since 1.0.0
	 * @param string $email Customer email.
	 * @param string $nome  Customer name.
	 * @return array|WP_Error Registration result or error.
	 */
	public function registrar_instalacao( $email, $nome ) {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$payload = array(
			'client_id'     => sanitize_text_field( get_option( 'blogname', 'loja' ) ) . '_' . wp_hash( home_url() ),
			'timestamp'     => time(),
			'nonce'         => wp_generate_uuid4(),
			'email_cliente' => sanitize_email( $email ),
			'nome_cliente'  => sanitize_text_field( $nome ),
			'site_url'      => home_url(),
			'ip'            => $ip,
		);

		$result = $this->post_json( self::URL_REGISTRO, $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $result['ok'] ) && true === $result['ok'] && ! empty( $result['token'] ) ) {
			update_option( 'cepcerto_token_cliente_postagem', sanitize_text_field( $result['token'] ) );
		}

		return array(
			'payload'  => $payload,
			'response' => $result,
		);
	}

	/**
	 * Add credit to account.
	 *
	 * @since 1.0.0
	 * @param string $token_cliente_postagem Client token.
	 * @param float  $valor_credito          Credit amount.
	 * @return array|WP_Error Credit result or error.
	 */
	public function credito( $token_cliente_postagem, $valor_credito ) {
		return $this->post_json(
			self::URL_CREDITO,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
				'valor_credito'          => (string) $valor_credito,
			)
		);
	}

	/**
	 * Get account balance.
	 *
	 * @since 1.0.0
	 * @param string $token_cliente_postagem Client token.
	 * @return array|WP_Error Balance data or error.
	 */
	public function saldo( $token_cliente_postagem ) {
		return $this->post_json(
			self::URL_SALDO,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
			)
		);
	}

	/**
	 * Get financial transactions.
	 *
	 * @since 1.0.0
	 * @param string   $token_cliente_postagem Client token.
	 * @param int|null $limit                  Query limit. Default null.
	 * @param int|null $offset                 Query offset. Default null.
	 * @return array|WP_Error Financial data or error.
	 */
	public function financeiro( $token_cliente_postagem, $limit = null, $offset = null ) {
		$payload = array(
			'token_cliente_postagem' => (string) $token_cliente_postagem,
		);
		if ( null !== $limit && '' !== $limit ) {
			$payload['limit'] = (int) $limit;
		}
		if ( null !== $offset && '' !== $offset ) {
			$payload['offset'] = (int) $offset;
		}
		return $this->post_json( self::URL_FINANCEIRO, $payload );
	}

	/**
	 * Get shipping quote from CepCerto.
	 *
	 * @since 1.0.0
	 * @param string $token_cliente_postagem Client token.
	 * @param string $cep_remetente          Sender postal code.
	 * @param string $cep_destinatario       Recipient postal code.
	 * @param float  $peso                   Package weight in kg.
	 * @param float  $altura                 Package height in cm.
	 * @param float  $largura                Package width in cm.
	 * @param float  $comprimento            Package length in cm.
	 * @param float  $valor_encomenda        Package value.
	 * @return array|WP_Error Quote data or error.
	 */
	public function quote_frete( $token_cliente_postagem, $cep_remetente, $cep_destinatario, $peso, $altura, $largura, $comprimento, $valor_encomenda ) {
		$payload = array(
			'token_cliente_postagem' => (string) $token_cliente_postagem,
			'cep_remetente'          => $this->format_cep( $cep_remetente ),
			'cep_destinatario'       => $this->format_cep( $cep_destinatario ),
			'peso'                   => $this->format_decimal( $peso ),
			'altura'                 => $this->format_decimal( $altura ),
			'largura'                => $this->format_decimal( $largura ),
			'comprimento'            => $this->format_decimal( $comprimento ),
			'valor_encomenda'        => $this->format_decimal( $valor_encomenda ),
		);

		return $this->post_json( self::URL_COTACAO_FRETE, $payload );
	}

	/**
	 * Create shipping label.
	 *
	 * @since 1.0.0
	 * @param array $payload Label data.
	 * @return array|WP_Error Label result or error.
	 */
	public function postagem_frete( $payload ) {
		return $this->post_json( self::URL_POSTAGEM_FRETE, (array) $payload );
	}

	/**
	 * Cancel shipping label.
	 *
	 * @since 1.0.0
	 * @param string $token_cliente_postagem Client token.
	 * @param string $cod_objeto             Tracking code.
	 * @return array|WP_Error Cancellation result or error.
	 */
	public function cancela_postagem( $token_cliente_postagem, $cod_objeto ) {
		return $this->post_json(
			self::URL_CANCELA_POSTAGEM,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
				'cod_objeto'             => (string) $cod_objeto,
			)
		);
	}

	/**
	 * Track package.
	 *
	 * @since 1.0.0
	 * @param string $codigo_objeto Tracking code.
	 * @return array|WP_Error Tracking data or error.
	 */
	public function rastreio( $codigo_objeto ) {
		return $this->post_json(
			self::URL_RASTREIO,
			array(
				'codigo_objeto' => (string) $codigo_objeto,
			)
		);
	}

	/**
	 * Make a POST request with JSON payload.
	 *
	 * @since 1.0.0
	 * @param string $url     Request URL.
	 * @param array  $payload Request payload.
	 * @return array|WP_Error Response data or error.
	 */
	public function post_json( $url, $payload ) {
		$start    = microtime( true );
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		$duration = ( microtime( true ) - $start ) * 1000;

		if ( is_wp_error( $response ) ) {
			if ( class_exists( 'CepCerto_Logger' ) ) {
				CepCerto_Logger::log_request( 'POST', $url, null, (int) $duration, $payload, null, $response->get_error_message() );
			}
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log_request( 'POST', $url, $status, (int) $duration, $payload, $body, null );
		}

		if ( null === $data ) {
			return new WP_Error( 'cepcerto_invalid_json', __( 'Resposta inválida da API.', 'cepcerto' ) );
		}

		return $data;
	}

	/**
	 * Format postal code (remove non-digits).
	 *
	 * @since 1.0.0
	 * @param string $cep Postal code.
	 * @return string Formatted postal code.
	 */
	private function format_cep( $cep ) {
		return preg_replace( '/\D+/', '', (string) $cep );
	}

	/**
	 * Format decimal value.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to format.
	 * @return string Formatted decimal value.
	 */
	private function format_decimal( $value ) {
		$value = (string) $value;
		$value = str_replace( ',', '.', $value );
		$value = preg_replace( '/[^0-9.]/', '', $value );
		if ( '' === $value ) {
			return '0';
		}
		return $value;
	}
}
