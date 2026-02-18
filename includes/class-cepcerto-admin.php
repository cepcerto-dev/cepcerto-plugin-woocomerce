<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CepCerto_Admin {
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_cepcerto_download_log', array( $this, 'download_log' ) );
	}

	public function register_menu() {
		add_menu_page(
			'CepCerto',
			'CepCerto',
			'manage_woocommerce',
			'cepcerto',
			array( $this, 'render_page' ),
			'dashicons-location-alt'
		);

		add_submenu_page(
			'cepcerto',
			'Configurações',
			'Configurações',
			'manage_woocommerce',
			'cepcerto',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'cepcerto',
			'Logs',
			'Logs',
			'manage_woocommerce',
			'cepcerto-logs',
			array( $this, 'render_logs_page' )
		);
	}

	public function register_settings() {
		register_setting( 'cepcerto_settings', 'cepcerto_token_cliente_postagem' );
		register_setting( 'cepcerto_settings', 'cepcerto_origin_cep' );
		register_setting( 'cepcerto_settings', 'cepcerto_debug' );

		register_setting( 'cepcerto_settings', 'cepcerto_default_width' );
		register_setting( 'cepcerto_settings', 'cepcerto_default_height' );
		register_setting( 'cepcerto_settings', 'cepcerto_default_length' );
		register_setting( 'cepcerto_settings', 'cepcerto_default_weight' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$token  = get_option( 'cepcerto_token_cliente_postagem', '' );
		$origin = get_option( 'cepcerto_origin_cep', '' );
		$debug  = get_option( 'cepcerto_debug', 'no' );

		$defaultWidth  = get_option( 'cepcerto_default_width', 10 );
		$defaultHeight = get_option( 'cepcerto_default_height', 10 );
		$defaultLength = get_option( 'cepcerto_default_length', 10 );
		$defaultWeight = get_option( 'cepcerto_default_weight', 1 );

		?>
		<div class="wrap">
			<h1>CepCerto</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cepcerto_settings' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="cepcerto_token_cliente_postagem">Token cliente postagem</label></th>
							<td>
								<input name="cepcerto_token_cliente_postagem" id="cepcerto_token_cliente_postagem" type="text" class="regular-text" value="<?php echo esc_attr( $token ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cepcerto_origin_cep">CEP de origem</label></th>
							<td>
								<input name="cepcerto_origin_cep" id="cepcerto_origin_cep" type="text" class="regular-text" value="<?php echo esc_attr( $origin ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">Debug</th>
							<td>
								<label>
									<input name="cepcerto_debug" type="checkbox" value="yes" <?php checked( $debug, 'yes' ); ?> />
									Ativar log no WooCommerce (source: cepcerto)
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<h2 class="title">Dimensões/Peso padrão (fallback)</h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="cepcerto_default_width">Largura (cm)</label></th>
							<td><input name="cepcerto_default_width" id="cepcerto_default_width" type="number" step="0.01" value="<?php echo esc_attr( $defaultWidth ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cepcerto_default_height">Altura (cm)</label></th>
							<td><input name="cepcerto_default_height" id="cepcerto_default_height" type="number" step="0.01" value="<?php echo esc_attr( $defaultHeight ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cepcerto_default_length">Comprimento (cm)</label></th>
							<td><input name="cepcerto_default_length" id="cepcerto_default_length" type="number" step="0.01" value="<?php echo esc_attr( $defaultLength ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cepcerto_default_weight">Peso (kg)</label></th>
							<td><input name="cepcerto_default_weight" id="cepcerto_default_weight" type="number" step="0.01" value="<?php echo esc_attr( $defaultWeight ); ?>" /></td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_logs_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$enabled = get_option( 'cepcerto_debug', 'no' );
		$file = class_exists( 'CepCerto_Logger' ) ? CepCerto_Logger::get_latest_log_file() : false;
		$content = '';

		if ( $file && file_exists( $file ) ) {
			$content = $this->tail_file( $file, 400 );
		}

		$downloadUrl = wp_nonce_url( admin_url( 'admin-post.php?action=cepcerto_download_log' ), 'cepcerto_download_log' );
		?>
		<div class="wrap">
			<h1>Logs - CepCerto</h1>
			<p>
				<strong>Status do log:</strong> <?php echo ( $enabled === 'yes' ) ? 'Ativo' : 'Inativo'; ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $downloadUrl ); ?>">Baixar log</a>
			</p>
			<textarea style="width:100%;min-height:520px;font-family:monospace;" readonly><?php echo esc_textarea( $content ); ?></textarea>
		</div>
		<?php
	}

	public function download_log() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cepcerto_download_log' );

		if ( ! class_exists( 'CepCerto_Logger' ) ) {
			wp_die( 'Logger indisponível.' );
		}

		$file = CepCerto_Logger::get_latest_log_file();
		if ( ! $file || ! file_exists( $file ) ) {
			wp_die( 'Arquivo de log não encontrado.' );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file ) );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

	private function tail_file( $file, $maxLines = 200 ) {
		$lines = @file( $file, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$total = count( $lines );
		$start = max( 0, $total - (int) $maxLines );
		return implode( "\n", array_slice( $lines, $start ) );
	}
}
