# Prerrequisitos del sistema

Requisitos de hardware y software necesarios para correr el proyecto
en una máquina de desarrollo.

## Sistema operativo

Soporte oficial:

- `Ubuntu` 22.04 LTS+
- `Debian` 12+
- `macOS` 13+ (con `Homebrew`)

Windows con WSL2 también funciona pero no es el flujo primario.

## Hardware mínimo

| Recurso  | Mínimo      | Recomendado  |
| -------- | ----------- | ------------ |
| CPU      | 2 cores     | 4+ cores     |
| RAM      | 4 GB        | 8+ GB        |
| Disco    | 20 GB SSD   | 50+ GB SSD   |

## Software requerido

### Backend (PHP)

| Herramienta | Versión | Instalación (Ubuntu)                                |
| ----------- | ------- | --------------------------------------------------- |
| `PHP`       | 8.3+    | `sudo apt install php8.3 php8.3-{cli,mbstring,xml,pgsql,zip,curl,intl}` |
| `Composer`  | 2.x     | `curl -sS https://getcomposer.org/installer \| php && sudo mv composer.phar /usr/local/bin/composer` |

Extensiones PHP requeridas:

```bash
php -m | grep -E "mbstring|pdo_pgsql|tokenizer|xml|ctype|json|bcmath|fileinfo|openssl"
```

### Frontend (Node)

| Herramienta | Versión  | Instalación                                       |
| ----------- | -------- | ------------------------------------------------- |
| `Node.js`   | 22.12+   | `nvm install 22.12` (recomendado)               |
| `pnpm`      | 9+       | `npm install -g pnpm@9`                          |

### Bases de datos

| Herramienta  | Versión | Forma recomendada        |
| ------------ | ------- | ------------------------ |
| `Docker`     | 24+     | Para Postgres + Redis    |
| `Docker Compose` v2 | incluido | `docker compose up -d` |

Alternativa sin Docker: `PostgreSQL` 18 + `Redis` 7 instalados
nativamente. Menos recomendado por portabilidad.

### Reverse proxy

| Herramienta | Versión | Instalación                            |
| ----------- | ------- | -------------------------------------- |
| `Caddy`     | 2.x     | `sudo apt install caddy` (Ubuntu)      |

Versión mínima: `Caddy 2.11+`.

### Linter y herramientas adicionales

| Herramienta         | Versión | Instalación                              |
| ------------------- | ------- | ---------------------------------------- |
| `git`               | 2.30+   | Preinstalado en Linux/macOS              |
| `make`              | 4.x     | `sudo apt install make`                  |
| `markdownlint-cli`  | 0.42+   | `npm install -g markdownlint-cli@0.42`   |

## Verificación rápida

```bash
# Versiones de runtime
php --version       # PHP 8.3.x
composer --version  # Composer 2.x
node --version      # v22.12.x
pnpm --version      # 9.x
docker --version    # 24.x
caddy version       # v2.11.x
git --version       # 2.x
```

Si algún comando no está disponible, instalá el prerrequisito
correspondiente antes de continuar.

## Puertos en uso

| Servicio          | Puerto | Acceso                |
| ----------------- | ------ | --------------------- |
| `PostgreSQL`      | 5432   | localhost only        |
| `Redis`           | 6379   | localhost only        |
| Backend Laravel   | 8000   | localhost / Caddy    |
| Frontend Astro    | 4321   | localhost / Caddy    |
| `Caddy` (HTTP)    | 80     | público (prod) / dev  |
| `Caddy` (HTTPS)   | 443    | público (prod)        |

## Próximo paso

[installation.md](installation.md "installation.md") — Clonar el repo
e instalar dependencias.
