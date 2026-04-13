# 🛠️ Paywall-bypass (Marreta)

[![pt-br](https://img.shields.io/badge/lang-pt--br-green.svg)](https://github.com/juniorgregio/Paywall-bypass/blob/main/README.md)
[![en](https://img.shields.io/badge/lang-en-red.svg)](https://github.com/juniorgregio/Paywall-bypass/blob/main/README.en.md)

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-purple.svg)](https://www.php.net/)
[![Forks](https://img.shields.io/github/forks/juniorgregio/Paywall-bypass)](https://github.com/juniorgregio/Paywall-bypass/network/members)
[![Stars](https://img.shields.io/github/stars/juniorgregio/Paywall-bypass)](https://github.com/juniorgregio/Paywall-bypass/stargazers)
[![Issues](https://img.shields.io/github/issues/juniorgregio/Paywall-bypass)](https://github.com/juniorgregio/Paywall-bypass/issues)

Tool that reduces access barriers and elements that get in the way of reading web pages.

Repository: [github.com/juniorgregio/Paywall-bypass](https://github.com/juniorgregio/Paywall-bypass)

![Before and after](https://github.com/juniorgregio/Paywall-bypass/blob/main/screen.png?raw=true)

## ✨ Features

- Cleans and normalizes URLs
- Strips tracking parameters
- Forces HTTPS
- User-agent switching to reduce blocking
- Cleaner HTML; relative URLs fixed
- Per-domain custom styles and scripts
- Removes unwanted elements
- Configurable cache
- Domain blocking
- DMCA protection with custom messages
- Configurable headers and cookies
- PHP-FPM and OPcache
- Proxy support
- Optional whole-site password (see `.env.sample`)

## 🐳 Docker

Install [Docker and Docker Compose](https://docs.docker.com/engine/install/).

Download the compose file from this repository:

`curl -o ./docker-compose.yml https://raw.githubusercontent.com/juniorgregio/Paywall-bypass/main/docker-compose.yml`

Edit to suit your setup:

`nano docker-compose.yml`

The main service uses **`build: .`** (local image from the `Dockerfile` in this repo).

- `SITE_NAME`, `SITE_DESCRIPTION`, `SITE_URL` — see `.env.sample`
- `SITE_PASSWORD` — optional site-wide password
- `SELENIUM_HOST` — e.g. `selenium-hub:4444`
- `LANGUAGE` — `pt-br`, `en`, `es`, `de-de`, `ru-ru`

Then run: `docker compose up -d`

### 🛡️ DMCA

To block domains from DMCA requests, create `app/cache/dmca_domains.json`:

```json
[
    {
        "host": "example.com",
        "message": "This content has been blocked on request"
    }
]
```

## Contributions and support

Questions and improvements: [GitHub Issues](https://github.com/juniorgregio/Paywall-bypass/issues).

Thanks to [Burlesco](https://github.com/burlesco/burlesco) and [Hover](https://github.com/nang-dev/hover-paywalls-browser-extension/) for rule ideas used in this project.

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=juniorgregio/Paywall-bypass&type=Date)](https://star-history.com/#juniorgregio/Paywall-bypass&Date)
