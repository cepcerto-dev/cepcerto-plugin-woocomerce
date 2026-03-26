=== CepCerto ===
Contributors: cepcerto
Tags: shipping, woocommerce, brazil, correios, frete
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce shipping calculator plugin using CepCerto API with support for PAC, SEDEX and Jadlog carriers.

== Description ==

**CepCerto** is a WordPress/WooCommerce plugin that allows automatic shipping calculation using the CepCerto API. With it you can offer accurate shipping quotes to your customers directly on the product page and at checkout.

= Key Features =

* Shipping quotes via CepCerto API
* Support for PAC, SEDEX, Jadlog Package and Jadlog .com
* Shipping calculator on product page
* Full integration with WooCommerce checkout
* Compatible with HPOS (High-Performance Order Storage)
* Compatible with WooCommerce Cart & Checkout Blocks
* Shipping label generation
* Package tracking
* Balance and credit management
* Debug logging system

= Advanced Features =

* Default dimensions and weight configuration
* Additional fee per shipping method
* Extra delivery days
* Automatic ZIP code lookup
* Intuitive admin interface
* Complete financial statement

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 7.0 or higher
* PHP 7.4 or higher
* CepCerto API token (get yours at https://cepcerto.com/)

= Support =

For technical support, visit [cepcerto.com](https://cepcerto.com/) or contact us through our website.

== Installation ==

= Automatic Installation =

1. Access the WordPress admin panel
2. Go to Plugins > Add New
3. Search for "CepCerto"
4. Click "Install Now"
5. Activate the plugin

= Manual Installation =

1. Download the plugin
2. Extract the ZIP file
3. Upload the `cepcerto` folder to `/wp-content/plugins/`
4. Activate the plugin through the 'Plugins' menu in WordPress

= Configuration =

1. After activation, access **CepCerto** in the WordPress sidebar menu
2. In the **Sender Data** tab, fill in your information:
   * Full name
   * CPF or CNPJ (Brazilian tax ID)
   * WhatsApp
   * Email
   * Origin ZIP code
   * Complete address
3. In the **Settings** tab, configure:
   * Default box dimensions and weight
   * Minimum order value
   * Enable/disable debug mode
4. Configure shipping methods in **WooCommerce > Settings > Shipping > Shipping Zones**
5. Add CepCerto methods (PAC, SEDEX, Jadlog) to the desired zone

== Frequently Asked Questions ==

= Do I need a CepCerto account? =

Yes, you need a CepCerto API token. The token is automatically generated when you activate the plugin for the first time.

= How do I add credits? =

Go to **CepCerto > Balance** and use the option to add credit via PIX.

= Does the plugin work with HPOS? =

Yes! The plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= Can I use it only on checkout or product page? =

You can choose where to display the calculator in **CepCerto > Settings > Shipping Calculator Display**.

= How do I track orders? =

Tracking appears automatically in the "Tracking" column of the orders list and in the **CepCerto > Orders** tab.

= Where are the logs? =

Logs are stored in `/wp-content/uploads/cepcerto-logs/` and can be viewed in **CepCerto > Logs** (when debug mode is active).

= Does the plugin work with WooCommerce Blocks? =

Yes, the plugin is compatible with WooCommerce Cart & Checkout Blocks.

== Screenshots ==

1. Calculadora de frete na página do produto
2. Configurações do plugin
3. Gestão de pedidos e etiquetas
4. Saldo e extrato financeiro
5. Logs de debug

== Changelog ==

= 1.0.0 =
* Initial release
* Shipping quotes via CepCerto API
* Support for PAC, SEDEX, Jadlog Package and Jadlog .com
* Product page calculator
* WooCommerce checkout integration
* Shipping label generation
* Package tracking
* Balance and credit management
* Logging system
* HPOS compatibility
* WooCommerce Blocks compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial plugin release.

== Additional Information ==

= Privacy =

This plugin connects to the CepCerto API (https://cepcerto.com/) to perform shipping quotes and manage shipments. The data sent includes:

* Origin and destination ZIP codes
* Product dimensions and weight
* Order value
* Sender information (for label generation)

Review CepCerto's privacy policy at https://cepcerto.com/privacidade

= Credits =

Developed by CepCerto - https://cepcerto.com/
