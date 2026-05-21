/**
 * CepCerto Admin Orders Tab Script
 * Handles label generation and cancellation for orders
 *
 * @package CepCerto
 * @since 1.0.0
 */
(function() {
	'use strict';

	if (!window.CepCertoOrders) {
		return;
	}

	var ajaxUrl = CepCertoOrders.ajaxUrl;
	var nonce = CepCertoOrders.nonce;
	var urlRastreioEncomenda = CepCertoOrders.urlRastreio;

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
		var trackCell = row.querySelector('.cepcerto-col-rastreio');
		var orderId = row.getAttribute('data-order-id');
		var codigo  = (frete && frete.codigoObjeto) ? frete.codigoObjeto : '';
		var pdfUrl  = (frete && frete.pdfUrlEtiqueta) ? frete.pdfUrlEtiqueta : '';
		var declUrl = (frete && frete.declaracaoUrl) ? frete.declaracaoUrl : '';

		var html = '<code>' + escHtml(codigo) + '</code><br>';
		if (pdfUrl)  html += '<a href="' + escAttr(pdfUrl) + '" target="_blank">Etiqueta PDF</a>';
		if (declUrl) html += '<a href="' + escAttr(declUrl) + '" target="_blank" style="margin-left:6px;">Declaração</a>';
		etqCell.innerHTML = html;

		if (trackCell && codigo) {
			var trackHtml = '<code>' + escHtml(codigo) + '</code>';
			var link = urlRastreioEncomenda + codigo;
			trackHtml = '<a href="' + escAttr(link) + '" target="_blank">' + trackHtml + '</a>';
			trackCell.innerHTML = trackHtml;
		}

		actCell.innerHTML = '<button type="button" class="button cepcerto-btn-cancelar" data-order-id="' + escAttr(orderId) + '" data-cod-objeto="' + escAttr(codigo) + '">Cancelar</button>';
		bindCancelar(actCell.querySelector('.cepcerto-btn-cancelar'));
	}

	function clearRowEtiqueta(row) {
		var etqCell = row.querySelector('.cepcerto-col-etiqueta');
		var actCell = row.querySelector('.cepcerto-col-acoes');
		var trackCell = row.querySelector('.cepcerto-col-rastreio');
		var orderId = row.getAttribute('data-order-id');
		etqCell.innerHTML = '<span style="color:#999;">—</span>';
		
		if (trackCell) {
			trackCell.innerHTML = '<span style="color:#999;">—</span>';
		}
		
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
