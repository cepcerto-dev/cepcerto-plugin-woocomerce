<?php

if (! defined('ABSPATH')) {
	exit;
}

class CepCerto_Admin
{
	public function init()
	{
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_init', array($this, 'maybe_redirect_legacy_pages'));
		add_action('admin_post_cepcerto_download_log', array($this, 'download_log'));
		add_action('admin_post_cepcerto_reset_settings', array($this, 'handle_reset_settings'));
		add_action('wp_ajax_cepcerto_test_registro', array($this, 'ajax_test_registro'));
		add_action('wp_ajax_cepcerto_consultar_cep_origem', array($this, 'ajax_consultar_cep_origem'));
		add_action('wp_ajax_cepcerto_consultar_saldo', array($this, 'ajax_consultar_saldo'));
		add_action('wp_ajax_cepcerto_adicionar_credito', array($this, 'ajax_adicionar_credito'));
		add_action('wp_ajax_cepcerto_gerar_etiqueta', array($this, 'ajax_gerar_etiqueta'));
		add_action('wp_ajax_cepcerto_cancelar_etiqueta', array($this, 'ajax_cancelar_etiqueta'));
	}

	public function register_menu()
	{
		add_menu_page(
			'CepCerto',
			'CepCerto',
			'manage_woocommerce',
			'cepcerto',
			array($this, 'render_page'),
			'dashicons-location-alt'
		);
	}

	public function maybe_redirect_legacy_pages()
	{
		if (! is_admin()) {
			return;
		}

		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		if ($page === 'cepcerto-saldo') {
			wp_safe_redirect(add_query_arg(array('page' => 'cepcerto', 'tab' => 'saldo'), admin_url('admin.php')));
			exit;
		}
		if ($page === 'cepcerto-logs') {
			wp_safe_redirect(add_query_arg(array('page' => 'cepcerto', 'tab' => 'logs'), admin_url('admin.php')));
			exit;
		}
	}

	public function register_settings()
	{
		register_setting(
			'cepcerto_settings',
			'cepcerto_token_cliente_postagem',
			array(
				'sanitize_callback' => array($this, 'sanitize_token_cliente_postagem'),
			)
		);
		register_setting('cepcerto_settings', 'cepcerto_debug');

		register_setting('cepcerto_settings', 'cepcerto_default_width');
		register_setting('cepcerto_settings', 'cepcerto_default_height');
		register_setting('cepcerto_settings', 'cepcerto_default_length');
		register_setting('cepcerto_settings', 'cepcerto_default_weight');
		register_setting('cepcerto_settings', 'cepcerto_min_order_value');
		register_setting('cepcerto_settings', 'cepcerto_display_locations', array(
			'type'              => 'array',
			'sanitize_callback' => array($this, 'sanitize_display_locations'),
			'default'           => array('product', 'checkout'),
		));

		register_setting('cepcerto_settings_sender', 'cepcerto_origin_cep');
		register_setting('cepcerto_settings_sender', 'cepcerto_nome_remetente');
		register_setting('cepcerto_settings_sender', 'cepcerto_cpf_cnpj_remetente', array(
			'sanitize_callback' => array($this, 'sanitize_cpf_cnpj_remetente'),
		));
		register_setting('cepcerto_settings_sender', 'cepcerto_whatsapp_remetente', array(
			'sanitize_callback' => array($this, 'sanitize_whatsapp_remetente'),
		));
		register_setting('cepcerto_settings_sender', 'cepcerto_email_remetente', array(
			'sanitize_callback' => array($this, 'sanitize_email_remetente'),
		));
		register_setting('cepcerto_settings_sender', 'cepcerto_logradouro_remetente');
		register_setting('cepcerto_settings_sender', 'cepcerto_bairro_remetente', array(
			'sanitize_callback' => array($this, 'sanitize_bairro_remetente'),
		));
		register_setting('cepcerto_settings_sender', 'cepcerto_numero_endereco_remetente', array(
			'sanitize_callback' => array($this, 'sanitize_numero_endereco_remetente'),
		));
		register_setting('cepcerto_settings_sender', 'cepcerto_complemento_remetente');
	}

	private function digits_only($value)
	{
		$value = (string) $value;
		$value = preg_replace('/\D+/', '', $value);
		return (string) $value;
	}

	public function sanitize_cpf_cnpj_remetente($value)
	{
		$digits = $this->digits_only($value);
		if ($digits === '' || (strlen($digits) !== 11 && strlen($digits) !== 14)) {
			add_settings_error('cepcerto_settings_sender', 'cepcerto_cpf_cnpj_remetente', 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
			return (string) get_option('cepcerto_cpf_cnpj_remetente', '');
		}
		return $digits;
	}

	public function sanitize_whatsapp_remetente($value)
	{
		$digits = $this->digits_only($value);
		$len = strlen($digits);
		if ($digits === '' || ($len < 10 || $len > 11)) {
			add_settings_error('cepcerto_settings_sender', 'cepcerto_whatsapp_remetente', 'Informe um WhatsApp com DDD (10 ou 11 dígitos).');
			return (string) get_option('cepcerto_whatsapp_remetente', '');
		}
		return $digits;
	}

	public function sanitize_email_remetente($value)
	{
		$email = sanitize_email((string) $value);
		if ($email === '' || ! is_email($email)) {
			add_settings_error('cepcerto_settings_sender', 'cepcerto_email_remetente', 'Informe um e-mail válido.');
			return (string) get_option('cepcerto_email_remetente', '');
		}
		return $email;
	}

	public function sanitize_bairro_remetente($value)
	{
		$value = sanitize_text_field((string) $value);
		$value = trim($value);
		if ($value === '') {
			add_settings_error('cepcerto_settings_sender', 'cepcerto_bairro_remetente', 'Informe o bairro.');
			return (string) get_option('cepcerto_bairro_remetente', '');
		}
		return $value;
	}

	public function sanitize_numero_endereco_remetente($value)
	{
		$value = sanitize_text_field((string) $value);
		$value = trim($value);
		if ($value === '') {
			add_settings_error('cepcerto_settings_sender', 'cepcerto_numero_endereco_remetente', 'Informe o número do endereço.');
			return (string) get_option('cepcerto_numero_endereco_remetente', '');
		}
		return $value;
	}

	public function sanitize_token_cliente_postagem($value)
	{
		return (string) get_option('cepcerto_token_cliente_postagem', '');
	}

	public function sanitize_display_locations($value)
	{
		if (! is_array($value)) {
			$value = array();
		}
		$allowed = array('product', 'checkout');
		$value = array_values(array_intersect($value, $allowed));
		if (! in_array('checkout', $value, true)) {
			$value[] = 'checkout';
		}
		return $value;
	}

	private function get_resettable_options()
	{
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

	public function handle_reset_settings()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die('Sem permissão.');
		}

		check_admin_referer('cepcerto_reset_settings');

		foreach ($this->get_resettable_options() as $optionName) {
			delete_option((string) $optionName);
		}

		wp_safe_redirect(add_query_arg(array('page' => 'cepcerto', 'cc_success' => 'reset'), admin_url('admin.php')));
		exit;
	}

	public function render_page()
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'sender';
		$allowedTabs = array('sender', 'saldo', 'logs', 'settings', 'pedidos');
		if (! in_array($tab, $allowedTabs, true)) {
			$tab = 'sender';
		}

		$baseUrl = add_query_arg(array('page' => 'cepcerto'), admin_url('admin.php'));
		$ajaxUrl = admin_url('admin-ajax.php');
		$nonceSaldo = wp_create_nonce('cepcerto_consultar_saldo');
		$tabs = array(
			'sender'   => 'Dados remetente',
			'pedidos'  => 'Pedidos',
			'saldo'    => 'Saldo',
			'logs'     => 'Logs',
			'settings' => 'Configurações',
		);

?>
		<div class="wrap">
			<div style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
				<div style="display:flex; align-items:center; gap: 10px;">
					<img src="<?php echo esc_url('https://cepcerto.com//imagens/logo-cepcerto.svg'); ?>" alt="CepCerto" style="height: 34px; width: auto;" />
				</div>
				<div id="cepcerto-header-saldo" style="display:flex; align-items:center; gap: 8px;">
					<span style="font-weight:600;">Saldo:</span>
					<span id="cepcerto-header-saldo-value">---</span>
					<button type="button" class="button" id="cepcerto-header-saldo-reload">Recarregar</button>
				</div>
			</div>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $tabKey => $label) : ?>
					<?php
					$url = add_query_arg(array('tab' => $tabKey), $baseUrl);
					$class = ($tabKey === $tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
					?>
					<a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></a>
				<?php endforeach; ?>
			</h2>
			<script>
				(function() {
					var ajaxUrl = <?php echo wp_json_encode($ajaxUrl); ?>;
					var nonceSaldo = <?php echo wp_json_encode($nonceSaldo); ?>;
					var valueEl = document.getElementById('cepcerto-header-saldo-value');
					var btn = document.getElementById('cepcerto-header-saldo-reload');

					function setValue(text) {
						if (!valueEl) return;
						valueEl.textContent = text;
					}

					function formatMoney(val) {
						var n = Number(val);
						if (!isFinite(n)) return null;
						try {
							return n.toLocaleString('pt-BR', {
								style: 'currency',
								currency: 'BRL'
							});
						} catch (e) {
							return 'R$ ' + n.toFixed(2);
						}
					}

					function postAjax(action, payload) {
						var body = new URLSearchParams();
						body.set('action', action);
						Object.keys(payload || {}).forEach(function(k) {
							body.set(k, payload[k]);
						});
						return fetch(ajaxUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
							},
							body: body.toString(),
							credentials: 'same-origin'
						}).then(function(r) {
							return r.json().catch(function() {
								return {
									success: false,
									data: {
										message: 'Resposta inválida.'
									}
								};
							});
						});
					}

					function extractSaldo(data) {
						if (!data || typeof data !== 'object') return null;
						var keys = ['saldo_atual', 'saldoAtual', 'saldo', 'valor', 'credito'];
						for (var i = 0; i < keys.length; i++) {
							if (Object.prototype.hasOwnProperty.call(data, keys[i]) && data[keys[i]] !== '' && data[keys[i]] !== null && data[keys[i]] !== undefined) {
								return data[keys[i]];
							}
						}
						return null;
					}

					function loadSaldo() {
						if (btn) {
							btn.disabled = true;
							btn.textContent = '...';
						}
						setValue('Carregando...');
						postAjax('cepcerto_consultar_saldo', {
							_wpnonce: nonceSaldo
						}).then(function(resp) {
							if (btn) {
								btn.disabled = false;
								btn.textContent = 'Recarregar';
							}
							if (!resp || resp.success === false) {
								var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Indisponível';
								setValue(msg);
								return;
							}
							var data = resp.data || {};
							var raw = extractSaldo(data);
							var formatted = formatMoney(raw);
							setValue(formatted !== null ? formatted : String(raw !== null ? raw : 'OK'));
						}).catch(function(err) {
							if (btn) {
								btn.disabled = false;
								btn.textContent = 'Recarregar';
							}
							setValue('Erro');
						});
					}

					if (btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							loadSaldo();
						});
					}
					loadSaldo();
					window.loadSaldo = loadSaldo;
				})();
			</script>

			<div style="margin-top: 16px;">
				<?php
				switch ($tab) {
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

	private function render_orders_tab()
	{
		if (! function_exists('wc_get_orders')) {
			echo '<p>WooCommerce indisponível.</p>';
			return;
		}

		$perPage = 20;
		$page = isset($_GET['orders_page']) ? absint($_GET['orders_page']) : 1;
		if ($page < 1) {
			$page = 1;
		}
		$status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
		if ('all' === $status) {
			$status = '';
		}

		$args = array(
			'limit'    => $perPage,
			'page'     => $page,
			'paginate' => true,
		);
		if ('' !== $status) {
			$args['status'] = $status;
		}

		$result = wc_get_orders($args);
		$orders = array();
		$total = 0;
		$totalPages = 1;
		if (is_object($result) && isset($result->orders, $result->total, $result->max_num_pages)) {
			$orders = is_array($result->orders) ? $result->orders : array();
			$total = (int) $result->total;
			$totalPages = max(1, (int) $result->max_num_pages);
		} elseif (is_array($result)) {
			$orders = $result;
		}

		$baseUrl = add_query_arg(
			array(
				'page' => 'cepcerto',
				'tab'  => 'pedidos',
			),
			admin_url('admin.php')
		);

		$ajaxUrl = admin_url('admin-ajax.php');
		$nonceEtiqueta = wp_create_nonce('cepcerto_etiqueta');

		$statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : array();
	?>
		<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 10px 0 15px;">
			<input type="hidden" name="page" value="cepcerto" />
			<input type="hidden" name="tab" value="pedidos" />
			<label for="cepcerto_orders_status"><strong>Status</strong></label>
			<select name="status" id="cepcerto_orders_status">
				<option value="all" <?php selected('' === $status); ?>>Todos</option>
				<?php foreach ($statuses as $key => $label) : ?>
					<?php $cleanKey = is_string($key) ? str_replace('wc-', '', $key) : ''; ?>
					<option value="<?php echo esc_attr($cleanKey); ?>" <?php selected($cleanKey, $status); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="submit" class="button" value="Filtrar" />
		</form>

		<?php if (empty($orders)) : ?>
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
						<th style="width: 140px;">Ações</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($orders as $order) : ?>
						<?php
						if (! $order instanceof WC_Order) {
							continue;
						}
						$orderId = $order->get_id();
						$editUrl = function_exists('get_edit_post_link') ? get_edit_post_link($orderId, '') : '';
						$dateCreated = $order->get_date_created();
						$dateStr = $dateCreated ? $dateCreated->date_i18n('d/m/Y H:i') : '';
						$customer = trim($order->get_formatted_billing_full_name());
						if ('' === $customer) {
							$customer = (string) $order->get_billing_email();
						}
						$statusName = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($order->get_status()) : $order->get_status();
						$totalStr = function_exists('wc_price') ? wc_price($order->get_total(), array('currency' => $order->get_currency())) : (string) $order->get_total();
						$shippingMethod = (string) $order->get_shipping_method();
						if ('' === trim($shippingMethod)) {
							$shippingMethod = '-';
						}

						$etiqueta = $order->get_meta('_cepcerto_etiqueta', true);
						$hasEtiqueta = is_array($etiqueta) && ! empty($etiqueta['codigoObjeto']);
						$codigoObjeto = $hasEtiqueta ? (string) $etiqueta['codigoObjeto'] : '';
						$pdfUrl = $hasEtiqueta && ! empty($etiqueta['pdfUrlEtiqueta']) ? (string) $etiqueta['pdfUrlEtiqueta'] : '';
						$declaracaoUrl = $hasEtiqueta && ! empty($etiqueta['declaracaoUrl']) ? (string) $etiqueta['declaracaoUrl'] : '';
						?>
						<tr data-order-id="<?php echo esc_attr((string) $orderId); ?>">
							<td>
								<?php if (! empty($editUrl)) : ?>
									<a href="<?php echo esc_url($editUrl); ?>"><strong>#<?php echo esc_html((string) $order->get_order_number()); ?></strong></a>
								<?php else : ?>
									<strong>#<?php echo esc_html((string) $order->get_order_number()); ?></strong>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html($dateStr); ?></td>
							<td><?php echo esc_html($customer); ?></td>
							<td><?php echo wp_kses_post($totalStr); ?></td>
							<td><?php echo esc_html((string) $statusName); ?></td>
							<td><?php echo esc_html($shippingMethod); ?></td>
							<td class="cepcerto-col-etiqueta">
								<?php if ($hasEtiqueta) : ?>
									<code><?php echo esc_html($codigoObjeto); ?></code><br>
									<?php if ('' !== $pdfUrl) : ?>
										<a href="<?php echo esc_url($pdfUrl); ?>" target="_blank">Etiqueta PDF</a>
									<?php endif; ?>
									<?php if ('' !== $declaracaoUrl) : ?>
										<a href="<?php echo esc_url($declaracaoUrl); ?>" target="_blank" style="margin-left:6px;">Declaração</a>
									<?php endif; ?>
								<?php else : ?>
									<span style="color:#999;">—</span>
								<?php endif; ?>
							</td>
							<td class="cepcerto-col-acoes">
								<?php if ($hasEtiqueta) : ?>
									<button type="button" class="button cepcerto-btn-cancelar" data-order-id="<?php echo esc_attr((string) $orderId); ?>" data-cod-objeto="<?php echo esc_attr($codigoObjeto); ?>">Cancelar</button>
								<?php else : ?>
									<button type="button" class="button button-primary cepcerto-btn-gerar" data-order-id="<?php echo esc_attr((string) $orderId); ?>">Gerar Etiqueta</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ($totalPages > 1) : ?>
			<?php
			$prev = max(1, $page - 1);
			$next = min($totalPages, $page + 1);
			$prevUrl = add_query_arg(array('orders_page' => $prev, 'status' => ('' === $status ? 'all' : $status)), $baseUrl);
			$nextUrl = add_query_arg(array('orders_page' => $next, 'status' => ('' === $status ? 'all' : $status)), $baseUrl);
			?>
			<div style="display:flex; align-items:center; gap: 10px; margin-top: 12px;">
				<a class="button" href="<?php echo esc_url($prevUrl); ?>" <?php echo ($page <= 1) ? 'aria-disabled="true" style="pointer-events:none; opacity:.6;"' : ''; ?>>Anterior</a>
				<span>
					Página <strong><?php echo esc_html((string) $page); ?></strong> de <strong><?php echo esc_html((string) $totalPages); ?></strong>
					<?php if ($total > 0) : ?>
						(<?php echo esc_html((string) $total); ?> pedidos)
					<?php endif; ?>
				</span>
				<a class="button" href="<?php echo esc_url($nextUrl); ?>" <?php echo ($page >= $totalPages) ? 'aria-disabled="true" style="pointer-events:none; opacity:.6;"' : ''; ?>>Próxima</a>
			</div>
		<?php endif; ?>

		<script>
		(function() {
			var ajaxUrl = <?php echo wp_json_encode($ajaxUrl); ?>;
			var nonce   = <?php echo wp_json_encode($nonceEtiqueta); ?>;

			function postAjax(action, payload) {
				var body = new URLSearchParams();
				body.set('action', action);
				body.set('_wpnonce', nonce);
				Object.keys(payload || {}).forEach(function(k) { body.set(k, payload[k]); });
				return fetch(ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString(),
					credentials: 'same-origin'
				}).then(function(r) {
					return r.json().catch(function() { return { success: false, data: { message: 'Resposta inválida.' } }; });
				});
			}

			function updateRowEtiqueta(row, frete) {
				var etqCell = row.querySelector('.cepcerto-col-etiqueta');
				var actCell = row.querySelector('.cepcerto-col-acoes');
				var orderId = row.getAttribute('data-order-id');
				var codigo  = (frete && frete.codigoObjeto) ? frete.codigoObjeto : '';
				var pdfUrl  = (frete && frete.pdfUrlEtiqueta) ? frete.pdfUrlEtiqueta : '';
				var declUrl = (frete && frete.declaracaoUrl) ? frete.declaracaoUrl : '';

				var html = '<code>' + escHtml(codigo) + '</code><br>';
				if (pdfUrl)  html += '<a href="' + escAttr(pdfUrl) + '" target="_blank">Etiqueta PDF</a>';
				if (declUrl) html += '<a href="' + escAttr(declUrl) + '" target="_blank" style="margin-left:6px;">Declaração</a>';
				etqCell.innerHTML = html;

				actCell.innerHTML = '<button type="button" class="button cepcerto-btn-cancelar" data-order-id="' + escAttr(orderId) + '" data-cod-objeto="' + escAttr(codigo) + '">Cancelar</button>';
				bindCancelar(actCell.querySelector('.cepcerto-btn-cancelar'));
			}

			function clearRowEtiqueta(row) {
				var etqCell = row.querySelector('.cepcerto-col-etiqueta');
				var actCell = row.querySelector('.cepcerto-col-acoes');
				var orderId = row.getAttribute('data-order-id');
				etqCell.innerHTML = '<span style="color:#999;">—</span>';
				actCell.innerHTML = '<button type="button" class="button button-primary cepcerto-btn-gerar" data-order-id="' + escAttr(orderId) + '">Gerar Etiqueta</button>';
				bindGerar(actCell.querySelector('.cepcerto-btn-gerar'));
			}

			function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
			function escAttr(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

			function showSuccessNotice(message) {
				var notice = document.createElement('div');
				notice.className = 'notice notice-success is-dismissible';
				notice.style.margin = '20px 0';
				notice.innerHTML = '<p>' + escHtml(message) + '</p>';
				var first = document.querySelector('.wrap > h1, .wrap h2');
				if (first) {
					first.parentNode.insertBefore(notice, first.nextSibling);
				} else {
					var wrap = document.querySelector('.wrap');
					if (wrap) wrap.insertBefore(notice, wrap.firstChild);
				}
				setTimeout(function() {
					if (notice.parentNode) {
						notice.parentNode.removeChild(notice);
					}
				}, 5000);
			}

			function bindGerar(btn) {
				if (!btn) return;
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var orderId = btn.getAttribute('data-order-id');
					var row = btn.closest('tr');
					btn.disabled = true;
					btn.textContent = 'Gerando...';
					postAjax('cepcerto_gerar_etiqueta', { order_id: orderId }).then(function(resp) {
						if (resp && resp.success && resp.data && resp.data.frete) {
							updateRowEtiqueta(row, resp.data.frete);
							if (resp.data.reload_saldo && typeof window.loadSaldo === 'function') {
								window.loadSaldo();
							}
							var msg = (resp.data.message) ? resp.data.message : 'Etiqueta gerada com sucesso.';
							showSuccessNotice(msg);
						} else {
							btn.disabled = false;
							btn.textContent = 'Gerar Etiqueta';
							var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao gerar etiqueta.';
							alert(msg);
						}
					}).catch(function() {
						btn.disabled = false;
						btn.textContent = 'Gerar Etiqueta';
						alert('Erro de conexão.');
					});
				});
			}

			function bindCancelar(btn) {
				if (!btn) return;
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					if (!confirm('Tem certeza que deseja cancelar esta etiqueta?')) return;
					var orderId = btn.getAttribute('data-order-id');
					var row = btn.closest('tr');
					btn.disabled = true;
					btn.textContent = 'Cancelando...';
					postAjax('cepcerto_cancelar_etiqueta', { order_id: orderId }).then(function(resp) {
						if (resp && resp.success) {
							clearRowEtiqueta(row);
							if (resp.data.reload_saldo && typeof window.loadSaldo === 'function') {
								window.loadSaldo();
							}
							var msg = (resp.data.message) ? resp.data.message : 'Etiqueta cancelada com sucesso.';
							showSuccessNotice(msg);
						} else {
							btn.disabled = false;
							btn.textContent = 'Cancelar';
							var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao cancelar etiqueta.';
							alert(msg);
						}
					}).catch(function() {
						btn.disabled = false;
						btn.textContent = 'Cancelar';
						alert('Erro de conexão.');
					});
				});
			}

			document.querySelectorAll('.cepcerto-btn-gerar').forEach(bindGerar);
			document.querySelectorAll('.cepcerto-btn-cancelar').forEach(bindCancelar);
		})();
		</script>
	<?php
	}

	private function render_sender_tab()
	{
		$origin = get_option('cepcerto_origin_cep', '');
		$nomeRemetente = get_option('cepcerto_nome_remetente', '');
		$cpfCnpjRemetente = get_option('cepcerto_cpf_cnpj_remetente', '');
		$whatsappRemetente = get_option('cepcerto_whatsapp_remetente', '');
		$emailRemetente = get_option('cepcerto_email_remetente', '');
		$logradouroRemetente = get_option('cepcerto_logradouro_remetente', '');
		$bairroRemetente = get_option('cepcerto_bairro_remetente', '');
		$numeroEnderecoRemetente = get_option('cepcerto_numero_endereco_remetente', '');
		$complementoRemetente = get_option('cepcerto_complemento_remetente', '');
		$ajaxUrl = admin_url('admin-ajax.php');
		$nonceCep = wp_create_nonce('cepcerto_consultar_cep_origem');
	?>
		<form method="post" action="options.php">
			<?php settings_fields('cepcerto_settings_sender'); ?>
			<?php settings_errors('cepcerto_settings_sender'); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="cepcerto_nome_remetente">Nome completo (obrigatório)</label></th>
						<td>
							<input name="cepcerto_nome_remetente" id="cepcerto_nome_remetente" type="text" class="regular-text" value="<?php echo esc_attr($nomeRemetente); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_cpf_cnpj_remetente">CPF ou CNPJ (obrigatório)</label></th>
						<td>
							<input name="cepcerto_cpf_cnpj_remetente" id="cepcerto_cpf_cnpj_remetente" type="text" class="regular-text" value="<?php echo esc_attr($cpfCnpjRemetente); ?>" required inputmode="numeric" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_whatsapp_remetente">WhatsApp (obrigatório)</label></th>
						<td>
							<input name="cepcerto_whatsapp_remetente" id="cepcerto_whatsapp_remetente" type="text" class="regular-text" value="<?php echo esc_attr($whatsappRemetente); ?>" required inputmode="numeric" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_email_remetente">E-mail (obrigatório)</label></th>
						<td>
							<input name="cepcerto_email_remetente" id="cepcerto_email_remetente" type="email" class="regular-text" value="<?php echo esc_attr($emailRemetente); ?>" required autocomplete="email" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_logradouro_remetente">Logradouro (obrigatório)</label></th>
						<td>
							<input name="cepcerto_logradouro_remetente" id="cepcerto_logradouro_remetente" type="text" class="regular-text" value="<?php echo esc_attr($logradouroRemetente); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_bairro_remetente">Bairro (obrigatório)</label></th>
						<td>
							<input name="cepcerto_bairro_remetente" id="cepcerto_bairro_remetente" type="text" class="regular-text" value="<?php echo esc_attr($bairroRemetente); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_numero_endereco_remetente">Número (obrigatório)</label></th>
						<td>
							<input name="cepcerto_numero_endereco_remetente" id="cepcerto_numero_endereco_remetente" type="text" class="regular-text" value="<?php echo esc_attr($numeroEnderecoRemetente); ?>" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_complemento_remetente">Complemento (opcional)</label></th>
						<td>
							<input name="cepcerto_complemento_remetente" id="cepcerto_complemento_remetente" type="text" class="regular-text" value="<?php echo esc_attr($complementoRemetente); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_origin_cep">CEP de origem</label></th>
						<td>
							<input name="cepcerto_origin_cep" id="cepcerto_origin_cep" type="text" class="regular-text" value="<?php echo esc_attr($origin); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<script>
			(function() {
				var ajaxUrl = <?php echo wp_json_encode($ajaxUrl); ?>;
				var nonceCep = <?php echo wp_json_encode($nonceCep); ?>;
				var form = document.currentScript && document.currentScript.previousElementSibling;
				var cepInput = document.getElementById('cepcerto_origin_cep');
				var logradouroInput = document.getElementById('cepcerto_logradouro_remetente');
				var bairroInput = document.getElementById('cepcerto_bairro_remetente');
				var cpfCnpjInput = document.getElementById('cepcerto_cpf_cnpj_remetente');
				var whatsappInput = document.getElementById('cepcerto_whatsapp_remetente');
				var emailInput = document.getElementById('cepcerto_email_remetente');

				function digitsOnly(v) {
					return String(v || '').replace(/\D+/g, '');
				}

				function formatCpfCnpjDigits(d) {
					if (d.length <= 11) {
						d = d.slice(0, 11);
						return d
							.replace(/(\d{3})(\d)/, '$1.$2')
							.replace(/(\d{3})(\d)/, '$1.$2')
							.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
					}
					d = d.slice(0, 14);
					return d
						.replace(/(\d{2})(\d)/, '$1.$2')
						.replace(/(\d{3})(\d)/, '$1.$2')
						.replace(/(\d{3})(\d)/, '$1/$2')
						.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
				}

				function formatWhatsappDigits(d) {
					d = d.slice(0, 11);
					if (d.length <= 2) return d;
					if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
					if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
					return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
				}

				function applyMask(el, formatter) {
					if (!el) return;
					var d = digitsOnly(el.value);
					el.value = formatter(d);
				}

				function isValidCpfCnpj(d) {
					return d.length === 11 || d.length === 14;
				}

				function isValidWhatsapp(d) {
					return d.length === 10 || d.length === 11;
				}

				function postAjax(action, payload) {
					var body = new URLSearchParams();
					body.set('action', action);
					Object.keys(payload || {}).forEach(function(k) {
						body.set(k, payload[k]);
					});
					return fetch(ajaxUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
						},
						body: body.toString(),
						credentials: 'same-origin'
					}).then(function(r) {
						return r.json().catch(function() {
							return {
								success: false,
								data: {
									message: 'Resposta inválida.'
								}
							};
						});
					});
				}

				function fillIfEmpty(el, value) {
					if (!el) return;
					if (el.value && el.value.trim() !== '') return;
					el.value = value || '';
				}

				function consultarCep() {
					if (!cepInput) return;
					var cep = digitsOnly(cepInput.value);
					if (cep.length !== 8) return;

					postAjax('cepcerto_consultar_cep_origem', {
						_wpnonce: nonceCep,
						cep: cep
					}).then(function(resp) {
						if (!resp || resp.success === false) {
							return;
						}
						var data = resp.data || {};
						fillIfEmpty(logradouroInput, data.logradouro);
						fillIfEmpty(bairroInput, data.bairro);
					});
				}

				if (cepInput) {
					cepInput.addEventListener('blur', consultarCep);
					cepInput.addEventListener('change', consultarCep);
				}

				if (cpfCnpjInput) {
					cpfCnpjInput.addEventListener('input', function() {
						applyMask(cpfCnpjInput, formatCpfCnpjDigits);
					});
					cpfCnpjInput.addEventListener('blur', function() {
						applyMask(cpfCnpjInput, formatCpfCnpjDigits);
					});
					applyMask(cpfCnpjInput, formatCpfCnpjDigits);
				}

				if (whatsappInput) {
					whatsappInput.addEventListener('input', function() {
						applyMask(whatsappInput, formatWhatsappDigits);
					});
					whatsappInput.addEventListener('blur', function() {
						applyMask(whatsappInput, formatWhatsappDigits);
					});
					applyMask(whatsappInput, formatWhatsappDigits);
				}

				function resolveForm() {
					if (form && form.tagName && form.tagName.toLowerCase() === 'form') return form;
					if (cpfCnpjInput && cpfCnpjInput.form) return cpfCnpjInput.form;
					return null;
				}

				var realForm = resolveForm();
				if (realForm) {
					realForm.addEventListener('submit', function(e) {
						var cpfCnpjDigits = cpfCnpjInput ? digitsOnly(cpfCnpjInput.value) : '';
						var whatsappDigits = whatsappInput ? digitsOnly(whatsappInput.value) : '';
						var emailVal = emailInput ? String(emailInput.value || '').trim() : '';

						if (cpfCnpjInput && (!cpfCnpjDigits || !isValidCpfCnpj(cpfCnpjDigits))) {
							e.preventDefault();
							alert('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
							cpfCnpjInput.focus();
							return;
						}
						if (whatsappInput && (!whatsappDigits || !isValidWhatsapp(whatsappDigits))) {
							e.preventDefault();
							alert('Informe um WhatsApp com DDD (10 ou 11 dígitos).');
							whatsappInput.focus();
							return;
						}
						if (emailInput && (!emailVal || !emailInput.checkValidity())) {
							e.preventDefault();
							alert('Informe um e-mail válido.');
							emailInput.focus();
							return;
						}
					}, false);
				}
			})();
		</script>
	<?php
	}

	public function ajax_consultar_cep_origem()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Sem permissão.'), 403);
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (empty($nonce) || ! wp_verify_nonce($nonce, 'cepcerto_consultar_cep_origem')) {
			wp_send_json_error(array('message' => 'Nonce inválido.'), 400);
		}

		$cep = isset($_POST['cep']) ? sanitize_text_field(wp_unslash($_POST['cep'])) : '';
		$api = new CepCerto_Api();
		$result = $api->consultar_cep($cep);
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message(), 'error_code' => $result->get_error_code()), 400);
		}

		wp_send_json_success(
			array(
				'cep' => isset($result['cep']) ? (string) $result['cep'] : '',
				'logradouro' => isset($result['logradouro']) ? (string) $result['logradouro'] : '',
				'bairro' => isset($result['bairro']) ? (string) $result['bairro'] : '',
				'localidade' => isset($result['localidade']) ? (string) $result['localidade'] : '',
				'uf' => isset($result['uf']) ? (string) $result['uf'] : '',
			),
			200
		);
	}

	private function render_settings_tab()
	{
		$token  = get_option('cepcerto_token_cliente_postagem', '');
		$debug  = get_option('cepcerto_debug', 'no');

		$defaultWidth  = get_option('cepcerto_default_width', 10);
		$defaultHeight = get_option('cepcerto_default_height', 10);
		$defaultLength = get_option('cepcerto_default_length', 10);
		$defaultWeight = get_option('cepcerto_default_weight', 1);
		$minOrderValue = get_option('cepcerto_min_order_value', 50);

	?>
		<form method="post" action="options.php">
			<?php settings_fields('cepcerto_settings'); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="cepcerto_token_cliente_postagem">Token cliente postagem</label></th>
						<td>
							<input name="cepcerto_token_cliente_postagem" id="cepcerto_token_cliente_postagem" type="text" class="regular-text" value="<?php echo esc_attr($token); ?>" readonly />
						</td>
					</tr>
					<tr>
						<th scope="row">Debug</th>
						<td>
							<label>
								<input name="cepcerto_debug" type="checkbox" value="yes" <?php checked($debug, 'yes'); ?> />
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
						<td><input name="cepcerto_default_width" id="cepcerto_default_width" type="number" step="0.01" value="<?php echo esc_attr($defaultWidth); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_default_height">Altura (cm)</label></th>
						<td><input name="cepcerto_default_height" id="cepcerto_default_height" type="number" step="0.01" value="<?php echo esc_attr($defaultHeight); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_default_length">Comprimento (cm)</label></th>
						<td><input name="cepcerto_default_length" id="cepcerto_default_length" type="number" step="0.01" value="<?php echo esc_attr($defaultLength); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_default_weight">Peso (kg)</label></th>
						<td><input name="cepcerto_default_weight" id="cepcerto_default_weight" type="number" step="0.01" value="<?php echo esc_attr($defaultWeight); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cepcerto_min_order_value">Valor mínimo da encomenda (R$)</label></th>
						<td>
							<input name="cepcerto_min_order_value" id="cepcerto_min_order_value" type="number" step="0.01" min="50" max="35000" value="<?php echo esc_attr($minOrderValue); ?>" />
							<p class="description">Valor mínimo para cotação (entre R$ 50,00 e R$ 35.000,00). Se o carrinhover for menor, será usado este valor.</p>
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
							$displayLocations = get_option('cepcerto_display_locations', array('product', 'checkout'));
							if (! is_array($displayLocations)) {
								$displayLocations = array('product', 'checkout');
							}
							?>
							<input type="hidden" name="cepcerto_display_locations" value="" />
							<label style="display:block; margin-bottom: 6px;">
								<input type="checkbox" name="cepcerto_display_locations[]" value="product" <?php checked(in_array('product', $displayLocations, true)); ?> />
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

			<?php submit_button(); ?>
		</form>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="cepcerto_reset_settings" />
			<?php wp_nonce_field('cepcerto_reset_settings'); ?>
			<?php submit_button('Resetar configurações e reiniciar', 'delete', 'submit', false, array('onclick' => "return confirm('Tem certeza? Isso irá remover as configurações do CepCerto e reiniciar o processo.');")); ?>
		</form>


	<?php
	}

	private function render_saldo_tab()
	{
		$ajaxUrl       = admin_url('admin-ajax.php');
		$nonceSaldo    = wp_create_nonce('cepcerto_consultar_saldo');
		$nonceCredito  = wp_create_nonce('cepcerto_adicionar_credito');
	?>
		<div id="cepcerto-saldo-notices"></div>

		<div class="card" style="max-width:700px;margin-top:20px;padding:20px;">
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

		<script>
			(function() {
				var ajaxUrl = <?php echo wp_json_encode($ajaxUrl); ?>;
				var nonceSaldo = <?php echo wp_json_encode($nonceSaldo); ?>;
				var nonceCredito = <?php echo wp_json_encode($nonceCredito); ?>;

				var pollingTimer = null;
				var pollingAttempts = 0;
				var pollingInFlight = false;
				var pollingInitialSaldoNumber = null;
				var pollingInitialSaldoText = null;
				var pollingMaxAttempts = 500;
				var pollingIntervalMs = 2000;

				function showNotice(type, message) {
					var container = document.getElementById('cepcerto-saldo-notices');
					if (!container) return;
					container.innerHTML = '';
					var notice = document.createElement('div');
					notice.className = 'notice ' + (type || 'notice-error') + ' is-dismissible';
					var p = document.createElement('p');
					p.textContent = message || '';
					notice.appendChild(p);
					container.appendChild(notice);
					window.scrollTo({
						top: 0,
						behavior: 'smooth'
					});
				}

				function postAjax(action, payload) {
					var body = new URLSearchParams();
					body.set('action', action);
					Object.keys(payload || {}).forEach(function(k) {
						body.set(k, payload[k]);
					});
					return fetch(ajaxUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
						},
						body: body.toString(),
						credentials: 'same-origin'
					}).then(function(r) {
						return r.json().catch(function() {
							return {
								success: false,
								data: {
									message: 'Resposta inválida.'
								}
							};
						});
					});
				}

				function parsePtBrMoneyToNumber(text) {
					if (!text) return null;
					var s = String(text);
					s = s.replace(/\s+/g, ' ');
					s = s.replace(/[^0-9,.-]/g, '');
					if (s === '') return null;
					s = s.replace(/\./g, '').replace(',', '.');
					var n = Number(s);
					return isFinite(n) ? n : null;
				}

				function formatMoney(val) {
					var n = Number(val);
					if (!isFinite(n)) return null;
					try {
						return n.toLocaleString('pt-BR', {
							style: 'currency',
							currency: 'BRL'
						});
					} catch (e) {
						return 'R$ ' + n.toFixed(2);
					}
				}

				function extractSaldo(data) {
					if (!data || typeof data !== 'object') return null;
					var keys = ['saldo_atual', 'saldoAtual', 'saldo', 'valor', 'credito'];
					for (var i = 0; i < keys.length; i++) {
						if (Object.prototype.hasOwnProperty.call(data, keys[i]) && data[keys[i]] !== '' && data[keys[i]] !== null && data[keys[i]] !== undefined) {
							return data[keys[i]];
						}
					}
					return null;
				}

				function getHeaderSaldoText() {
					var el = document.getElementById('cepcerto-header-saldo-value');
					return el ? (el.textContent || '').trim() : null;
				}

				function setHeaderSaldoText(text) {
					var el = document.getElementById('cepcerto-header-saldo-value');
					if (!el) return;
					el.textContent = text;
				}

				function stopSaldoPolling() {
					if (pollingTimer) {
						clearInterval(pollingTimer);
						pollingTimer = null;
					}
					pollingAttempts = 0;
					pollingInFlight = false;
					pollingInitialSaldoNumber = null;
					pollingInitialSaldoText = null;
				}

				function showPaymentConfirmedMessage(newSaldoText) {
					var result = document.getElementById('cepcerto-credito-resultado');
					if (!result) return;

					var table = document.getElementById('cepcerto-credito-tabela');
					if (table) table.style.display = 'none';
					var pixSection = document.getElementById('cepcerto-pix-section');
					if (pixSection) pixSection.style.display = 'none';

					var existing = document.getElementById('cepcerto-credito-payment-confirmed');
					if (!existing) {
						existing = document.createElement('div');
						existing.id = 'cepcerto-credito-payment-confirmed';
						existing.style.marginTop = '15px';
						existing.style.padding = '12px 14px';
						existing.style.border = '1px solid #c3e6cb';
						existing.style.background = '#d4edda';
						existing.style.borderRadius = '6px';
						existing.style.color = '#155724';
						result.appendChild(existing);
					}
					existing.textContent = 'Pagamento identificado! Seu saldo foi atualizado' + (newSaldoText ? (': ' + newSaldoText) : '') + '.';
				}

				function consultarSaldoFromApi() {
					return postAjax('cepcerto_consultar_saldo', {
						_wpnonce: nonceSaldo
					}).then(function(resp) {
						if (!resp || resp.success === false) {
							var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao consultar saldo.';
							throw new Error(msg);
						}
						var data = resp.data || {};
						var raw = extractSaldo(data);
						var rawNum = parsePtBrMoneyToNumber(raw);
						if (rawNum === null) {
							rawNum = parsePtBrMoneyToNumber(formatMoney(raw));
						}
						var formatted = formatMoney(rawNum !== null ? rawNum : raw);
						return {
							number: rawNum,
							text: formatted !== null ? formatted : (raw !== null && raw !== undefined ? String(raw) : null)
						};
					});
				}

				function startSaldoPolling() {
					stopSaldoPolling();

					pollingInFlight = true;
					consultarSaldoFromApi().then(function(saldo) {
						pollingInitialSaldoText = saldo ? saldo.text : null;
						pollingInitialSaldoNumber = (saldo && saldo.number !== null && isFinite(saldo.number)) ? saldo.number : 0;
						if (saldo && saldo.text) {
							setHeaderSaldoText(saldo.text);
						}
					}).catch(function() {
						pollingInitialSaldoText = getHeaderSaldoText();
						var headerNum = parsePtBrMoneyToNumber(pollingInitialSaldoText);
						pollingInitialSaldoNumber = (headerNum !== null && isFinite(headerNum)) ? headerNum : 0;
					}).finally(function() {
						pollingInFlight = false;
						pollingAttempts = 0;
						pollingTimer = setInterval(function() {
							if (pollingInFlight) return;
							pollingInFlight = true;
							pollingAttempts++;

							consultarSaldoFromApi().then(function(saldo) {
								if (saldo && saldo.text) {
									setHeaderSaldoText(saldo.text);
								}

								var currentText = saldo ? saldo.text : null;
								var currentNumber = saldo ? saldo.number : null;
								var changed = false;
								if (currentNumber !== null && isFinite(currentNumber)) {
									changed = currentNumber > (pollingInitialSaldoNumber + 0.000001);
								}

								if (changed) {
									stopSaldoPolling();
									showPaymentConfirmedMessage(currentText);
									showNotice('notice-success', 'Pagamento identificado e saldo atualizado!');
									return;
								}

								if (pollingAttempts >= pollingMaxAttempts) {
									stopSaldoPolling();
									showNotice('notice-error', 'Tempo esgotado. Cobrança PIX expirada.');
									return;
								}
							}).catch(function() {
								if (pollingAttempts >= pollingMaxAttempts) {
									stopSaldoPolling();
									showNotice('notice-error', 'Tempo esgotado. Cobrança PIX expirada.');
									return;
								}
							}).finally(function() {
								pollingInFlight = false;
							});
						}, pollingIntervalMs);
					});
				}

				function buildInfoTable(tableId, rows) {
					var tbody = document.querySelector('#' + tableId + ' tbody');
					if (!tbody) return;
					tbody.innerHTML = '';
					rows.forEach(function(row) {
						var tr = document.createElement('tr');
						var th = document.createElement('th');
						th.setAttribute('scope', 'row');
						th.textContent = row.label;
						var td = document.createElement('td');
						td.innerHTML = row.value;
						tr.appendChild(th);
						tr.appendChild(td);
						tbody.appendChild(tr);
					});
				}

				var btnCredito = document.getElementById('cepcerto-btn-credito');
				if (btnCredito) {
					btnCredito.addEventListener('click', function() {
						var valorInput = document.getElementById('cepcerto_valor_credito');
						var valor = valorInput ? valorInput.value.trim() : '';
						if (!valor || parseFloat(valor) <= 0) {
							showNotice('notice-error', 'Informe um valor válido para o crédito.');
							return;
						}

						btnCredito.disabled = true;
						btnCredito.textContent = 'Gerando...';
						postAjax('cepcerto_adicionar_credito', {
							_wpnonce: nonceCredito,
							valor_credito: valor
						}).then(function(resp) {
							btnCredito.disabled = false;
							btnCredito.textContent = 'Gerar cobrança PIX';

							if (!resp || resp.success === false) {
								var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao gerar crédito.';
								showNotice('notice-error', msg);
								return;
							}

							var data = resp.data || {};
							var rows = [];
							if (data.nome_cliente) rows.push({
								label: 'Cliente',
								value: '<strong>' + escHtml(data.nome_cliente) + '</strong>'
							});
							if (data.email) rows.push({
								label: 'Email',
								value: escHtml(data.email)
							});
							if (data.telefone) rows.push({
								label: 'Telefone',
								value: escHtml(data.telefone)
							});
							if (data.valor) rows.push({
								label: 'Valor',
								value: '<strong style="font-size:1.3em;color:#2e7d32;">R$ ' + escHtml(data.valor) + '</strong>'
							});
							if (data.data_requisicao) rows.push({
								label: 'Data',
								value: escHtml(data.data_requisicao)
							});

							buildInfoTable('cepcerto-credito-tabela', rows);

							var qrcodeContainer = document.getElementById('cepcerto-qrcode-container');
							var pixSection = document.getElementById('cepcerto-pix-section');
							if (qrcodeContainer) qrcodeContainer.innerHTML = '';

							if (data.qrcode_img) {
								var img = document.createElement('img');
								img.src = data.qrcode_img;
								img.alt = 'QR Code PIX';
								img.style.maxWidth = '280px';
								img.style.height = 'auto';
								img.style.border = '1px solid #ddd';
								img.style.borderRadius = '8px';
								img.style.padding = '10px';
								img.style.background = '#fff';
								if (qrcodeContainer) qrcodeContainer.appendChild(img);
							}

							var copiaColaInput = document.getElementById('cepcerto_copia_cola');
							if (copiaColaInput && data.copia_cola) {
								copiaColaInput.value = data.copia_cola;
							}

							if (pixSection) {
								pixSection.style.display = (data.qrcode_img || data.copia_cola) ? 'block' : 'none';
							}

							document.getElementById('cepcerto-credito-resultado').style.display = 'block';
							showNotice('notice-success', 'Cobrança PIX gerada com sucesso!');
							startSaldoPolling();
						}).catch(function(err) {
							btnCredito.disabled = false;
							btnCredito.textContent = 'Gerar cobrança PIX';
							showNotice('notice-error', 'Erro: ' + err.message);
						});
					});
				}

				var btnCopiar = document.getElementById('cepcerto-btn-copiar');
				if (btnCopiar) {
					btnCopiar.addEventListener('click', function() {
						var input = document.getElementById('cepcerto_copia_cola');
						if (input && input.value) {
							if (navigator.clipboard && navigator.clipboard.writeText) {
								navigator.clipboard.writeText(input.value).then(function() {
									btnCopiar.textContent = 'Copiado!';
									setTimeout(function() {
										btnCopiar.textContent = 'Copiar';
									}, 2000);
								});
							} else {
								input.select();
								document.execCommand('copy');
								btnCopiar.textContent = 'Copiado!';
								setTimeout(function() {
									btnCopiar.textContent = 'Copiar';
								}, 2000);
							}
						}
					});
				}

				function escHtml(str) {
					var div = document.createElement('div');
					div.appendChild(document.createTextNode(str || ''));
					return div.innerHTML;
				}

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

	private function render_logs_tab()
	{
		$enabled = get_option('cepcerto_debug', 'no');
		$file = class_exists('CepCerto_Logger') ? CepCerto_Logger::get_latest_log_file() : false;
		$downloadUrl = wp_nonce_url(admin_url('admin-post.php?action=cepcerto_download_log'), 'cepcerto_download_log');

		$minutes = isset($_GET['minutes']) ? absint(wp_unslash($_GET['minutes'])) : 10;
		if ($minutes <= 0) {
			$minutes = 10;
		}
		$minutes = min($minutes, 10080);

		$view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'table';
		if (! in_array($view, array('table', 'raw'), true)) {
			$view = 'table';
		}

		$baseUrl = add_query_arg(array('page' => 'cepcerto', 'tab' => 'logs'), admin_url('admin.php'));
		$tableUrl = add_query_arg(array('minutes' => $minutes, 'view' => 'table'), $baseUrl);
		$rawUrl = add_query_arg(array('minutes' => $minutes, 'view' => 'raw'), $baseUrl);

		$lines = array();
		if ($file && file_exists($file)) {
			$lines = $this->tail_file_lines($file, 2000);
		}

		$cutoffTs = time() - ($minutes * MINUTE_IN_SECONDS);
		$rows = array();
		$rawContent = '';
		foreach ($lines as $line) {
			$parsed = $this->parse_log_line($line);
			if ($parsed && isset($parsed['ts']) && is_int($parsed['ts']) && $parsed['ts'] < $cutoffTs) {
				continue;
			}
			if ($parsed) {
				$rows[] = $parsed;
			} else {
				$rows[] = array(
					'ts' => null,
					'ts_str' => '',
					'level' => 'RAW',
					'endpoint' => '',
					'message' => (string) $line,
					'context' => '',
				);
			}
			$rawContent .= $line . "\n";
		}
	?>
		<p>
			<strong>Status do log:</strong> <?php echo ($enabled === 'yes') ? 'Ativo' : 'Inativo'; ?>
		</p>
		<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 10px 0 15px;">
			<input type="hidden" name="page" value="cepcerto" />
			<input type="hidden" name="tab" value="logs" />
			<input type="hidden" name="view" value="<?php echo esc_attr($view); ?>" />
			<label for="cepcerto_logs_minutes"><strong>Exibir últimos</strong></label>
			<input type="number" min="1" max="10080" step="1" id="cepcerto_logs_minutes" name="minutes" value="<?php echo esc_attr((string) $minutes); ?>" style="width: 90px;" />
			<span>minutos</span>
			<input type="submit" class="button" value="Aplicar" />
			<span style="margin-left:12px;">
				<a class="button <?php echo ('table' === $view) ? 'button-primary' : ''; ?>" href="<?php echo esc_url($tableUrl); ?>">Tabela</a>
				<a class="button <?php echo ('raw' === $view) ? 'button-primary' : ''; ?>" href="<?php echo esc_url($rawUrl); ?>">Raw</a>
			</span>
		</form>
		<p>
			<a class="button button-primary" href="<?php echo esc_url($downloadUrl); ?>">Baixar log</a>
		</p>
		<?php if (empty($rows)) : ?>
			<p>Nenhum log encontrado para o período selecionado.</p>
		<?php else : ?>
			<?php if ('raw' === $view) : ?>
				<textarea style="width:100%;min-height:520px;font-family:monospace;" readonly><?php echo esc_textarea(rtrim($rawContent)); ?></textarea>
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
							<?php foreach (array_reverse($rows) as $row) : ?>
								<?php
								$level = isset($row['level']) ? strtoupper((string) $row['level']) : '';
								$style = '';
								if (in_array($level, array('ERROR', 'CRITICAL'), true)) {
									$style = 'background:#fbeaea;';
								} elseif ('WARNING' === $level) {
									$style = 'background:#fff8e5;';
								}
								?>
								<tr style="<?php echo esc_attr($style); ?>">
									<td><code><?php echo esc_html((string) ($row['ts_str'] ?? '')); ?></code></td>
									<td><strong><?php echo esc_html($level); ?></strong></td>
									<td style="white-space: pre-wrap;"><code><?php echo esc_html((string) ($row['endpoint'] ?? ($row['message'] ?? ''))); ?></code></td>
									<td style="white-space: pre-wrap;"><code><?php echo esc_html((string) ($row['context'] ?? '')); ?></code></td>
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

	public function render_saldo_page()
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		wp_safe_redirect(add_query_arg(array('page' => 'cepcerto', 'tab' => 'saldo'), admin_url('admin.php')));
		exit;
	}

	public function render_logs_page()
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		wp_safe_redirect(add_query_arg(array('page' => 'cepcerto', 'tab' => 'logs'), admin_url('admin.php')));
		exit;
	}

	public function ajax_consultar_saldo()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Sem permissão.'), 403);
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (empty($nonce) || ! wp_verify_nonce($nonce, 'cepcerto_consultar_saldo')) {
			wp_send_json_error(array('message' => 'Nonce inválido.'), 400);
		}

		$token = get_option('cepcerto_token_cliente_postagem', '');
		if (empty($token)) {
			wp_send_json_error(array('message' => 'Token de cliente não configurado.'), 400);
		}

		$api    = new CepCerto_Api();
		$result = $api->saldo($token);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		wp_send_json_success($result, 200);
	}

	public function ajax_adicionar_credito()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Sem permissão.'), 403);
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (empty($nonce) || ! wp_verify_nonce($nonce, 'cepcerto_adicionar_credito')) {
			wp_send_json_error(array('message' => 'Nonce inválido.'), 400);
		}

		$valor = isset($_POST['valor_credito']) ? sanitize_text_field(wp_unslash($_POST['valor_credito'])) : '';
		if (empty($valor)) {
			wp_send_json_error(array('message' => 'Informe o valor do crédito.'), 400);
		}

		$token = get_option('cepcerto_token_cliente_postagem', '');
		if (empty($token)) {
			wp_send_json_error(array('message' => 'Token de cliente não configurado.'), 400);
		}

		$api    = new CepCerto_Api();
		$result = $api->credito($token, $valor);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		wp_send_json_success($result, 200);
	}

	public function ajax_gerar_etiqueta()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Sem permissão.'), 403);
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (empty($nonce) || ! wp_verify_nonce($nonce, 'cepcerto_etiqueta')) {
			wp_send_json_error(array('message' => 'Nonce inválido.'), 400);
		}

		$orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		if ($orderId < 1) {
			wp_send_json_error(array('message' => 'Pedido inválido.'), 400);
		}

		$order = wc_get_order($orderId);
		if (! $order instanceof WC_Order) {
			wp_send_json_error(array('message' => 'Pedido não encontrado.'), 404);
		}

		$existing = $order->get_meta('_cepcerto_etiqueta', true);
		if (is_array($existing) && ! empty($existing['codigoObjeto'])) {
			wp_send_json_error(array('message' => 'Etiqueta já gerada para este pedido.'), 400);
		}

		$token = get_option('cepcerto_token_cliente_postagem', '');
		if (empty($token)) {
			wp_send_json_error(array('message' => 'Token de cliente não configurado.'), 400);
		}

		$tipoEntrega = $this->resolve_tipo_entrega($order);

		$cepRemetente = preg_replace('/\D+/', '', (string) get_option('cepcerto_origin_cep', ''));
		$shippingPostcode = (string) $order->get_shipping_postcode();
		$billingPostcode  = (string) $order->get_billing_postcode();
		$cepDestinatario  = preg_replace('/\D+/', '', '' !== $shippingPostcode ? $shippingPostcode : $billingPostcode);

		if ('' === $cepRemetente || '' === $cepDestinatario) {
			wp_send_json_error(array('message' => 'CEP de origem ou destino não configurado.'), 400);
		}

		$defaultWeight = $this->etiqueta_to_float(get_option('cepcerto_default_weight', 1));
		$defaultWidth  = $this->etiqueta_to_float(get_option('cepcerto_default_width', 10));
		$defaultHeight = $this->etiqueta_to_float(get_option('cepcerto_default_height', 10));
		$defaultLength = $this->etiqueta_to_float(get_option('cepcerto_default_length', 10));

		$defaultWeightKg = $this->etiqueta_convert_weight_to_kg($defaultWeight);

		$totalWeight = 0.0;
		$totalQuantity = 0;
		$produtos = array();

		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			$qty = max(1, (int) $item->get_quantity());

			if ($product instanceof WC_Product && ! $product->is_virtual()) {
				$totalQuantity += $qty;
				$productWeight = $this->etiqueta_to_float($product->get_weight());
				$productWeightKg = $productWeight > 0 ? $this->etiqueta_convert_weight_to_kg($productWeight) : $defaultWeightKg;
				$totalWeight += $productWeightKg * $qty;
			} elseif (! ($product instanceof WC_Product)) {
				$totalQuantity += $qty;
				$totalWeight += $defaultWeightKg * $qty;
			}

			$itemTotal = (float) $item->get_total();
			$unitPrice = $qty > 0 ? round($itemTotal / $qty, 2) : $itemTotal;

			$produtos[] = array(
				'descricao'  => mb_substr((string) $item->get_name(), 0, 80),
				'valor'      => number_format($unitPrice, 2, ',', ''),
				'quantidade' => (string) $qty,
			);
		}

		if ($totalWeight <= 0 && $totalQuantity > 0) {
			$totalWeight = $defaultWeightKg * $totalQuantity;
		}

		$finalWidth  = $this->etiqueta_convert_dimension_to_cm($defaultWidth);
		$finalHeight = $this->etiqueta_convert_dimension_to_cm($defaultHeight);
		$finalLength = $this->etiqueta_convert_dimension_to_cm($defaultLength);

		if ($totalWeight <= 0 || $finalWidth <= 0 || $finalHeight <= 0 || $finalLength <= 0) {
			wp_send_json_error(array('message' => 'Dimensões ou peso padrão inválidos. Verifique as configurações.'), 400);
		}

		$minOrderValue = (float) get_option('cepcerto_min_order_value', 50);
		$orderTotal = (float) $order->get_total();
		$valorEncomenda = max($minOrderValue, min(35000, $orderTotal));

		$shippingFirstName = (string) $order->get_shipping_first_name();
		$shippingLastName  = (string) $order->get_shipping_last_name();
		$nomeDestinatario  = trim($shippingFirstName . ' ' . $shippingLastName);
		if ('' === $nomeDestinatario) {
			$nomeDestinatario = trim($order->get_formatted_billing_full_name());
		}

		$billingPhone = preg_replace('/\D+/', '', (string) $order->get_billing_phone());
		if ('' === $billingPhone) {
			$billingPhone = '11975532552'; //Chumbado
		}
		$billingEmail = (string) $order->get_billing_email();

		$cpfCnpjDest = '';
		$metaKeys = array('_billing_cpf', '_billing_cnpj', '_billing_cpf_cnpj', 'billing_cpf');
		foreach ($metaKeys as $mk) {
			$val = $order->get_meta($mk, true);
			if (! empty($val)) {
				$cpfCnpjDest = preg_replace('/\D+/', '', (string) $val);
				break;
			}
		}
		if ('' === $cpfCnpjDest) {
			$cpfCnpjDest = '44598844884'; //Chumbado
		}

		$shippingAddress1 = (string) $order->get_shipping_address_1();
		$shippingAddress2 = (string) $order->get_shipping_address_2();
		$billingAddress1  = (string) $order->get_billing_address_1();
		$billingAddress2  = (string) $order->get_billing_address_2();

		$logradouroDest = '' !== $shippingAddress1 ? $shippingAddress1 : $billingAddress1;
		$complementoDest = '' !== $shippingAddress2 ? $shippingAddress2 : $billingAddress2;
		if ('' === trim($complementoDest)) {
			$complementoDest = '-'; //Chumbado
		}

		$numeroDest = '';
		$bairroDest = '';
		$numMeta = $order->get_meta('_shipping_number', true);
		if (empty($numMeta)) {
			$numMeta = $order->get_meta('_billing_number', true);
		}
		$numeroDest = ! empty($numMeta) ? (string) $numMeta : 'S/N';

		$bairroMeta = $order->get_meta('_shipping_neighborhood', true);
		if (empty($bairroMeta)) {
			$bairroMeta = $order->get_meta('_billing_neighborhood', true);
		}
		$bairroDest = ! empty($bairroMeta) ? (string) $bairroMeta : 'Centro'; //Chumbado

		$payload = array(
			'token_cliente_postagem'      => $token,
			'tipo_entrega'                => $tipoEntrega,
			'cep_remetente'               => $cepRemetente,
			'cep_destinatario'            => $cepDestinatario,
			'peso'                        => $this->etiqueta_format_number($totalWeight),
			'altura'                      => $this->etiqueta_format_number($finalHeight),
			'largura'                     => $this->etiqueta_format_number($finalWidth),
			'comprimento'                 => $this->etiqueta_format_number($finalLength),
			'valor_encomenda'             => (string) $valorEncomenda,
			'nome_remetente'              => (string) get_option('cepcerto_nome_remetente', ''),
			'cpf_cnpj_remetente'          => preg_replace('/\D+/', '', (string) get_option('cepcerto_cpf_cnpj_remetente', '')),
			'whatsapp_remetente'          => preg_replace('/\D+/', '', (string) get_option('cepcerto_whatsapp_remetente', '')),
			'email_remetente'             => (string) get_option('cepcerto_email_remetente', ''),
			'logradouro_remetente'        => (string) get_option('cepcerto_logradouro_remetente', ''),
			'bairro_remetente'            => (string) get_option('cepcerto_bairro_remetente', ''),
			'numero_endereco_remetente'   => (string) get_option('cepcerto_numero_endereco_remetente', ''),
			'complemento_remetente'       => (string) get_option('cepcerto_complemento_remetente', ''),
			'nome_destinatario'           => $nomeDestinatario,
			'cpf_cnpj_destinatario'       => $cpfCnpjDest,
			'whatsapp_destinatario'       => $billingPhone,
			'email_destinatario'          => mb_substr($billingEmail, 0, 50),
			'logradouro_destinatario'     => mb_substr($logradouroDest, 0, 40),
			'bairro_destinatario'         => mb_substr($bairroDest, 0, 30),
			'numero_endereco_destinatario' => mb_substr($numeroDest, 0, 10),
			'complemento_destinatario'    => mb_substr($complementoDest, 0, 20),
			'tipo_doc_fiscal'             => 'declaracao',
			'produtos'                    => $produtos,
		);

		$api    = new CepCerto_Api();
		$result = $api->postagem_frete($payload);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		if (! is_array($result)) {
			wp_send_json_error(array('message' => 'Resposta inválida da API.'), 400);
		}

		$sucesso = isset($result['sucesso']) ? (bool) $result['sucesso'] : false;
		if (! $sucesso) {
			$msg = isset($result['mensagem']) ? (string) $result['mensagem'] : 'Erro desconhecido da API.';
			wp_send_json_error(array('message' => $msg), 400);
		}

		$frete = isset($result['frete']) && is_array($result['frete']) ? $result['frete'] : array();

		$order->update_meta_data('_cepcerto_etiqueta', $frete);
		$order->save();

		wp_send_json_success(array(
			'frete'        => $frete,
			'message'      => isset($result['mensagem']) ? (string) $result['mensagem'] : 'Etiqueta gerada com sucesso.',
			'reload_saldo' => true,
		), 200);
	}

	public function ajax_cancelar_etiqueta()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Sem permissão.'), 403);
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (empty($nonce) || ! wp_verify_nonce($nonce, 'cepcerto_etiqueta')) {
			wp_send_json_error(array('message' => 'Nonce inválido.'), 400);
		}

		$orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		if ($orderId < 1) {
			wp_send_json_error(array('message' => 'Pedido inválido.'), 400);
		}

		$order = wc_get_order($orderId);
		if (! $order instanceof WC_Order) {
			wp_send_json_error(array('message' => 'Pedido não encontrado.'), 404);
		}

		$etiqueta = $order->get_meta('_cepcerto_etiqueta', true);
		if (! is_array($etiqueta) || empty($etiqueta['codigoObjeto'])) {
			wp_send_json_error(array('message' => 'Nenhuma etiqueta encontrada para este pedido.'), 400);
		}

		$token = get_option('cepcerto_token_cliente_postagem', '');
		if (empty($token)) {
			wp_send_json_error(array('message' => 'Token de cliente não configurado.'), 400);
		}

		$codObjeto = (string) $etiqueta['codigoObjeto'];

		$api    = new CepCerto_Api();
		$result = $api->cancela_postagem($token, $codObjeto);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()), 400);
		}

		if (! is_array($result)) {
			wp_send_json_error(array('message' => 'Resposta inválida da API.'), 400);
		}

		$sucesso = isset($result['sucesso']) ? (bool) $result['sucesso'] : false;
		if (! $sucesso) {
			$msg = isset($result['mensagem']) ? (string) $result['mensagem'] : 'Erro ao cancelar.';
			wp_send_json_error(array('message' => $msg), 400);
		}

		$order->delete_meta_data('_cepcerto_etiqueta');
		$order->save();

		wp_send_json_success(array(
			'message'      => isset($result['mensagem']) ? (string) $result['mensagem'] : 'Etiqueta cancelada com sucesso.',
			'reload_saldo' => true,
		), 200);
	}

	private function resolve_tipo_entrega($order)
	{
		$shippingMethods = $order->get_shipping_methods();
		foreach ($shippingMethods as $method) {
			$methodId = (string) $method->get_method_id();
			if (strpos($methodId, 'cepcerto_') === 0) {
				$tipo = str_replace('cepcerto_', '', $methodId);
				$map = array(
					'pac'            => 'pac',
					'sedex'          => 'sedex',
					'jadlog_package' => 'jadlog_package',
					'jadlog_dotcom'  => 'jadlog_dotcom',
				);
				if (isset($map[$tipo])) {
					return $map[$tipo];
				}
			}
			$methodTitle = strtolower((string) $method->get_method_title());
			if (strpos($methodTitle, 'sedex') !== false) {
				return 'sedex';
			}
			if (strpos($methodTitle, 'pac') !== false) {
				return 'pac';
			}
		}
		return 'pac';
	}

	private function etiqueta_to_float($value)
	{
		$value = (string) $value;
		$value = str_replace(',', '.', $value);
		$value = preg_replace('/[^0-9.\-]/', '', $value);
		if ('' === $value || '-' === $value) {
			return 0.0;
		}
		return (float) $value;
	}

	private function etiqueta_convert_weight_to_kg($value)
	{
		$unit = strtolower((string) get_option('woocommerce_weight_unit', 'kg'));
		$value = (float) str_replace(',', '.', (string) $value);
		if ('g' === $unit) {
			return $value / 1000;
		}
		return $value;
	}

	private function etiqueta_convert_dimension_to_cm($value)
	{
		$unit = strtolower((string) get_option('woocommerce_dimension_unit', 'cm'));
		$value = (float) str_replace(',', '.', (string) $value);
		if ('m' === $unit) {
			return $value * 100;
		}
		if ('mm' === $unit) {
			return $value / 10;
		}
		return $value;
	}

	private function etiqueta_format_number($value)
	{
		$value = (float) $value;
		return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
	}

	public function download_log()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die('Sem permissão.');
		}
		check_admin_referer('cepcerto_download_log');

		if (! class_exists('CepCerto_Logger')) {
			wp_die('Logger indisponível.');
		}

		$file = CepCerto_Logger::get_latest_log_file();
		if (! $file || ! file_exists($file)) {
			wp_die('Arquivo de log não encontrado.');
		}

		nocache_headers();
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . basename($file));
		header('Content-Length: ' . filesize($file));
		readfile($file);
		exit;
	}

	private function tail_file($file, $maxLines = 200)
	{
		$lines = @file($file, FILE_IGNORE_NEW_LINES);
		if (! is_array($lines)) {
			return '';
		}
		$total = count($lines);
		$start = max(0, $total - (int) $maxLines);
		return implode("\n", array_slice($lines, $start));
	}

	private function tail_file_lines($file, $maxLines = 200)
	{
		$lines = @file($file, FILE_IGNORE_NEW_LINES);
		if (! is_array($lines)) {
			return array();
		}
		$total = count($lines);
		$start = max(0, $total - (int) $maxLines);
		return array_slice($lines, $start);
	}

	private function parse_log_line($line)
	{
		$line = (string) $line;
		$line = trim($line);
		if ('' === $line) {
			return null;
		}

		$re = '/^\[(?<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[(?<level>[^\]]+)\] (?<message>.*?)(?:\s+(?<json>\{.*\}))?$/';
		if (! preg_match($re, $line, $m)) {
			return null;
		}

		$ts = strtotime($m['date'] . ' UTC');
		$ctx = '';
		$endpoint = '';
		if (isset($m['json']) && '' !== trim((string) $m['json'])) {
			$ctxRaw = (string) $m['json'];
			$decoded = json_decode($ctxRaw, true);
			if (JSON_ERROR_NONE === json_last_error()) {
				$endpoint = $this->extract_endpoint_from_context($decoded);
				$decoded = $this->normalize_log_context_for_display($decoded);
				$ctx = wp_json_encode(
					$decoded,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				);
			} else {
				$ctx = $ctxRaw;
			}
		}

		return array(
			'ts' => is_int($ts) ? $ts : null,
			'ts_str' => (string) $m['date'] . ' UTC',
			'level' => strtoupper((string) $m['level']),
			'endpoint' => (string) $endpoint,
			'message' => (string) $m['message'],
			'context' => $ctx,
		);
	}

	private function extract_endpoint_from_context($context)
	{
		if (! is_array($context)) {
			return '';
		}

		if (isset($context['url']) && is_string($context['url']) && '' !== trim($context['url'])) {
			return trim($context['url']);
		}

		if (isset($context['endpoint']) && is_string($context['endpoint']) && '' !== trim($context['endpoint'])) {
			return trim($context['endpoint']);
		}

		return '';
	}

	private function normalize_log_context_for_display($value)
	{
		if (is_array($value)) {
			$out = array();
			foreach ($value as $k => $v) {
				$key = strtolower((string) $k);
				if (in_array($key, array('api_key', 'token', 'authorization', 'token_cliente_postagem'), true)) {
					$out[$k] = '***';
					continue;
				}
				$out[$k] = $this->normalize_log_context_for_display($v);
			}
			return $out;
		}

		if (is_string($value)) {
			$trim = trim($value);
			$startsJson = '' !== $trim && ('{' === substr($trim, 0, 1) || '[' === substr($trim, 0, 1));
			if ($startsJson) {
				$decoded = json_decode($trim, true);
				if (JSON_ERROR_NONE === json_last_error()) {
					return $this->normalize_log_context_for_display($decoded);
				}
			}
		}

		return $value;
	}
}
