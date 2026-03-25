(function () {
	function $(id) {
		return document.getElementById( id );
	}

	function sanitizeCep(value) {
		return String( value || '' ).replace( /\D+/g, '' );
	}

	function maskCep(value) {
		var digits = sanitizeCep( value );
		if (digits.length > 5) {
			return digits.slice( 0, 5 ) + '-' + digits.slice( 5, 8 );
		}
		return digits;
	}

	function formatMoney(value) {
		var n = Number( String( value ).replace( ',', '.' ) );
		if ( ! isFinite( n ) || n <= 0) {
			return null;
		}
		return n.toLocaleString( 'pt-BR', { style: 'currency', currency: 'BRL' } );
	}

	function pluralDias(d) {
		var n = parseInt( d, 10 );
		if ( ! n || n <= 0) {
			return '';
		}
		return n === 1 ? '1 dia útil' : n + ' dias úteis';
	}

	function buildItem(name, price, days) {
		var fmtPrice = formatMoney( price );
		if ( ! fmtPrice) {
			return '';
		}
		var timeHtml = days ? '<span class="cepcerto-result__carrier-time">' + pluralDias( days ) + '</span>' : '';
		return '<div class="cepcerto-result__item">' +
		'<div class="cepcerto-result__carrier">' +
		'<span class="cepcerto-result__carrier-name">' + name + '</span>' +
		timeHtml +
		'</div>' +
		'<span class="cepcerto-result__price">' + fmtPrice + '</span>' +
		'</div>';
	}

	function extractServices(q) {
		var items = [];

		if (q.frete) {
			var f = q.frete;
			if (f.valor_pac) {
				items.push( { name: 'PAC',            price: f.valor_pac,            days: f.prazo_pac } );
			}
			if (f.valor_sedex) {
				items.push( { name: 'SEDEX',          price: f.valor_sedex,          days: f.prazo_sedex } );
			}
			if (f.valor_jadlog_package) {
				items.push( { name: 'Jadlog Package', price: f.valor_jadlog_package, days: f.prazo_jadlog_package } );
			}
			if (f.valor_jadlog_dotcom) {
				items.push( { name: 'Jadlog .com',    price: f.valor_jadlog_dotcom,  days: f.prazo_jadlog_dotcom } );
			}
		}

		if (q.servicos && Array.isArray( q.servicos )) {
			q.servicos.forEach(
				function (s) {
					if (s.valor) {
						items.push( { name: s.servico || 'Frete', price: s.valor, days: s.prazo } );
					}
				}
			);
		}

		if (items.length === 0) {
			if (q.valorpac) {
				items.push( { name: 'PAC',   price: q.valorpac,   days: q.prazopac } );
			}
			if (q.valorsedex) {
				items.push( { name: 'SEDEX', price: q.valorsedex, days: q.prazosedex } );
			}
		}

		return items;
	}

	function renderResult(container, payload) {
		var q = payload && payload.quote ? payload.quote : null;
		if ( ! q) {
			container.innerHTML = '<div class="cepcerto-result__error">Não foi possível obter a cotação.</div>';
			return;
		}

		var services = extractServices( q );
		if (services.length === 0) {
			container.innerHTML = '<div class="cepcerto-result__empty">Nenhuma opção de frete disponível para este CEP.</div>';
			return;
		}

		var html = '<div class="cepcerto-result__list">';
		services.forEach(
			function (s) {
				var row = buildItem( s.name, s.price, s.days );
				if (row) {
					html += row;
				}
			}
		);
		html               += '</div>';
		container.innerHTML = html;
	}

	// CEP mask on input
	document.addEventListener(
		'input',
		function (e) {
			if (e.target && e.target.id === 'cepcerto-postcode') {
				var pos        = e.target.selectionStart;
				var old        = e.target.value;
				e.target.value = maskCep( old );
				if (pos < e.target.value.length) {
					e.target.setSelectionRange( pos, pos );
				}
			}
		}
	);

	// Allow Enter key to trigger calculation
	document.addEventListener(
		'keydown',
		function (e) {
			if (e.target && e.target.id === 'cepcerto-postcode' && e.key === 'Enter') {
				e.preventDefault();
				var btn = $( 'cepcerto-calc-btn' );
				if (btn) {
					btn.click();
				}
			}
		}
	);

	document.addEventListener(
		'click',
		function (e) {
			var btn = e.target && e.target.id === 'cepcerto-calc-btn' ? e.target : null;
			if ( ! btn) {
				return;
			}

			var postcodeEl = $( 'cepcerto-postcode' );
			var resultEl   = $( 'cepcerto-result' );

			if ( ! postcodeEl || ! resultEl) {
				return;
			}

			var productId = btn.getAttribute( 'data-product-id' );
			var cep       = sanitizeCep( postcodeEl.value );

			if (cep.length !== 8) {
				resultEl.innerHTML = '<div class="cepcerto-result__error">Informe um CEP válido.</div>';
				return;
			}

			btn.disabled       = true;
			resultEl.innerHTML = '<div class="cepcerto-result__loading"><span class="cepcerto-result__spinner"></span> Calculando...</div>';

			var form = new FormData();
			form.append( 'action', (window.CepCertoCalculator && window.CepCertoCalculator.action) || 'cepcerto_calculate_product_shipping' );
			form.append( 'nonce', (window.CepCertoCalculator && window.CepCertoCalculator.nonce) || '' );
			form.append( 'product_id', productId || '' );
			form.append( 'postcode', cep );

			fetch(
				(window.CepCertoCalculator && window.CepCertoCalculator.ajaxUrl) || '/wp-admin/admin-ajax.php',
				{
					method: 'POST',
					credentials: 'same-origin',
					body: form
				}
			)
			.then(
				function (r) {
					return r.text().then(
						function (t) {
							return { ok: r.ok, status: r.status, text: t };
						}
					);
				}
			)
			.then(
				function (resp) {
					var json = null;
					try {
						json = JSON.parse( resp.text );
					} catch (e) {
						resultEl.innerHTML = '<div class="cepcerto-result__error">Resposta inválida do servidor.</div>';
						return;
					}

					if ( ! json || ! json.success) {
						var msg            = (json && json.data && json.data.message) ? json.data.message : 'Erro ao calcular.';
						resultEl.innerHTML = '<div class="cepcerto-result__error">' + msg + '</div>';
						return;
					}

					renderResult( resultEl, json.data );
				}
			)
			.catch(
				function () {
					resultEl.innerHTML = '<div class="cepcerto-result__error">Erro ao calcular. Tente novamente.</div>';
				}
			)
			.finally(
				function () {
					btn.disabled = false;
				}
			);
		}
	);
})();
