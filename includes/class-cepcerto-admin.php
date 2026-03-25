<?php
/**
 * CepCerto Admin Class.
 *
 * Handles all admin area functionality including settings, orders management,
 * and integration with WooCommerce admin.
 *
 * @package CepCerto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin Class.
 *
 * @since 1.0.0
 */
class CepCerto_Admin {

	/**
	 * Initialize admin features.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_post_cepcerto_download_log', array( $this, 'download_log' ) );
		add_action( 'admin_post_cepcerto_reset_settings', array( $this, 'handle_reset_settings' ) );
		add_action( 'wp_ajax_cepcerto_consultar_cep_origem', array( $this, 'ajax_consultar_cep_origem' ) );
		add_action( 'wp_ajax_cepcerto_consultar_saldo', array( $this, 'ajax_consultar_saldo' ) );
		add_action( 'wp_ajax_cepcerto_adicionar_credito', array( $this, 'ajax_adicionar_credito' ) );
		add_action( 'wp_ajax_cepcerto_gerar_etiqueta', array( $this, 'ajax_gerar_etiqueta' ) );
		add_action( 'wp_ajax_cepcerto_cancelar_etiqueta', array( $this, 'ajax_cancelar_etiqueta' ) );
		add_action( 'wp_ajax_cepcerto_financeiro', array( $this, 'ajax_financeiro' ) );

		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_wc_order_tracking_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_wc_order_tracking_column' ), 20, 2 );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_wc_order_tracking_column' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_wc_order_tracking_column_hpos' ), 20, 2 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_cepcerto' !== $hook ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'sender'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_enqueue_script(
			'cepcerto-admin-header',
			CEPCERTO_PLUGIN_URL . 'assets/admin-header.js',
			array(),
			CEPCERTO_VERSION,
			true
		);
		wp_localize_script(
			'cepcerto-admin-header',
			'CepCertoAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonceSaldo'  => wp_create_nonce( 'cepcerto_consultar_saldo' ),
			)
		);

		if ( 'sender' === $tab ) {
			wp_enqueue_script(
				'cepcerto-admin-sender',
				CEPCERTO_PLUGIN_URL . 'assets/admin-sender.js',
				array(),
				CEPCERTO_VERSION,
				true
			);
			wp_localize_script(
				'cepcerto-admin-sender',
				'CepCertoSender',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonceCep' => wp_create_nonce( 'cepcerto_consultar_cep_origem' ),
				)
			);
		}

		if ( 'pedidos' === $tab ) {
			wp_enqueue_script(
				'cepcerto-admin-orders',
				CEPCERTO_PLUGIN_URL . 'assets/admin-orders.js',
				array(),
				CEPCERTO_VERSION,
				true
			);
			wp_localize_script(
				'cepcerto-admin-orders',
				'CepCertoOrders',
				array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'cepcerto_etiqueta' ),
					'urlRastreio' => CepCerto_Api::URL_RASTREIO_ENCOMENDA,
				)
			);
		}

		if ( 'saldo' === $tab ) {
			wp_enqueue_script(
				'cepcerto-admin-saldo',
				CEPCERTO_PLUGIN_URL . 'assets/admin-saldo.js',
				array(),
				CEPCERTO_VERSION,
				true
			);
			wp_localize_script(
				'cepcerto-admin-saldo',
				'CepCertoSaldo',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'nonceSaldo'       => wp_create_nonce( 'cepcerto_consultar_saldo' ),
					'nonceCredito'     => wp_create_nonce( 'cepcerto_adicionar_credito' ),
					'nonceFinanceiro'  => wp_create_nonce( 'cepcerto_financeiro' ),
				)
			);
		}

		if ( 'logs' === $tab ) {
			wp_enqueue_script(
				'cepcerto-admin-logs',
				CEPCERTO_PLUGIN_URL . 'assets/admin-logs.js',
				array(),
				CEPCERTO_VERSION,
				true
			);
		}
	}

	/**
	 * Get tracking cell HTML.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 * @return string HTML output.
	 */
	private function get_tracking_cell_html( $order ) {
		ob_start();
		$this->echo_tracking_cell( $order );
		return (string) ob_get_clean();
	}

	/**
	 * Add tracking column to orders table.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_wc_order_tracking_column( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}
		$columns['cepcerto_rastreio'] = 'Rastreio';
		return $columns;
	}

	public function render_wc_order_tracking_column( $column, $post_id ) {
		if ( 'cepcerto_rastreio' !== $column ) {
			return;
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $post_id ) : null;
		if ( ! ( $order instanceof WC_Order ) ) {
			echo '<span style="color:#999;">—</span>';
			return;
		}
		$this->echo_tracking_cell( $order );
	}

	public function render_wc_order_tracking_column_hpos( $column, $order ) {
		if ( 'cepcerto_rastreio' !== $column ) {
			return;
		}
		if ( ! ( $order instanceof WC_Order ) ) {
			echo '<span style="color:#999;">—</span>';
			return;
		}
		$this->echo_tracking_cell( $order );
	}

	private function echo_tracking_cell( $order ) {
		$etiqueta     = $order->get_meta( '_cepcerto_etiqueta', true );
		$has_etiqueta = is_array( $etiqueta ) && ! empty( $etiqueta['codigoObjeto'] );
		if ( ! $has_etiqueta ) {
			echo '<span style="color:#999;">—</span>';
			return;
		}

		$codigo = (string) $etiqueta['codigoObjeto'];
		$track  = $this->get_cached_tracking( $codigo );
		$link   = '';
		$evt    = null;
		if ( is_array( $track ) ) {
			$link = ! empty( $track['link_cepcerto'] ) ? (string) $track['link_cepcerto'] : ( CepCerto_Api::URL_RASTREIO_ENCOMENDA . rawurlencode( $codigo ) );
			if ( ! empty( $track['eventos'] ) && is_array( $track['eventos'] ) ) {
				$evt = $track['eventos'][0];
			}
		}

		$code_html = '<code>' . esc_html( $codigo ) . '</code>';
		if ( '' !== $link ) {
			$code_html = '<a href="' . esc_url( $link ) . '" target="_blank">' . $code_html . '</a>';
		}

		echo wp_kses_post( $code_html );
		if ( is_array( $evt ) ) {
			$desc = isset( $evt['descricao'] ) ? (string) $evt['descricao'] : '';
			$data = isset( $evt['data_br'] ) ? (string) $evt['data_br'] : '';
			if ( '' !== $desc || '' !== $data ) {
				echo '<br><small style="color:#555;">' . esc_html( trim( $desc ) ) . ( '' !== $data ? ' · ' . esc_html( $data ) : '' ) . '</small>';
			}
		}
	}

	private function get_cached_tracking( $codigo ) {
		$codigo = (string) $codigo;
		if ( '' === $codigo ) {
			return null;
		}
		$key    = 'cepcerto_track_' . md5( $codigo );
		$cached = get_transient( $key );
		if ( false !== $cached && ( is_array( $cached ) || is_object( $cached ) ) ) {
			return $cached;
		}
		if ( ! class_exists( 'CepCerto_Api' ) ) {
			return null;
		}
		$api    = new CepCerto_Api();
		$result = $api->rastreio( $codigo );
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return null;
		}
		set_transient( $key, $result, 15 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			'CepCerto',
			'CepCerto',
			'manage_woocommerce',
			'cepcerto',
			array( $this, 'render_page' ),
			'dashicons-location-alt'
		);
	}

	public function maybe_redirect_legacy_pages() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'cepcerto-saldo' === $page ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'cepcerto',
						'tab'  => 'saldo',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		if ( 'cepcerto-logs' === $page ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'cepcerto',
						'tab'  => 'logs',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'cepcerto_settings',
			'cepcerto_token_cliente_postagem',
			array(
				'sanitize_callback' => array( $this, 'sanitize_token_cliente_postagem' ),
			)
		);
		register_setting( 'cepcerto_settings', 'cepcerto_debug' );

		register_setting( 'cepcerto_settings', 'cepcerto_default_width' );
		register_setting( 'cepcerto_settings', 'cepcerto_default_height' );
		register_setting( 'cepcerto_settings', 'cepcerto_default_length' );
		register_setting( 'cepcerto_settings', 'cepcerto_default_weight' );
		register_setting( 'cepcerto_settings', 'cepcerto_min_order_value' );
		register_setting(
			'cepcerto_settings',
			'cepcerto_display_locations',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_display_locations' ),
				'default'           => array( 'product', 'checkout' ),
			)
		);

		register_setting( 'cepcerto_settings_sender', 'cepcerto_origin_cep' );
		register_setting( 'cepcerto_settings_sender', 'cepcerto_nome_remetente' );
		register_setting(
			'cepcerto_settings_sender',
			'cepcerto_cpf_cnpj_remetente',
			array(
				'sanitize_callback' => array( $this, 'sanitize_cpf_cnpj_remetente' ),
			)
		);
		register_setting(
			'cepcerto_settings_sender',
			'cepcerto_whatsapp_remetente',
			array(
				'sanitize_callback' => array( $this, 'sanitize_whatsapp_remetente' ),
			)
		);
		register_setting(
			'cepcerto_settings_sender',
			'cepcerto_email_remetente',
			array(
				'sanitize_callback' => array( $this, 'sanitize_email_remetente' ),
			)
		);
		register_setting( 'cepcerto_settings_sender', 'cepcerto_logradouro_remetente' );
		register_setting(
			'cepcerto_settings_sender',
			'cepcerto_bairro_remetente',
			array(
				'sanitize_callback' => array( $this, 'sanitize_bairro_remetente' ),
			)
		);
		register_setting(
			'cepcerto_settings_sender',
			'cepcerto_numero_endereco_remetente',
			array(
				'sanitize_callback' => array( $this, 'sanitize_numero_endereco_remetente' ),
			)
		);
		register_setting( 'cepcerto_settings_sender', 'cepcerto_complemento_remetente' );
	}

	private function digits_only( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/\D+/', '', $value );
		return (string) $value;
	}

	public function sanitize_cpf_cnpj_remetente( $value ) {
		$digits = $this->digits_only( $value );
		if ( '' === $digits || ( 11 !== strlen( $digits ) && 14 !== strlen( $digits ) ) ) {
			add_settings_error( 'cepcerto_settings_sender', 'cepcerto_cpf_cnpj_remetente', 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.' );
			return (string) get_option( 'cepcerto_cpf_cnpj_remetente', '' );
		}
		return $digits;
	}

	public function sanitize_whatsapp_remetente( $value ) {
		$digits = $this->digits_only( $value );
		$len    = strlen( $digits );
		if ( '' === $digits || ( 10 > $len || 11 < $len ) ) {
			add_settings_error( 'cepcerto_settings_sender', 'cepcerto_whatsapp_remetente', 'Informe um WhatsApp com DDD (10 ou 11 dígitos).' );
			return (string) get_option( 'cepcerto_whatsapp_remetente', '' );
		}
		return $digits;
	}

	public function sanitize_email_remetente( $value ) {
		$email = sanitize_email( (string) $value );
		if ( '' === $email || ! is_email( $email ) ) {
			add_settings_error( 'cepcerto_settings_sender', 'cepcerto_email_remetente', 'Informe um e-mail válido.' );
			return (string) get_option( 'cepcerto_email_remetente', '' );
		}
		return $email;
	}

	public function sanitize_bairro_remetente( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = trim( $value );
		if ( '' === $value ) {
			add_settings_error( 'cepcerto_settings_sender', 'cepcerto_bairro_remetente', 'Informe o bairro.' );
			return (string) get_option( 'cepcerto_bairro_remetente', '' );
		}
		return $value;
	}

	public function sanitize_numero_endereco_remetente( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = trim( $value );
		if ( '' === $value ) {
			add_settings_error( 'cepcerto_settings_sender', 'cepcerto_numero_endereco_remetente', 'Informe o número do endereço.' );
			return (string) get_option( 'cepcerto_numero_endereco_remetente', '' );
		}
		return $value;
	}

	public function sanitize_token_cliente_postagem( $value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return (string) get_option( 'cepcerto_token_cliente_postagem', '' );
	}

	public function sanitize_display_locations( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array();
		}
		$allowed = array( 'product', 'checkout' );
		$value   = array_values( array_intersect( $value, $allowed ) );
		if ( ! in_array( 'checkout', $value, true ) ) {
			$value[] = 'checkout';
		}
		return $value;
	}

	private function get_resettable_options() {
		return array(
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
	}

	public function handle_reset_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissão.' );
		}

		check_admin_referer( 'cepcerto_reset_settings' );

		foreach ( $this->get_resettable_options() as $option_name ) {
			delete_option( (string) $option_name );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'cepcerto',
					'cc_success' => 'reset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab          = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'sender'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed_tabs = array( 'sender', 'saldo', 'logs', 'settings', 'pedidos' );
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'sender';
		}

		$base_url    = add_query_arg( array( 'page' => 'cepcerto' ), admin_url( 'admin.php' ) );
		$ajax_url    = admin_url( 'admin-ajax.php' );
		$nonce_saldo = wp_create_nonce( 'cepcerto_consultar_saldo' );
		$debug       = get_option( 'cepcerto_debug', 'yes' );
		$tabs        = array(
			'sender'   => 'Dados remetente',
			'pedidos'  => 'Pedidos',
			'saldo'    => 'Saldo',
			'settings' => 'Configurações',
		);
		if ( 'yes' === $debug ) {
			$tabs['logs'] = 'Logs';
		}

		?>
		<div class="wrap">
			<div style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
				<div style="display:flex; align-items:center; gap: 10px;">
					<img src="<?php echo esc_url( plugins_url( 'assets/logo-cepcerto.svg', __DIR__ ) ); ?>" alt="CepCerto" style="height: 34px; width: auto;" />
				</div>
				<div id="cepcerto-header-saldo" style="display:flex; align-items:center; gap: 8px;">
					<span style="font-weight:600;">Saldo:</span>
					<span id="cepcerto-header-saldo-value">---</span>
					<button type="button" class="button" id="cepcerto-header-saldo-reload">Recarregar</button>
				</div>
			</div>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $label ) : ?>
					<?php
					$url   = add_query_arg( array( 'tab' => $tab_key ), $base_url );
					$class = ( $tab_key === $tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<div style="margin-top: 16px;">
				<?php
				switch ( $tab ) {
					case 'sender':
						$this->render_sender_tab();
						break;
					case 'saldo':
						$this->render_saldo_tab();
						break;
					case 'pedidos':
						$this->render_orders_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					case 'settings':
					default:
						$this->render_settings_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_orders_tab() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			echo '<p>WooCommerce indisponível.</p>';
			return;
		}

		$per_page = 20;
		$page     = isset( $_GET['orders_page'] ) ? absint( $_GET['orders_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 1 > $page ) {
			$page = 1;
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'all' === $status ) {
			$status = '';
		}

		$args = array(
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
		);
		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		$result      = wc_get_orders( $args );
		$orders      = array();
		$total       = 0;
		$total_pages = 1;
		if ( is_object( $result ) && isset( $result->orders, $result->total, $result->max_num_pages ) ) {
			$orders      = is_array( $result->orders ) ? $result->orders : array();
			$total       = (int) $result->total;
			$total_pages = max( 1, (int) $result->max_num_pages );
		} elseif ( is_array( $result ) ) {
			$orders = $result;
		}

		$base_url = add_query_arg(
			array(
				'page' => 'cepcerto',
				'tab'  => 'pedidos',
			),
			admin_url( 'admin.php' )
		);

		$ajax_url       = admin_url( 'admin-ajax.php' );
		$nonce_etiqueta = wp_create_nonce( 'cepcerto_etiqueta' );

		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin: 10px 0 15px;">
			<input type="hidden" name="page" value="cepcerto" />
			<input type="hidden" name="tab" value="pedidos" />
			<label for="cepcerto_orders_status"><strong>Status</strong></label>
			<select name="status" id="cepcerto_orders_status">
				<option value="all" <?php selected( '' === $status ); ?>>Todos</option>
				<?php foreach ( $statuses as $key => $label ) : ?>
					<?php $clean_key = is_string( $key ) ? str_replace( 'wc-', '', $key ) : ''; ?>
					<option value="<?php echo esc_attr( $clean_key ); ?>" <?php selected( $clean_key, $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="submit" class="button" value="Filtrar" />
		</form>

		<?php if ( empty( $orders ) ) : ?>
			<p>Nenhum pedido encontrado.</p>
		<?php else : ?>
			<table class="widefat striped" id="cepcerto-orders-table">
				<thead>
					<tr>
						<th style="width: 80px;">Pedido</th>
						<th style="width: 140px;">Data</th>
						<th>Cliente</th>
						<th style="width: 110px;">Total</th>
						<th style="width: 120px;">Status</th>
						<th style="width: 160px;">Envio</th>
						<th style="width: 220px;">Etiqueta</th>
						<th style="width: 220px;">Rastreio</th>
						<th style="width: 140px;">Ações</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders as $order ) : ?>
						<?php
						if ( ! $order instanceof WC_Order ) {
							continue;
						}
						$order_id     = $order->get_id();
						$edit_url     = function_exists( 'get_edit_post_link' ) ? get_edit_post_link( $order_id, '' ) : '';
						$date_created = $order->get_date_created();
						$date_str     = $date_created ? $date_created->date_i18n( 'd/m/Y H:i' ) : '';
						$customer     = trim( $order->get_formatted_billing_full_name() );
						if ( '' === $customer ) {
							$customer = (string) $order->get_billing_email();
						}
						$status_name     = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();
						$total_str       = function_exists( 'wc_price' ) ? wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) : (string) $order->get_total();
						$shipping_method = (string) $order->get_shipping_method();
						if ( '' === trim( $shipping_method ) ) {
							$shipping_method = '-';
						}

						$etiqueta       = $order->get_meta( '_cepcerto_etiqueta', true );
						$has_etiqueta   = is_array( $etiqueta ) && ! empty( $etiqueta['codigoObjeto'] );
						$codigo_objeto  = $has_etiqueta ? (string) $etiqueta['codigoObjeto'] : '';
						$pdf_url        = $has_etiqueta && ! empty( $etiqueta['pdfUrlEtiqueta'] ) ? (string) $etiqueta['pdfUrlEtiqueta'] : '';
						$declaracao_url = $has_etiqueta && ! empty( $etiqueta['declaracaoUrl'] ) ? (string) $etiqueta['declaracaoUrl'] : '';
						?>
						<tr data-order-id="<?php echo esc_attr( (string) $order_id ); ?>">
							<td>
								<?php if ( ! empty( $edit_url ) ) : ?>
									<a href="<?php echo esc_url( $edit_url ); ?>"><strong>#<?php echo esc_html( (string) $order->get_order_number() ); ?></strong></a>
								<?php else : ?>
									<strong>#<?php echo esc_html( (string) $order->get_order_number() ); ?></strong>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $date_str ); ?></td>
							<td><?php echo esc_html( $customer ); ?></td>
							<td><?php echo wp_kses_post( $total_str ); ?></td>
							<td><?php echo esc_html( (string) $status_name ); ?></td>
							<td><?php echo esc_html( $shipping_method ); ?></td>
							<td class="cepcerto-col-etiqueta">
								<?php if ( $has_etiqueta ) : ?>
									<code><?php echo esc_html( $codigo_objeto ); ?></code><br>
									<?php if ( '' !== $pdf_url ) : ?>
										<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank">Etiqueta PDF</a>
									<?php endif; ?>
									<?php if ( '' !== $declaracao_url ) : ?>
										<a href="<?php echo esc_url( $declaracao_url ); ?>" target="_blank" style="margin-left:6px;">Declaração</a>
									<?php endif; ?>
								<?php else : ?>
									<span style="color:#999;">—</span>
								<?php endif; ?>
							</td>
							<td class="cepcerto-col-rastreio">
								<?php echo wp_kses_post( $this->get_tracking_cell_html( $order ) ); ?>
							</td>
							<td class="cepcerto-col-acoes">
								<?php if ( $has_etiqueta ) : ?>
									<button type="button" class="button cepcerto-btn-cancelar" data-order-id="<?php echo esc_attr( (string) $order_id ); ?>" data-cod-objeto="<?php echo esc_attr( $codigo_objeto ); ?>">Cancelar</button>
								<?php else : ?>
									<button type="button" class="button button-primary cepcerto-btn-gerar" data-order-id="<?php echo esc_attr( (string) $order_id ); ?>">Gerar Etiqueta</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 1 < $total_pages ) : ?>
			<?php
			$prev     = max( 1, $page - 1 );
			$next     = min( $total_pages, $page + 1 );
			$prev_url = add_query_arg(
				array(
					'orders_page' => $prev,
					'status'      => ( '' === $status ? 'all' : $status ),
				),
				$base_url
			);
			$next_url = add_query_arg(
				array(
					'orders_page' => $next,
					'status'      => ( '' === $status ? 'all' : $status ),
				),
				$base_url
			);
			?>
			<div style="display:flex; align-items:center; gap: 10px; margin-top: 12px;">
				<a class="button" href="<?php echo esc_url( $prev_url ); ?>" <?php echo ( 1 >= $page ) ? 'aria-disabled="true" style="pointer-events:none; opacity:.6;"' : ''; ?>>Anterior</a>
				<span>
					Página <strong><?php echo esc_html( (string) $page ); ?></strong> de <strong><?php echo esc_html( (string) $total_pages ); ?></strong>
					<?php if ( 0 < $total ) : ?>
						(<?php echo esc_html( (string) $total ); ?> pedidos)
					<?php endif; ?>
				</span>
				<a class="button" href="<?php echo esc_url( $next_url ); ?>" <?php echo ( $total_pages <= $page ) ? 'aria-disabled="true" style="pointer-events:none; opacity:.6;"' : ''; ?>>Próxima</a>
			</div>
		<?php endif; ?>
		<?php
	}

	private function render_sender_tab() {
		$origin                    = get_option( 'cepcerto_origin_cep', '' );
		$nome_remetente            = get_option( 'cepcerto_nome_remetente', '' );
		$cpf_cnpj_remetente        = get_option( 'cepcerto_cpf_cnpj_remetente', '' );
		$whatsapp_remetente        = get_option( 'cepcerto_whatsapp_remetente', '' );
		$email_remetente           = get_option( 'cepcerto_email_remetente', '' );
		$logradouro_remetente      = get_option( 'cepcerto_logradouro_remetente', '' );
		$bairro_remetente          = get_option( 'cepcerto_bairro_remetente', '' );
		$numero_endereco_remetente = get_option( 'cepcerto_numero_endereco_remetente', '' );
		$complemento_remetente     = get_option( 'cepcerto_complemento_remetente', '' );
		$ajax_url                  = admin_url( 'admin-ajax.php' );
		$nonce_cep                 = wp_create_nonce( 'cepcerto_consultar_cep_origem' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'cepcerto_settings_sender' ); ?>
			<?php settings_errors( 'cepcerto_settings_sender' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="cepcerto_nome_remetente">Nome completo (obrigatório)</label></th>
						<td>
							<input name="cepcerto_nome_remetente" id="cepcerto_nome_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $nome_remetente ); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_cpf_cnpj_remetente">CPF ou CNPJ (obrigatório)</label></th>
						<td>
							<input name="cepcerto_cpf_cnpj_remetente" id="cepcerto_cpf_cnpj_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $cpf_cnpj_remetente ); ?>" required inputmode="numeric" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_whatsapp_remetente">WhatsApp (obrigatório)</label></th>
						<td>
							<input name="cepcerto_whatsapp_remetente" id="cepcerto_whatsapp_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $whatsapp_remetente ); ?>" required inputmode="numeric" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_email_remetente">E-mail (obrigatório)</label></th>
						<td>
							<input name="cepcerto_email_remetente" id="cepcerto_email_remetente" type="email" class="regular-text" value="<?php echo esc_attr( $email_remetente ); ?>" required autocomplete="email" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_origin_cep">CEP de origem</label></th>
						<td>
							<input name="cepcerto_origin_cep" id="cepcerto_origin_cep" type="text" class="regular-text" value="<?php echo esc_attr( $origin ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_logradouro_remetente">Logradouro (obrigatório)</label></th>
						<td>
							<input name="cepcerto_logradouro_remetente" id="cepcerto_logradouro_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $logradouro_remetente ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_bairro_remetente">Bairro (obrigatório)</label></th>
						<td>
							<input name="cepcerto_bairro_remetente" id="cepcerto_bairro_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $bairro_remetente ); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_numero_endereco_remetente">Número (obrigatório)</label></th>
						<td>
							<input name="cepcerto_numero_endereco_remetente" id="cepcerto_numero_endereco_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $numero_endereco_remetente ); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_complemento_remetente">Complemento (opcional)</label></th>
						<td>
							<input name="cepcerto_complemento_remetente" id="cepcerto_complemento_remetente" type="text" class="regular-text" value="<?php echo esc_attr( $complemento_remetente ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	public function ajax_consultar_cep_origem() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_consultar_cep_origem' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 400 );
		}

		$cep    = isset( $_POST['cep'] ) ? sanitize_text_field( wp_unslash( $_POST['cep'] ) ) : '';
		$api    = new CepCerto_Api();
		$result = $api->consultar_cep( $cep );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'cep'        => isset( $result['cep'] ) ? (string) $result['cep'] : '',
				'logradouro' => isset( $result['logradouro'] ) ? (string) $result['logradouro'] : '',
				'bairro'     => isset( $result['bairro'] ) ? (string) $result['bairro'] : '',
				'localidade' => isset( $result['localidade'] ) ? (string) $result['localidade'] : '',
				'uf'         => isset( $result['uf'] ) ? (string) $result['uf'] : '',
			),
			200
		);
	}

	private function render_settings_tab() {
		$token = get_option( 'cepcerto_token_cliente_postagem', '' );
		$debug = get_option( 'cepcerto_debug', 'yes' );

		$default_width   = get_option( 'cepcerto_default_width', 15.2 );
		$default_height  = get_option( 'cepcerto_default_height', 10.5 );
		$default_length  = get_option( 'cepcerto_default_length', 20.0 );
		$default_weight  = get_option( 'cepcerto_default_weight', 1 );
		$min_order_value = get_option( 'cepcerto_min_order_value', 50 );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'cepcerto_settings' ); ?>

			<h2 class="title">Tamanho da Caixa / Peso padrão</h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="cepcerto_default_width">Largura da caixa (cm)</label></th>
						<td>
							<input name="cepcerto_default_width" id="cepcerto_default_width" type="number" step="0.01" min="15.2" value="<?php echo esc_attr( $default_width ); ?>" />
							<p class="description">Mínimo: 15.2 cm</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_default_height">Altura da caixa (cm)</label></th>
						<td>
							<input name="cepcerto_default_height" id="cepcerto_default_height" type="number" step="0.01" min="10.5" value="<?php echo esc_attr( $default_height ); ?>" />
							<p class="description">Mínimo: 10.5 cm</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_default_length">Comprimento da caixa (cm)</label></th>
						<td>
							<input name="cepcerto_default_length" id="cepcerto_default_length" type="number" step="0.01" min="20.0" value="<?php echo esc_attr( $default_length ); ?>" />
							<p class="description">Mínimo: 20.0 cm</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_default_weight">Peso (kg)</label></th>
						<td>
							<input name="cepcerto_default_weight" id="cepcerto_default_weight" type="number" step="0.01" min="0.01" max="30" value="<?php echo esc_attr( $default_weight ); ?>" />
							<p class="description">Mínimo: maior que 0 kg / Máximo: 30 kg</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_min_order_value">Valor mínimo da encomenda (R$)</label></th>
						<td>
							<input name="cepcerto_min_order_value" id="cepcerto_min_order_value" type="number" step="0.01" min="50" max="35000" value="<?php echo esc_attr( $min_order_value ); ?>" />
							<p class="description">Valor mínimo para cotação (entre R$ 50,00 e R$ 35.000,00). Se o carrinho for menor, será usado este valor.</p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2 class="title">Exibição do cálculo de frete</h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">Exibir calculadora em</th>
						<td>
							<?php
							$display_locations = get_option( 'cepcerto_display_locations', array( 'product', 'checkout' ) );
							if ( ! is_array( $display_locations ) ) {
								$display_locations = array( 'product', 'checkout' );
							}
							?>
							<input type="hidden" name="cepcerto_display_locations" value="" />
							<label style="display:block; margin-bottom: 6px;">
								<input type="checkbox" name="cepcerto_display_locations[]" value="product" <?php checked( in_array( 'product', $display_locations, true ) ); ?> />
								Página do produto
							</label>
							<label style="display:block; margin-bottom: 6px;">
								<input type="hidden" name="cepcerto_display_locations[]" value="checkout" />
								<input type="checkbox" value="checkout" checked="checked" disabled="disabled" />
								Checkout / Carrinho <span class="description">(obrigatório)</span>
							</label>
							<p class="description">Selecione onde a calculadora de frete deve ser exibida. Você pode selecionar mais de uma opção.</p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2 class="title">Token e Debug</h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">Token cliente postagem</th>
						<td>
							<?php
							$masked_token = '';
							if ( strlen( $token ) > 8 ) {
								$masked_token = substr( $token, 0, 4 ) . '...' . substr( $token, -4 );
							} elseif ( strlen( $token ) > 0 ) {
								$masked_token = '****';
							} else {
								$masked_token = 'Não configurado';
							}
							?>
							<code style="font-size: 14px; padding: 4px 8px; background: #f0f0f0; border-radius: 3px;"><?php echo esc_html( $masked_token ); ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row">Debug</th>
						<td>
							<label>
								<input type="hidden" name="cepcerto_debug" value="no" />
								<input name="cepcerto_debug" type="checkbox" value="yes" <?php checked( $debug, 'yes' ); ?> />
								<?php echo 'yes' === $debug ? 'Desativar' : 'Ativar'; ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>

		<?php
	}

	private function render_saldo_tab() {
		$ajax_url         = admin_url( 'admin-ajax.php' );
		$nonce_saldo      = wp_create_nonce( 'cepcerto_consultar_saldo' );
		$nonce_credito    = wp_create_nonce( 'cepcerto_adicionar_credito' );
		$nonce_financeiro = wp_create_nonce( 'cepcerto_financeiro' );
		?>
		<div id="cepcerto-saldo-notices"></div>

		<div style="display:flex; gap:16px; align-items:flex-start; margin-top:20px;">
			<div class="card" style="flex:1; padding:20px;">
			<h2 style="margin-top:0;">Adicionar Crédito</h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="cepcerto_valor_credito">Valor (R$)</label></th>
						<td>
							<input type="number" id="cepcerto_valor_credito" class="regular-text" step="0.01" min="1" placeholder="Ex: 100.00" />
						</td>
					</tr>
				</tbody>
			</table>
			<p>
				<button type="button" class="button button-primary" id="cepcerto-btn-credito">Gerar cobrança PIX</button>
			</p>

			<div id="cepcerto-credito-resultado" style="display:none;margin-top:20px;">
				<hr />
				<h3>Dados do pagamento</h3>
				<table class="form-table" role="presentation" id="cepcerto-credito-tabela">
					<tbody></tbody>
				</table>

				<div id="cepcerto-pix-section" style="margin-top:15px;text-align:center;">
					<h3>QR Code PIX</h3>
					<div id="cepcerto-qrcode-container" style="margin:15px 0;"></div>
					<div style="margin-top:10px;">
						<label for="cepcerto_copia_cola"><strong>PIX Copia e Cola:</strong></label>
						<div style="display:flex;gap:8px;margin-top:6px;max-width:600px;margin-left:auto;margin-right:auto;">
							<input type="text" id="cepcerto_copia_cola" class="regular-text" style="flex:1;" readonly />
							<button type="button" class="button" id="cepcerto-btn-copiar">Copiar</button>
						</div>
					</div>
				</div>
			</div>
			</div>
			<div class="card" style="flex:1; padding:20px;" id="cepcerto-extrato-card">
				<h2 style="margin-top:0;">Extrato</h2>
				<div id="cepcerto-extrato-list" style="max-height:600px; overflow:auto; border:1px solid #eee; border-radius:8px;"></div>
				<div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
					<button type="button" class="button" id="cepcerto-extrato-reload">Recarregar</button>
					<button type="button" class="button" id="cepcerto-extrato-load-more">Carregar mais</button>
					<span id="cepcerto-extrato-status" style="color:#666;"></span>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_logs_tab() {
		$enabled      = get_option( 'cepcerto_debug', 'no' );
		$file         = class_exists( 'CepCerto_Logger' ) ? CepCerto_Logger::get_latest_log_file() : false;
		$download_url = wp_nonce_url( admin_url( 'admin-post.php?action=cepcerto_download_log' ), 'cepcerto_download_log' );

		$minutes = isset( $_GET['minutes'] ) ? absint( wp_unslash( $_GET['minutes'] ) ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $minutes <= 0 ) {
			$minutes = 10;
		}
		$minutes = min( $minutes, 10080 );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'table'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $view, array( 'table', 'raw' ), true ) ) {
			$view = 'table';
		}

		$base_url  = add_query_arg(
			array(
				'page' => 'cepcerto',
				'tab'  => 'logs',
			),
			admin_url( 'admin.php' )
		);
		$table_url = add_query_arg(
			array(
				'minutes' => $minutes,
				'view'    => 'table',
			),
			$base_url
		);
		$raw_url   = add_query_arg(
			array(
				'minutes' => $minutes,
				'view'    => 'raw',
			),
			$base_url
		);

		$lines = array();
		if ( $file && file_exists( $file ) ) {
			$lines = $this->tail_file_lines( $file, 2000 );
		}

		$cutoff_ts   = time() - ( $minutes * MINUTE_IN_SECONDS );
		$rows        = array();
		$raw_content = '';
		foreach ( $lines as $line ) {
			$parsed = $this->parse_log_line( $line );
			if ( $parsed && isset( $parsed['ts'] ) && is_int( $parsed['ts'] ) && $cutoff_ts > $parsed['ts'] ) {
				continue;
			}
			if ( $parsed ) {
				$rows[] = $parsed;
			} else {
				$rows[] = array(
					'ts'       => null,
					'ts_str'   => '',
					'level'    => 'RAW',
					'endpoint' => '',
					'message'  => (string) $line,
					'context'  => '',
				);
			}
			$raw_content .= $line . "\n";
		}
		?>
		<p>
			<strong>Status do log:</strong> <?php echo ( 'yes' === $enabled ) ? 'Ativo' : 'Inativo'; ?>
		</p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin: 10px 0 15px;">
			<input type="hidden" name="page" value="cepcerto" />
			<input type="hidden" name="tab" value="logs" />
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
			<label for="cepcerto_logs_minutes"><strong>Exibir últimos</strong></label>
			<input type="number" min="1" max="10080" step="1" id="cepcerto_logs_minutes" name="minutes" value="<?php echo esc_attr( (string) $minutes ); ?>" style="width: 90px;" />
			<span>minutos</span>
			<input type="submit" class="button" value="Aplicar" />
			<span style="margin-left:12px;">
				<a class="button <?php echo ( 'table' === $view ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $table_url ); ?>">Tabela</a>
				<a class="button <?php echo ( 'raw' === $view ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $raw_url ); ?>">Raw</a>
			</span>
		</form>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $download_url ); ?>">Baixar log</a>
		</p>
		<?php if ( empty( $rows ) ) : ?>
			<p>Nenhum log encontrado para o período selecionado.</p>
		<?php else : ?>
			<?php if ( 'raw' === $view ) : ?>
				<textarea style="width:100%;min-height:520px;font-family:monospace;" readonly><?php echo esc_textarea( rtrim( $raw_content ) ); ?></textarea>
			<?php else : ?>
				<div style="overflow:auto; max-height: 620px; border: 1px solid #ccd0d4; background: #fff;">
					<table class="widefat striped" style="margin:0;">
						<thead>
							<tr>
								<th style="width: 170px;">Data</th>
								<th style="width: 90px;">Nível</th>
								<th>Endpoint</th>
								<th style="width: 35%;">Contexto</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_reverse( $rows ) as $row ) : ?>
								<?php
								$level = isset( $row['level'] ) ? strtoupper( (string) $row['level'] ) : '';
								$style = '';
								if ( in_array( $level, array( 'ERROR', 'CRITICAL' ), true ) ) {
									$style = 'background:#fbeaea;';
								} elseif ( 'WARNING' === $level ) {
									$style = 'background:#fff8e5;';
								}
								?>
								<tr style="<?php echo esc_attr( $style ); ?>">
									<td><code><?php echo esc_html( (string) ( $row['ts_str'] ?? '' ) ); ?></code></td>
									<td><strong><?php echo esc_html( $level ); ?></strong></td>
									<td style="white-space: pre-wrap;"><code><?php echo esc_html( (string) ( $row['endpoint'] ?? ( $row['message'] ?? '' ) ) ); ?></code></td>
									<td style="white-space: pre-wrap;"><code><?php echo esc_html( (string) ( $row['context'] ?? '' ) ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<script>
			(function() {
				try {
					var needles = [
						'Atenção usuário do Melhor Envio',
						'Atenção usuário do Plugin Melhor Envio',
						'Plugin Melhor Envio',
						'Melhor Envio'
					];
					var nodes = document.querySelectorAll('.notice, .updated, .error');
					Array.prototype.forEach.call(nodes, function(n) {
						var text = (n && n.textContent) ? n.textContent : '';
						var matched = needles.some(function(s) {
							return text.indexOf(s) !== -1;
						});
						if (matched) {
							n.parentNode && n.parentNode.removeChild(n);
						}
					});
				} catch (e) {}
			})();
		</script>
		<?php
	}

	public function ajax_consultar_saldo() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_consultar_saldo' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 400 );
		}

		$token = get_option( 'cepcerto_token_cliente_postagem', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Token de cliente não configurado.' ), 400 );
		}

		$api    = new CepCerto_Api();
		$result = $api->saldo( $token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result, 200 );
	}

	public function ajax_financeiro() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_financeiro' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 400 );
		}

		$token = get_option( 'cepcerto_token_cliente_postagem', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Token de cliente não configurado.' ), 400 );
		}

		$limit  = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : null;
		$offset = isset( $_POST['offset'] ) ? intval( wp_unslash( $_POST['offset'] ) ) : null;
		if ( null !== $limit && 0 >= $limit ) {
			$limit = null; }
		if ( null !== $offset && 0 > $offset ) {
			$offset = null; }

		$api    = new CepCerto_Api();
		$result = $api->financeiro( $token, $limit, $offset );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result, 200 );
	}

	public function ajax_adicionar_credito() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_adicionar_credito' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 400 );
		}

		$valor = isset( $_POST['valor_credito'] ) ? sanitize_text_field( wp_unslash( $_POST['valor_credito'] ) ) : '';
		if ( empty( $valor ) ) {
			wp_send_json_error( array( 'message' => 'Informe o valor do crédito.' ), 400 );
		}

		$token = get_option( 'cepcerto_token_cliente_postagem', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Token de cliente não configurado.' ), 400 );
		}

		$api    = new CepCerto_Api();
		$result = $api->credito( $token, $valor );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result, 200 );
	}

	public function ajax_gerar_etiqueta() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_etiqueta' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 400 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( 1 > $order_id ) {
			wp_send_json_error( array( 'message' => 'Pedido inválido.' ), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => 'Pedido não encontrado.' ), 404 );
		}

		$existing = $order->get_meta( '_cepcerto_etiqueta', true );
		if ( is_array( $existing ) && ! empty( $existing['codigoObjeto'] ) ) {
			wp_send_json_error( array( 'message' => 'Etiqueta já gerada para este pedido.' ), 400 );
		}

		$token = get_option( 'cepcerto_token_cliente_postagem', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Token de cliente não configurado.' ), 400 );
		}

		$tipo_entrega = $this->resolve_tipo_entrega( $order );

		$cep_remetente     = preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_origin_cep', '' ) );
		$shipping_postcode = (string) $order->get_shipping_postcode();
		$billing_postcode  = (string) $order->get_billing_postcode();
		$cep_destinatario  = preg_replace( '/\D+/', '', '' !== $shipping_postcode ? $shipping_postcode : $billing_postcode );

		if ( '' === $cep_remetente || '' === $cep_destinatario ) {
			wp_send_json_error( array( 'message' => 'CEP de origem ou destino não configurado.' ), 400 );
		}

		$default_weight = $this->etiqueta_to_float( get_option( 'cepcerto_default_weight', 1 ) );
		$default_width  = $this->etiqueta_to_float( get_option( 'cepcerto_default_width', 10 ) );
		$default_height = $this->etiqueta_to_float( get_option( 'cepcerto_default_height', 10 ) );
		$default_length = $this->etiqueta_to_float( get_option( 'cepcerto_default_length', 10 ) );

		$default_weight_kg = $this->etiqueta_convert_weight_to_kg( $default_weight );

		$total_weight   = 0.0;
		$total_quantity = 0;
		$produtos       = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$qty     = max( 1, (int) $item->get_quantity() );

			if ( $product instanceof WC_Product && ! $product->is_virtual() ) {
				$total_quantity   += $qty;
				$product_weight    = $this->etiqueta_to_float( $product->get_weight() );
				$product_weight_kg = 0 < $product_weight ? $this->etiqueta_convert_weight_to_kg( $product_weight ) : $default_weight_kg;
				$total_weight     += $product_weight_kg * $qty;
			} elseif ( ! ( $product instanceof WC_Product ) ) {
				$total_quantity += $qty;
				$total_weight   += $default_weight_kg * $qty;
			}

			$item_total = (float) $item->get_total();
			$unit_price = 0 < $qty ? round( $item_total / $qty, 2 ) : $item_total;

			$produtos[] = array(
				'descricao'  => mb_substr( (string) $item->get_name(), 0, 80 ),
				'valor'      => number_format( $unit_price, 2, ',', '' ),
				'quantidade' => (string) $qty,
			);
		}

		if ( 0 >= $total_weight && 0 < $total_quantity ) {
			$total_weight = $default_weight_kg * $total_quantity;
		}

		$final_width  = $this->etiqueta_convert_dimension_to_cm( $default_width );
		$final_height = $this->etiqueta_convert_dimension_to_cm( $default_height );
		$final_length = $this->etiqueta_convert_dimension_to_cm( $default_length );

		if ( 0 >= $total_weight || 0 >= $final_width || 0 >= $final_height || 0 >= $final_length ) {
			wp_send_json_error( array( 'message' => 'Dimensões ou peso padrão inválidos. Verifique as configurações.' ), 400 );
		}

		$min_order_value = (float) get_option( 'cepcerto_min_order_value', 50 );
		$order_total     = (float) $order->get_total();
		$valor_encomenda = max( $min_order_value, min( 35000, $order_total ) );

		$shipping_first_name = (string) $order->get_shipping_first_name();
		$shipping_last_name  = (string) $order->get_shipping_last_name();
		$nome_destinatario   = trim( $shipping_first_name . ' ' . $shipping_last_name );
		if ( '' === $nome_destinatario ) {
			$nome_destinatario = trim( $order->get_formatted_billing_full_name() );
		}

		$billing_phone = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
		if ( '' === $billing_phone ) {
			$billing_phone = '11975532552'; // TODO: AQUI - telefone fallback chumbado
		}
		$billing_email = (string) $order->get_billing_email();

		$cpf_cnpj_dest = '';
		$meta_keys     = array( '_billing_cpf', '_billing_cnpj', '_billing_cpf_cnpj', 'billing_cpf' );
		foreach ( $meta_keys as $mk ) {
			$val = $order->get_meta( $mk, true );
			if ( ! empty( $val ) ) {
				$cpf_cnpj_dest = preg_replace( '/\D+/', '', (string) $val );
				break;
			}
		}
		if ( '' === $cpf_cnpj_dest ) {
			$cpf_cnpj_dest = '44598844884'; // TODO: AQUI - CPF fallback chumbado
		}

		$shipping_address_1 = (string) $order->get_shipping_address_1();
		$shipping_address_2 = (string) $order->get_shipping_address_2();
		$billing_address_1  = (string) $order->get_billing_address_1();
		$billing_address_2  = (string) $order->get_billing_address_2();

		$logradouro_dest  = '' !== $shipping_address_1 ? $shipping_address_1 : $billing_address_1;
		$complemento_dest = '' !== $shipping_address_2 ? $shipping_address_2 : $billing_address_2;
		if ( '' === trim( $complemento_dest ) ) {
			$complemento_dest = '-'; // TODO: AQUI - complemento fallback chumbado
		}

		$numero_dest = '';
		$bairro_dest = '';
		$num_meta    = $order->get_meta( '_shipping_number', true );
		if ( empty( $num_meta ) ) {
			$num_meta = $order->get_meta( '_billing_number', true );
		}
		$numero_dest = ! empty( $num_meta ) ? (string) $num_meta : 'S/N';

		$bairro_meta = $order->get_meta( '_shipping_neighborhood', true );
		if ( empty( $bairro_meta ) ) {
			$bairro_meta = $order->get_meta( '_billing_neighborhood', true );
		}
		$bairro_dest = ! empty( $bairro_meta ) ? (string) $bairro_meta : 'Centro'; // TODO: AQUI - bairro fallback chumbado

		$payload = array(
			'token_cliente_postagem'       => $token,
			'tipo_entrega'                 => $tipo_entrega,
			'cep_remetente'                => $cep_remetente,
			'cep_destinatario'             => $cep_destinatario,
			'peso'                         => $this->etiqueta_format_number( $total_weight ),
			'altura'                       => $this->etiqueta_format_number( $final_height ),
			'largura'                      => $this->etiqueta_format_number( $final_width ),
			'comprimento'                  => $this->etiqueta_format_number( $final_length ),
			'valor_encomenda'              => (string) $valor_encomenda,
			'nome_remetente'               => (string) get_option( 'cepcerto_nome_remetente', '' ),
			'cpf_cnpj_remetente'           => preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_cpf_cnpj_remetente', '' ) ),
			'whatsapp_remetente'           => preg_replace( '/\D+/', '', (string) get_option( 'cepcerto_whatsapp_remetente', '' ) ),
			'email_remetente'              => (string) get_option( 'cepcerto_email_remetente', '' ),
			'logradouro_remetente'         => (string) get_option( 'cepcerto_logradouro_remetente', '' ),
			'bairro_remetente'             => (string) get_option( 'cepcerto_bairro_remetente', '' ),
			'numero_endereco_remetente'    => (string) get_option( 'cepcerto_numero_endereco_remetente', '' ),
			'complemento_remetente'        => (string) get_option( 'cepcerto_complemento_remetente', '' ),
			'nome_destinatario'            => $nome_destinatario,
			'cpf_cnpj_destinatario'        => $cpf_cnpj_dest,
			'whatsapp_destinatario'        => $billing_phone,
			'email_destinatario'           => mb_substr( $billing_email, 0, 50 ),
			'logradouro_destinatario'      => mb_substr( $logradouro_dest, 0, 40 ),
			'bairro_destinatario'          => mb_substr( $bairro_dest, 0, 30 ),
			'numero_endereco_destinatario' => mb_substr( $numero_dest, 0, 10 ),
			'complemento_destinatario'     => mb_substr( $complemento_dest, 0, 20 ),
			'tipo_doc_fiscal'              => 'declaracao',
			'produtos'                     => $produtos,
		);

		$api    = new CepCerto_Api();
		$result = $api->postagem_frete( $payload );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		if ( ! is_array( $result ) ) {
			wp_send_json_error( array( 'message' => 'Resposta inválida da API.' ), 400 );
		}

		$sucesso = isset( $result['sucesso'] ) ? (bool) $result['sucesso'] : false;
		if ( ! $sucesso ) {
			$msg = isset( $result['mensagem'] ) ? (string) $result['mensagem'] : 'Erro desconhecido da API.';
			wp_send_json_error( array( 'message' => $msg ), 400 );
		}

		$frete = isset( $result['frete'] ) && is_array( $result['frete'] ) ? $result['frete'] : array();

		$order->update_meta_data( '_cepcerto_etiqueta', $frete );
		$order->save();

		wp_send_json_success(
			array(
				'frete'        => $frete,
				'message'      => isset( $result['mensagem'] ) ? (string) $result['mensagem'] : 'Etiqueta gerada com sucesso.',
				'reload_saldo' => true,
			),
			200
		);
	}

	public function ajax_cancelar_etiqueta() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cepcerto_etiqueta' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 400 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( 1 > $order_id ) {
			wp_send_json_error( array( 'message' => 'Pedido inválido.' ), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => 'Pedido não encontrado.' ), 404 );
		}

		$etiqueta = $order->get_meta( '_cepcerto_etiqueta', true );
		if ( ! is_array( $etiqueta ) || empty( $etiqueta['codigoObjeto'] ) ) {
			wp_send_json_error( array( 'message' => 'Nenhuma etiqueta encontrada para este pedido.' ), 400 );
		}

		$token = get_option( 'cepcerto_token_cliente_postagem', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Token de cliente não configurado.' ), 400 );
		}

		$cod_objeto = (string) $etiqueta['codigoObjeto'];

		$api    = new CepCerto_Api();
		$result = $api->cancela_postagem( $token, $cod_objeto );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		if ( ! is_array( $result ) ) {
			wp_send_json_error( array( 'message' => 'Resposta inválida da API.' ), 400 );
		}

		$sucesso = isset( $result['sucesso'] ) ? (bool) $result['sucesso'] : false;
		if ( ! $sucesso ) {
			$msg = isset( $result['mensagem'] ) ? (string) $result['mensagem'] : 'Erro ao cancelar.';
			wp_send_json_error( array( 'message' => $msg ), 400 );
		}

		$order->delete_meta_data( '_cepcerto_etiqueta' );
		$order->save();

		wp_send_json_success(
			array(
				'message'      => isset( $result['mensagem'] ) ? (string) $result['mensagem'] : 'Etiqueta cancelada com sucesso.',
				'reload_saldo' => true,
			),
			200
		);
	}

	private function resolve_tipo_entrega( $order ) {
		$shipping_methods = $order->get_shipping_methods();
		foreach ( $shipping_methods as $method ) {
			$method_id = (string) $method->get_method_id();
			if ( 0 === strpos( $method_id, 'cepcerto_' ) ) {
				$tipo = str_replace( 'cepcerto_', '', $method_id );
				$map  = array(
					'pac'            => 'pac',
					'sedex'          => 'sedex',
					'jadlog_package' => 'jadlog_package',
					'jadlog_dotcom'  => 'jadlog_dotcom',
				);
				if ( isset( $map[ $tipo ] ) ) {
					return $map[ $tipo ];
				}
			}
			$method_title = strtolower( (string) $method->get_method_title() );
			if ( false !== strpos( $method_title, 'sedex' ) ) {
				return 'sedex';
			}
			if ( false !== strpos( $method_title, 'pac' ) ) {
				return 'pac';
			}
		}
		return 'pac';
	}

	private function etiqueta_to_float( $value ) {
		$value = (string) $value;
		$value = str_replace( ',', '.', $value );
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		if ( '' === $value || '-' === $value ) {
			return 0.0;
		}
		return (float) $value;
	}

	private function etiqueta_convert_weight_to_kg( $value ) {
		$unit  = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
		$value = (float) str_replace( ',', '.', (string) $value );
		if ( 'g' === $unit ) {
			return $value / 1000;
		}
		return $value;
	}

	private function etiqueta_convert_dimension_to_cm( $value ) {
		$unit  = strtolower( (string) get_option( 'woocommerce_dimension_unit', 'cm' ) );
		$value = (float) str_replace( ',', '.', (string) $value );
		if ( 'm' === $unit ) {
			return $value * 100;
		}
		if ( 'mm' === $unit ) {
			return $value / 10;
		}
		return $value;
	}

	private function etiqueta_format_number( $value ) {
		$value = (float) $value;
		return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
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

	private function tail_file_lines( $file, $max_lines = 200 ) {
		$lines = @file( $file, FILE_IGNORE_NEW_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$total = count( $lines );
		$start = max( 0, $total - (int) $max_lines );
		return array_slice( $lines, $start );
	}

	private function parse_log_line( $line ) {
		$line = (string) $line;
		$line = trim( $line );
		if ( '' === $line ) {
			return null;
		}

		$re = '/^\[(?<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[(?<level>[^\]]+)\] (?<message>.*?)(?:\s+(?<json>\{.*\}))?$/';
		if ( ! preg_match( $re, $line, $m ) ) {
			return null;
		}

		$ts       = strtotime( $m['date'] . ' UTC' );
		$ctx      = '';
		$endpoint = '';
		if ( isset( $m['json'] ) && '' !== trim( (string) $m['json'] ) ) {
			$ctx_raw = (string) $m['json'];
			$decoded = json_decode( $ctx_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$endpoint = $this->extract_endpoint_from_context( $decoded );
				$decoded  = $this->normalize_log_context_for_display( $decoded );
				$ctx      = wp_json_encode(
					$decoded,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				);
			} else {
				$ctx = $ctx_raw;
			}
		}

		return array(
			'ts'       => is_int( $ts ) ? $ts : null,
			'ts_str'   => (string) $m['date'] . ' UTC',
			'level'    => strtoupper( (string) $m['level'] ),
			'endpoint' => (string) $endpoint,
			'message'  => (string) $m['message'],
			'context'  => $ctx,
		);
	}

	private function extract_endpoint_from_context( $context ) {
		if ( ! is_array( $context ) ) {
			return '';
		}

		if ( isset( $context['url'] ) && is_string( $context['url'] ) && '' !== trim( $context['url'] ) ) {
			return trim( $context['url'] );
		}

		if ( isset( $context['endpoint'] ) && is_string( $context['endpoint'] ) && '' !== trim( $context['endpoint'] ) ) {
			return trim( $context['endpoint'] );
		}

		return '';
	}

	private function normalize_log_context_for_display( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$key = strtolower( (string) $k );
				if ( in_array( $key, array( 'api_key', 'token', 'authorization', 'token_cliente_postagem' ), true ) ) {
					$out[ $k ] = '***';
					continue;
				}
				$out[ $k ] = $this->normalize_log_context_for_display( $v );
			}
			return $out;
		}

		if ( is_string( $value ) ) {
			$trim        = trim( $value );
			$starts_json = '' !== $trim && ( '{' === substr( $trim, 0, 1 ) || '[' === substr( $trim, 0, 1 ) );
			if ( $starts_json ) {
				$decoded = json_decode( $trim, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					return $this->normalize_log_context_for_display( $decoded );
				}
			}
		}

		return $value;
	}
}
