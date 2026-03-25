/**
 * CepCerto Admin Logs Tab Script
 * Removes competitor plugin notices
 *
 * @package CepCerto
 * @since 1.0.0
 */
(function() {
	'use strict';

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
