# 🛠️ Paywall-bypass (Marreta)

[![en](https://img.shields.io/badge/lang-en-red.svg)](https://github.com/juniorgregio/Paywall-bypass/blob/main/README.en.md)
[![pt-br](https://img.shields.io/badge/lang-pt--br-green.svg)](https://github.com/juniorgregio/Paywall-bypass/blob/main/README.md)

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-purple.svg)](https://www.php.net/)
[![Forks](https://img.shields.io/github/forks/juniorgregio/Paywall-bypass)](https://github.com/juniorgregio/Paywall-bypass/network/members)
[![Stars](https://img.shields.io/github/stars/juniorgregio/Paywall-bypass)](https://github.com/juniorgregio/Paywall-bypass/stargazers)
[![Issues](https://img.shields.io/github/issues/juniorgregio/Paywall-bypass)](https://github.com/juniorgregio/Paywall-bypass/issues)

Ferramenta que reduz barreiras de acesso e elementos que atrapalham a leitura de páginas na web.

Repositório: [github.com/juniorgregio/Paywall-bypass](https://github.com/juniorgregio/Paywall-bypass)

![Antes e depois](https://github.com/juniorgregio/Paywall-bypass/blob/main/screen.png?raw=true)

## ✨ Recursos

- Limpa e corrige URLs automaticamente
- Remove parâmetros de rastreamento
- Força HTTPS
- Troca de user agent para reduzir bloqueios
- HTML mais limpo; URLs relativas corrigidas
- Estilos e scripts customizáveis por domínio
- Remove elementos indesejados
- Cache configurável
- Bloqueio de domínios
- Proteção DMCA com mensagens personalizadas
- Headers e cookies configuráveis
- PHP-FPM e OPcache
- Suporte a proxy
- Proteção por senha do site (opcional; ver `.env.sample`)

## 🐳 Docker

Instale [Docker e Docker Compose](https://docs.docker.com/engine/install/).

Baixe o compose deste repositório:

`curl -o ./docker-compose.yml https://raw.githubusercontent.com/juniorgregio/Paywall-bypass/main/docker-compose.yml`

Edite conforme sua necessidade:

`nano docker-compose.yml`

O serviço principal usa **`build: .`** (imagem local a partir do `Dockerfile` neste repo).

- `SITE_NAME`, `SITE_DESCRIPTION`, `SITE_URL` — ver `.env.sample`
- `SITE_PASSWORD` — senha do site inteiro (opcional)
- `SELENIUM_HOST` — ex.: `selenium-hub:4444`
- `LANGUAGE` — `pt-br`, `en`, `es`, `de-de`, `ru-ru`

Depois: `docker compose up -d`

### 🛡️ DMCA

Para bloquear domínios por pedidos de DMCA, crie `app/cache/dmca_domains.json`:

```json
[
    {
        "host": "exemplo.com.br",
        "message": "Este conteúdo foi bloqueado a pedido"
    }
]
```

## Contribuições e suporte

Dúvidas e melhorias: [Issues no GitHub](https://github.com/juniorgregio/Paywall-bypass/issues).

Agradecimento aos projetos [Burlesco](https://github.com/burlesco/burlesco) e [Hover](https://github.com/nang-dev/hover-paywalls-browser-extension/), que serviram de base para várias regras.

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=juniorgregio/Paywall-bypass&type=Date)](https://star-history.com/#juniorgregio/Paywall-bypass&Date)
