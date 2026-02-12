(function () {
  function $(id) {
    return document.getElementById(id);
  }

  function sanitizeCep(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function formatMoney(value) {
    var n = Number(String(value).replace(',', '.'));
    if (!isFinite(n)) return String(value);
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function renderResult(container, payload) {
    var q = payload && payload.quote ? payload.quote : null;
    if (!q) {
      container.innerHTML = '<div>Não foi possível obter a cotação.</div>';
      return;
    }

    var pac = q.valorpac ? ('<div><strong>PAC:</strong> ' + formatMoney(q.valorpac) + (q.prazopac ? ' - ' + q.prazopac + ' dia(s)' : '') + '</div>') : '';
    var sedex = q.valorsedex ? ('<div><strong>SEDEX:</strong> ' + formatMoney(q.valorsedex) + (q.prazosedex ? ' - ' + q.prazosedex + ' dia(s)' : '') + '</div>') : '';

    if (!pac && !sedex) {
      container.innerHTML = '<div>Cotação sem valores retornados.</div>';
      return;
    }

    container.innerHTML = '<div>' + pac + sedex + '</div>';
  }

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.id === 'cepcerto-calc-btn' ? e.target : null;
    if (!btn) return;

    var postcodeEl = $('cepcerto-postcode');
    var resultEl = $('cepcerto-result');

    if (!postcodeEl || !resultEl) return;

    var productId = btn.getAttribute('data-product-id');
    var cep = sanitizeCep(postcodeEl.value);

    if (cep.length !== 8) {
      resultEl.innerHTML = '<div>Informe um CEP válido.</div>';
      return;
    }

    resultEl.innerHTML = '<div>Calculando...</div>';

    var form = new FormData();
    form.append('action', (window.CepCertoCalculator && window.CepCertoCalculator.action) || 'cepcerto_calculate_product_shipping');
    form.append('nonce', (window.CepCertoCalculator && window.CepCertoCalculator.nonce) || '');
    form.append('product_id', productId || '');
    form.append('postcode', cep);

    console.log('CepCerto AJAX payload:', {
      action: (window.CepCertoCalculator && window.CepCertoCalculator.action) || 'cepcerto_calculate_product_shipping',
      nonce: (window.CepCertoCalculator && window.CepCertoCalculator.nonce) || '',
      product_id: productId,
      postcode: cep
    });

    fetch((window.CepCertoCalculator && window.CepCertoCalculator.ajaxUrl) || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: form
    })
      .then(function (r) {
        return r.text().then(function (t) {
          return { ok: r.ok, status: r.status, text: t };
        });
      })
      .then(function (resp) {
        var json = null;
        try {
          json = JSON.parse(resp.text);
        } catch (e) {
          console.log('CepCerto AJAX resposta não-JSON:', resp.status, resp.text);
          resultEl.innerHTML = '<div>Resposta inválida do servidor (' + resp.status + '): ' + String(resp.text).slice(0, 200) + '</div>';
          return;
        }

        if (!json || !json.success) {
          var msg = (json && json.data && json.data.message) ? json.data.message : 'Erro ao calcular.';
          console.log('CepCerto AJAX erro:', resp.status, json);
          resultEl.innerHTML = '<div>' + msg + '</div>';
          return;
        }

        renderResult(resultEl, json.data);
      })
      .catch(function (err) {
        console.log('CepCerto AJAX exception:', err);
        resultEl.innerHTML = '<div>Erro ao calcular.</div>';
      });
  });
})();
