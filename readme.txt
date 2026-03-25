=== CepCerto ===
Contributors: cepcerto
Tags: shipping, woocommerce, brazil, correios, frete
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Plugin para cotação de fretes no WooCommerce utilizando a API do CepCerto com suporte para PAC, SEDEX e Jadlog.

== Description ==

O **CepCerto** é um plugin WordPress/WooCommerce que permite calcular fretes automaticamente utilizando a API do CepCerto. Com ele você pode oferecer cotações precisas de frete para seus clientes diretamente na página do produto e no checkout.

= Principais recursos =

* Cotação de frete via API CepCerto
* Suporte para PAC, SEDEX, Jadlog Package e Jadlog .com
* Calculadora de frete na página do produto
* Integração completa com checkout do WooCommerce
* Compatível com HPOS (High-Performance Order Storage)
* Compatível com WooCommerce Cart & Checkout Blocks
* Geração de etiquetas de envio
* Rastreamento de encomendas
* Gestão de saldo e créditos
* Sistema de logs para debug

= Funcionalidades avançadas =

* Configuração de dimensões e peso padrão
* Taxa adicional por método de envio
* Dias extras de prazo
* Consulta de CEP automática
* Interface administrativa intuitiva
* Extrato financeiro completo

= Requisitos =

* WordPress 6.0 ou superior
* WooCommerce 7.0 ou superior
* PHP 7.4 ou superior
* Token de API CepCerto (obtenha em https://cepcerto.com/)

= Suporte =

Para suporte técnico, visite [cepcerto.com](https://cepcerto.com/) ou entre em contato através do nosso site.

== Installation ==

= Instalação automática =

1. Acesse o painel do WordPress
2. Vá em Plugins > Adicionar novo
3. Busque por "CepCerto"
4. Clique em "Instalar agora"
5. Ative o plugin

= Instalação manual =

1. Faça o download do plugin
2. Extraia o arquivo ZIP
3. Envie a pasta `cepcerto` para `/wp-content/plugins/`
4. Ative o plugin através do menu 'Plugins' no WordPress

= Configuração =

1. Após ativar, acesse **CepCerto** no menu lateral do WordPress
2. Na aba **Dados remetente**, preencha suas informações:
   * Nome completo
   * CPF ou CNPJ
   * WhatsApp
   * E-mail
   * CEP de origem
   * Endereço completo
3. Na aba **Configurações**, ajuste:
   * Dimensões e peso padrão da caixa
   * Valor mínimo da encomenda
   * Ative/desative o modo debug
4. Configure os métodos de envio em **WooCommerce > Configurações > Envio > Zonas de envio**
5. Adicione os métodos CepCerto (PAC, SEDEX, Jadlog) na zona desejada

== Frequently Asked Questions ==

= Preciso de uma conta CepCerto? =

Sim, você precisa de um token de API do CepCerto. O token é gerado automaticamente ao ativar o plugin pela primeira vez.

= Como adiciono créditos? =

Acesse **CepCerto > Saldo** e utilize a opção de adicionar crédito via PIX.

= O plugin funciona com HPOS? =

Sim! O plugin é totalmente compatível com o High-Performance Order Storage (HPOS) do WooCommerce.

= Posso usar apenas no checkout ou na página do produto? =

Você pode escolher onde exibir a calculadora em **CepCerto > Configurações > Exibição do cálculo de frete**.

= Como faço rastreamento dos pedidos? =

O rastreamento aparece automaticamente na coluna "Rastreio" da lista de pedidos e na aba **CepCerto > Pedidos**.

= Onde ficam os logs? =

Os logs ficam em `/wp-content/uploads/cepcerto-logs/` e podem ser visualizados em **CepCerto > Logs** (quando o debug está ativo).

= O plugin funciona com WooCommerce Blocks? =

Sim, o plugin é compatível com Cart & Checkout Blocks do WooCommerce.

== Screenshots ==

1. Calculadora de frete na página do produto
2. Configurações do plugin
3. Gestão de pedidos e etiquetas
4. Saldo e extrato financeiro
5. Logs de debug

== Changelog ==

= 1.0.0 =
* Lançamento inicial
* Cotação de frete via API CepCerto
* Suporte para PAC, SEDEX, Jadlog Package e Jadlog .com
* Calculadora na página do produto
* Integração com checkout WooCommerce
* Geração de etiquetas
* Rastreamento de encomendas
* Gestão de saldo e créditos
* Sistema de logs
* Compatibilidade HPOS
* Compatibilidade WooCommerce Blocks

== Upgrade Notice ==

= 1.0.0 =
Versão inicial do plugin.

== Additional Information ==

= Privacidade =

Este plugin conecta-se à API do CepCerto (https://cepcerto.com/) para realizar cotações de frete e gerenciar envios. Os dados enviados incluem:

* CEP de origem e destino
* Dimensões e peso dos produtos
* Valor da encomenda
* Informações do remetente (para geração de etiquetas)

Consulte a política de privacidade do CepCerto em https://cepcerto.com/privacidade

= Créditos =

Desenvolvido por CepCerto - https://cepcerto.com/
