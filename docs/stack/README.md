# Stack tecnológico

Vista general de las tecnologías que componen la plataforma. Cada
subsección describe herramientas, versiones, cómo se instalaron y cómo
se usan.

## Capas del sistema

| Capa            | Tecnología principal | Documento                  |
| --------------- | -------------------- | -------------------------- |
| Frontend        | `Astro` 7 + `React` 19 | [stack/frontend.md](frontend.md "stack/frontend.md") |
| Backend         | `Laravel` 13 + `PHP` 8.3 | [stack/backend.md](backend.md "stack/backend.md") |
| Base de datos   | `PostgreSQL` 18 + `PostGIS` | [stack/database.md](database.md "stack/database.md") |
| Cache y colas   | `Redis` 7           | [stack/database.md](database.md "stack/database.md") |
| Almacenamiento  | `FTP` o `S3` compatible | [stack/database.md](database.md "stack/database.md") |
| Reverse proxy   | `Caddy` 2           | [stack/infrastructure.md](infrastructure.md "stack/infrastructure.md") |
| Contenedores    | `Docker` Compose     | [stack/infrastructure.md](infrastructure.md "stack/infrastructure.md") |
| Orquestación    | `Kubernetes` (producción) | [stack/infrastructure.md](infrastructure.md "stack/infrastructure.md") |
| DNS y CDN       | `Cloudflare`         | [stack/infrastructure.md](infrastructure.md "stack/infrastructure.md") |
| Tests           | `PHPUnit` 12, `Playwright` | [stack/frontend.md](frontend.md "stack/frontend.md") |
| Linter backend  | `Laravel Pint`, `Larastan` | [stack/backend.md](backend.md "stack/backend.md") |
| Linter frontend | `ESLint` 9, `Prettier` 3 | [stack/frontend.md](frontend.md "stack/frontend.md") |
| Linter docs     | `markdownlint-cli`    | [stack/frontend.md](frontend.md "stack/frontend.md") |

## Lenguajes y runtimes

| Herramienta     | Versión  | Archivo                     |
| --------------- | -------- | --------------------------- |
| `PHP`           | 8.3+     | [stack/backend.md](backend.md "stack/backend.md") |
| `Node.js`       | 22.12+   | [stack/frontend.md](frontend.md "stack/frontend.md") |
| `pnpm`          | 9+       | [stack/frontend.md](frontend.md "stack/frontend.md") |
| `Go`            | 1.22+    | (solo para tooling interno de Caddy) |

## Servicios externos

| Servicio       | Propósito                              |
| -------------- | -------------------------------------- |
| `Cloudflare`   | DNS, WAF, CDN, proxy inverso externo  |
| `Let's Encrypt`| Certificados TLS automáticos (vía Caddy) |

## Cómo se instaló todo

El setup detallado de cada pieza vive en
[getting-started/](../getting-started/README.md "getting-started/README.md").

Resumen rápido:

```bash
# 1. Clonar el repo
git clone https://github.com/D4nitrix13/AtlasCommerce.git
cd AtlasCommerce

# 2. Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate

# 3. Frontend
cd ../frontend
pnpm install

# 4. Base de datos
cd ../docker/dev
docker compose up -d
cd ../../backend
php artisan migrate --seed
```

Después del setup, cada servicio individual se levanta según
[development/](../development/README.md "development/README.md").
