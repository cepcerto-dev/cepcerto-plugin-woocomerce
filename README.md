# CepCerto (WooCommerce)

Plugin WordPress/WooCommerce para cotação de frete (PAC e SEDEX) via API CepCerto.

## Requisitos

- WordPress 6+
- WooCommerce 7+
- PHP 7.4+

## Instalação

1. Copie a pasta `cepcerto` para:

   `wp-content/plugins/cepcerto`

2. Ative o plugin em:

   **Plugins -> Ativar**

## Configuração

Acesse:

- **CepCerto -> Configurações**

Campos principais:

- **API Key (CepCerto)**: chave usada na cotação GET.
- **Token Cliente Postagem (CepCerto)**: usado para endpoints POST (postagem/etiqueta/cancelamento etc.).
- **CEP de origem**: CEP do remetente (somente números, 8 dígitos).
- **Base URL**: base da API de cotação (padrão do CepCerto).
- **Dimensões/Peso padrão**: valores usados quando o produto não possui peso/dimensões cadastrados.

## Como funciona

### 1) Métodos de entrega no checkout

O plugin registra métodos de envio no WooCommerce:

- PAC
- SEDEX

As cotações são calculadas usando a API do CepCerto.

### 2) Calculadora de frete na página do produto

Na página do produto é exibido:

- Input de CEP
- Botão para cotar via AJAX

O resultado é renderizado abaixo do input.

## Logs (log próprio do CepCerto)

O plugin possui log próprio para requisições e status.

### Onde ver

- **Wp-admin -> CepCerto -> Logs**

### Onde fica o arquivo

O log é salvo em:

- `wp-content/uploads/cepcerto-logs/cepcerto-YYYY-MM-DD.log`

### Observações

- Se a pasta `wp-content/uploads/` não estiver com permissão de escrita, o arquivo pode não ser criado.
- Em ambiente Windows/XAMPP, verifique permissões do diretório `uploads`.

## Troubleshooting

### A calculadora retorna `0` no AJAX

Isso normalmente significa que a action não foi registrada no WordPress. Confirme se o plugin está ativo e recarregue a página.

### A calculadora retorna erro de nonce

Recarregue a página (Ctrl+F5) e tente novamente. Nonce pode expirar ou ficar inválido em páginas de preview/cache.

### Não há métodos de envio no checkout

Verifique:

- WooCommerce ativo
- CEP de origem configurado
- Produtos com peso/dimensões (ou defaults preenchidos)

## Desenvolvimento

Estrutura (resumo):

- `cepcerto.php`: bootstrap do plugin
- `includes/class-cepcerto-api.php`: integração com API CepCerto (GET/POST)
- `includes/class-cepcerto-admin.php`: menu e configurações no wp-admin
- `includes/class-cepcerto-frontend.php`: calculadora no produto e endpoint AJAX
- `includes/class-cepcerto-logger.php`: log próprio do plugin
- `assets/product-calculator.js`: JS da calculadora

## Licença

Uso interno / conforme necessidade do projeto.
