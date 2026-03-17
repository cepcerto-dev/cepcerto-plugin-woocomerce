<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CepCerto_Api {
	const DEFAULT_BASE_URL = 'https://www.cepcerto.com/ws/json-frete';
	const URL_VIACEP = 'https://viacep.com.br/ws/';
	const URL_CADASTRO = 'https://cepcerto.com/api-cadastro/';
	const URL_VALIDAR_TOKEN = 'https://cepcerto.com/api-validar-token/';
	const URL_CREDITO = 'https://cepcerto.com/api-credito/';
	const URL_SALDO = 'https://cepcerto.com/api-saldo/';
	const URL_COTACAO_POST = 'https://cepcerto.com/api-cotacao/';
	const URL_COTACAO_FRETE = 'https://cepcerto.com/api-cotacao-frete/';
	const URL_POSTAGEM = 'https://cepcerto.com/api-postagem/';
	const URL_ETIQUETA = 'https://cepcerto.com/api-etiqueta/';
	const URL_CANCELA = 'https://cepcerto.com/api-cancela/';
	const URL_POSTAGEM_FRETE = 'https://cepcerto.com/api-postagem-frete/';
	const URL_CANCELA_POSTAGEM = 'https://cepcerto.com/api-cancela-postagem';
	const URL_REGISTRO = 'https://cepcerto.com/api-cadastro-wordpress/';

	const TIMEOUT = 10;
	const TIMEOUT_CEP = 10;

	public function get_base_url() {
		return untrailingslashit( self::DEFAULT_BASE_URL );
	}

	public function get_api_key() {
		return '';
	}

	public function get_token_cliente_postagem() {
		return (string) get_option( 'cepcerto_token_cliente_postagem', '' );
	}

	public function quote_get( $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor_encomenda = 0 ) {
		$token = $this->get_token_cliente_postagem();
		return $this->quote_frete( $token, $cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor_encomenda );
	}

	public function consultar_cep( $cep ) {
		$cep = preg_replace( '/\D+/', '', (string) $cep );
		if ( strlen( $cep ) !== 8 ) {
			return new WP_Error( 'cepcerto_invalid_cep', 'CEP inválido.' );
		}

		$url = trailingslashit( self::URL_VIACEP ) . rawurlencode( $cep ) . '/json/';
		$start = microtime( true );
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
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( class_exists( 'CepCerto_Logger' ) ) {
			CepCerto_Logger::log_request( 'GET', $url, $status, (int) $duration, null, $body, null );
		}

		if ( null === $data || ! is_array( $data ) ) {
			return new WP_Error( 'cepcerto_invalid_json', 'Resposta inválida (não-JSON).' );
		}

		if ( isset( $data['erro'] ) && $data['erro'] ) {
			return new WP_Error( 'cepcerto_cep_not_found', 'CEP não encontrado.' );
		}

		return $data;
	}

	public function registrar_instalacao( $email, $nome ) {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) $_SERVER['REMOTE_ADDR'];
		}

		$payload = array(
			'client_id' => sanitize_text_field( get_option( 'blogname', 'loja' ) ) . '_' . wp_hash( home_url() ),
			'timestamp' => time(),
			'nonce'     => wp_generate_uuid4(),
			'email_cliente'     => sanitize_email( $email ),
			'nome_cliente' => sanitize_text_field( $nome ),
			'site_url'  => home_url(),
			'ip'        => $ip,
			'telefone_cliente' => '1234567890',
			// telefone_cliente?
		);

		//$ip = $_SERVER['REMOTE_ADDR'];
		//echo $ip;


		$result = $this->post_json( self::URL_REGISTRO, $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $result['ok'] ) && $result['ok'] === true && ! empty( $result['token'] ) ) {
			update_option( 'cepcerto_token_cliente_postagem', sanitize_text_field( $result['token'] ) );
		}

		return array(
			'payload'  => $payload,
			'response' => $result,
		);
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

	public function cadastro( $email, $nome ) {
		return $this->post_json(
			self::URL_CADASTRO,
			array(
				'email' => (string) $email,
				'nome'  => (string) $nome,
			)
		);
	}

	public function validar_token( $email, $codigo ) {
		return $this->post_json(
			self::URL_VALIDAR_TOKEN,
			array(
				'email'  => (string) $email,
				'codigo' => (string) $codigo,
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

	public function postagem_frete( $payload ) {
		return $this->post_json( self::URL_POSTAGEM_FRETE, (array) $payload );
	}

	public function cancela_postagem( $token_cliente_postagem, $cod_objeto ) {
		return $this->post_json(
			self::URL_CANCELA_POSTAGEM,
			array(
				'token_cliente_postagem' => (string) $token_cliente_postagem,
				'cod_objeto'             => (string) $cod_objeto,
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
			
			return new WP_Error( 'cepcerto_invalid_json', '$payload: ' . json_encode( $payload ) . ' $response: ' . json_encode( $response ) );
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

	private function format_cep( $cep ) {
		return preg_replace( '/\D+/', '', (string) $cep );
	}

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
