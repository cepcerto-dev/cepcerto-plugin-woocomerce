/**
 * CepCerto Admin Header Script
 * Handles balance display in admin header
 *
 * @package CepCerto
 * @since 1.0.0
 */
(function() {
	'use strict';

	if (!window.CepCertoAdmin) {
		return;
	}

	var ajaxUrl = CepCertoAdmin.ajaxUrl;
	var nonceSaldo = CepCertoAdmin.nonceSaldo;
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
