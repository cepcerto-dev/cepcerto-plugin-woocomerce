/**
 * CepCerto Admin Saldo Tab Script
 * Handles balance management, credit addition, and financial statements
 *
 * @package CepCerto
 * @since 1.0.0
 */
(function() {
	'use strict';

	if (!window.CepCertoSaldo) {
		return;
	}

	var ajaxUrl = CepCertoSaldo.ajaxUrl;
	var nonceSaldo = CepCertoSaldo.nonceSaldo;
	var nonceCredito = CepCertoSaldo.nonceCredito;
	var nonceFinanceiro = CepCertoSaldo.nonceFinanceiro;

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
		var table = document.getElementById(tableId);
		if (!table) return;
		var tbody = table.querySelector('tbody');
		if (!tbody) return;
		
		while (tbody.firstChild) {
			tbody.removeChild(tbody.firstChild);
		}
		
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
			
			var paymentConfirmed = document.getElementById('cepcerto-credito-payment-confirmed');
			if (paymentConfirmed && paymentConfirmed.parentNode) {
				paymentConfirmed.parentNode.removeChild(paymentConfirmed);
			}
			
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
			if (qrcodeContainer) {
				while (qrcodeContainer.firstChild) {
					qrcodeContainer.removeChild(qrcodeContainer.firstChild);
				}
			}

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

				var resultado = document.getElementById('cepcerto-credito-resultado');
				if (resultado) {
					resultado.style.display = 'block';
				}
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

	var extratoLimit = 20;
	var extratoOffset = 0;
	var extratoTotal = null;
	var extratoLoading = false;
	var extratoList = document.getElementById('cepcerto-extrato-list');
	var extratoReloadBtn = document.getElementById('cepcerto-extrato-reload');
	var extratoLoadMoreBtn = document.getElementById('cepcerto-extrato-load-more');
	var extratoStatus = document.getElementById('cepcerto-extrato-status');

	function setExtratoStatus(text) {
		if (extratoStatus) extratoStatus.textContent = text || '';
	}

	function renderExtratoItems(items, append) {
		if (!extratoList) return;
		if (!append) extratoList.innerHTML = '';
		if (!items || !items.length) {
			if (!append) {
				extratoList.innerHTML = '<div style="padding:12px; color:#666;">Sem lançamentos.</div>';
			}
			return;
		}
		items.forEach(function(it){
			var row = document.createElement('div');
			row.style.padding = '10px 12px';
			row.style.borderBottom = '1px solid #f0f0f0';
			row.style.display = 'flex';
			row.style.alignItems = 'center';
			row.style.justifyContent = 'space-between';

			var left = document.createElement('div');
			left.innerHTML = '<div style="font-weight:600;">' + escHtml(it.data || it.data_iso || '') + '</div>' +
				'<div style="color:#555;">' + escHtml(it.descricao || '') + '</div>';

			var right = document.createElement('div');
			right.style.fontWeight = '600';
			var color = '#333';
			if (it.classe === 'positivo') color = '#2e7d32';
			if (it.classe === 'negativo') color = '#c62828';
			right.style.color = color;
			right.textContent = it.valor_br || '';

			row.appendChild(left);
			row.appendChild(right);
			extratoList.appendChild(row);
		});
	}

	function updateExtratoButtons() {
		if (!extratoLoadMoreBtn) return;
		if (extratoTotal === null) {
			extratoLoadMoreBtn.style.display = '';
			return;
		}
		var canLoadMore = extratoOffset < extratoTotal;
		extratoLoadMoreBtn.style.display = canLoadMore ? '' : 'none';
	}

	function loadExtrato(reset) {
		if (extratoLoading) return;
		extratoLoading = true;
		if (reset) {
			extratoOffset = 0;
			extratoTotal = null;
		}
		if (extratoReloadBtn) extratoReloadBtn.disabled = true;
		if (extratoLoadMoreBtn) extratoLoadMoreBtn.disabled = true;
		setExtratoStatus('Carregando...');
		postAjax('cepcerto_financeiro', {
			_wpnonce: nonceFinanceiro,
			limit: extratoLimit,
			offset: extratoOffset
		}).then(function(resp){
			if (!resp || resp.success === false) {
				var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erro ao carregar extrato.';
				showNotice('notice-error', msg);
				return;
			}
			var data = resp.data || {};
			var items = data.extrato || [];
			renderExtratoItems(items, !reset);
			extratoTotal = (typeof data.total === 'number') ? data.total : extratoTotal;
			extratoOffset += items.length;
			var showing = extratoOffset;
			if (extratoTotal !== null && isFinite(extratoTotal)) {
				setExtratoStatus('Mostrando ' + showing + ' de ' + extratoTotal);
			} else {
				setExtratoStatus('Mostrando ' + showing);
			}
			updateExtratoButtons();
		}).catch(function(err){
			showNotice('notice-error', 'Erro ao carregar extrato.');
		}).finally(function(){
			extratoLoading = false;
			if (extratoReloadBtn) extratoReloadBtn.disabled = false;
			if (extratoLoadMoreBtn) extratoLoadMoreBtn.disabled = false;
		});
	}

	if (extratoReloadBtn) {
		extratoReloadBtn.addEventListener('click', function(e){
			e.preventDefault();
			loadExtrato(true);
		});
	}
	if (extratoLoadMoreBtn) {
		extratoLoadMoreBtn.addEventListener('click', function(e){
			e.preventDefault();
			loadExtrato(false);
		});
	}

	loadExtrato(true);

	// Remove competitor notices
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
