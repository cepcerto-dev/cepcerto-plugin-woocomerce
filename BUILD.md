# Build Instructions

## Asset Minification

For production deployment, minify all CSS and JavaScript files:

### Prerequisites
```bash
npm install -g terser csso-cli
```

### Minify JavaScript
```bash
# Admin scripts
terser assets/admin-header.js -o assets/admin-header.min.js -c -m
terser assets/admin-sender.js -o assets/admin-sender.min.js -c -m
terser assets/admin-orders.js -o assets/admin-orders.min.js -c -m
terser assets/admin-saldo.js -o assets/admin-saldo.min.js -c -m
terser assets/admin-logs.js -o assets/admin-logs.min.js -c -m

# Frontend script
terser assets/product-calculator.js -o assets/product-calculator.min.js -c -m
```

### Minify CSS
```bash
csso assets/product-calculator.css -o assets/product-calculator.min.css
```

### Update Enqueue Calls
After minification, update `class-cepcerto-admin.php` and `class-cepcerto-frontend.php` to use `.min.js` and `.min.css` files in production.

## Translation Files

Generate POT file with WP-CLI:
```bash
wp i18n make-pot . languages/cepcerto.pot --domain=cepcerto
```

## WordPress Coding Standards

Check code against WPCS:
```bash
composer require --dev wp-coding-standards/wpcs
./vendor/bin/phpcs --standard=WordPress wp-content/plugins/cepcerto/
```

Fix automatically:
```bash
./vendor/bin/phpcbf --standard=WordPress wp-content/plugins/cepcerto/
```

## Screenshots

Add screenshots to the plugin root directory:
- `screenshot-1.png` - Product page shipping calculator
- `screenshot-2.png` - Plugin settings page
- `screenshot-3.png` - Orders management
- `screenshot-4.png` - Balance and financial statement
- `screenshot-5.png` - Debug logs

Recommended size: 1280x720 pixels

## Pre-Release Checklist

- [ ] All JavaScript inline moved to external files
- [ ] Assets minified for production
- [ ] Translation POT file generated
- [ ] Screenshots added
- [ ] readme.txt updated with latest version
- [ ] Tested on WordPress 6.7+
- [ ] Tested on WooCommerce 9.0+
- [ ] PHPCS validation passed
- [ ] All AJAX endpoints tested
- [ ] uninstall.php tested
