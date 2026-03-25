/**
 * CepCerto Admin Sender Tab Script
 * Handles sender information form validation and CEP lookup
 *
 * @package CepCerto
 * @since 1.0.0
 */
(function() {
	'use strict';

	if (!window.CepCertoSender) {
		return;
	}

	var ajaxUrl = CepCertoSender.ajaxUrl;
	var nonceCep = CepCertoSender.nonceCep;
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
