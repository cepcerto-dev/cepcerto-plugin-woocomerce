<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CepCerto_Api {
	const DEFAULT_BASE_URL = 'https://www.cepcerto.com/ws/json-frete';
	const URL_CREDITO = 'https://cepcerto.com/api-credito/';
	const URL_SALDO = 'https://cepcerto.com/api-saldo/';
	const URL_COTACAO_POST = 'https://cepcerto.com/api-cotacao/';
	const URL_POSTAGEM = 'https://cepcerto.com/api-postagem/';
	const URL_ETIQUETA = 'https://cepcerto.com/api-etiqueta/';
	const URL_CANCELA = 'https://cepcerto.com/api-cancela/';

	const TIMEOUT = 10;

	public function get_base_url() {
		return untrailingslashit( self::DEFAULT_BASE_URL );
	}

	public function get_api_key() {
		return '';
	}

	public function get_token_cliente_postagem() {
		return (string) get_option( 'cepcerto_token_cliente_postagem', '' );
	}

	public function quote_get( $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento ) {
		$apiKey = $this->get_api_key();

		$url = sprintf(
			'%s/%s/%s/%s/%s/%s/%s/%s',
			$this->get_base_url(),
			preg_replace( '/\D+/', '', (string) $cep_origem ),
			preg_replace( '/\D+/', '', (string) $cep_destino ),
			$this->normalize_number( $peso ),
			$this->normalize_number( $altura ),
			$this->normalize_number( $largura ),
			$this->normalize_number( $comprimento ),
			rawurlencode( $apiKey )
		);

		$start = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log_request( 'GET', $url, $status, (int) $duration, null, $body, null );
		}

		if ( null === $data ) {
			return new WP_Error( 'cepcerto_invalid_json', 'Resposta inválida do CepCerto.' );
		}

		return $data;
	}

	public function credito( $token_cliente_postagem, $valor_credito ) {
		return $this->post_json(
			self::URL_CREDITO,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
				'valor_credito'          => (string) $valor_credito,
			)
		);
	}

	public function saldo( $token_cliente_postagem ) {
		return $this->post_json(
			self::URL_SALDO,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
			)
		);
	}

	public function cotacao_post( $payload ) {
		return $this->post_json( self::URL_COTACAO_POST, (array) $payload );
	}

	public function gerar_pre_postagem( $payload ) {
		return $this->post_json( self::URL_POSTAGEM, (array) $payload );
	}

	public function etiqueta( $token_cliente_postagem, $recibo ) {
		return $this->post_json(
			self::URL_ETIQUETA,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
				'recibo'                 => (string) $recibo,
			)
		);
	}

	public function cancela( $token_cliente_postagem, $recibo ) {
		return $this->post_json(
			self::URL_CANCELA,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
				'recibo'                 => (string) $recibo,
			)
		);
	}

	public function post_json( $url, $payload ) {
		$start = microtime( true );
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log_request( 'POST', $url, $status, (int) $duration, $payload, $body, null );
		}

		if ( null === $data ) {
			return new WP_Error( 'cepcerto_invalid_json', 'Resposta inválida do CepCerto.' );
		}

		return $data;
	}

	private function normalize_number( $value ) {
		$value = (string) $value;
		$value = str_replace( ',', '.', $value );
		$value = preg_replace( '/[^0-9.]/', '', $value );
		if ( '' === $value ) {
			return '0';
		}
		return $value;
	}
}
