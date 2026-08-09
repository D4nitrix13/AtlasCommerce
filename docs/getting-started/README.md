# Getting Started

Setup paso a paso del entorno de desarrollo. Cada archivo cubre una
pieza específica del sistema.

## Orden de instalación

```txt
1. prerequisites       Sistema (PHP, Node, Docker, Caddy)
2. installation        Clonar repo + instalar deps
3. databases           Levantar Postgres + Redis
4. backend-setup       Configurar Laravel (env, migrate, seed)
5. frontend-setup      Configurar Astro (env)
6. local-domains       Mapear hosts locales para multi-tienda
7. caddy               Configurar reverse proxy
```

## Vista rápida

```bash
# 1. Sistema (Ubuntu)
sudo apt install php8.3 composer nodejs pnpm docker.io caddy
sudo usermod -aG docker $USER

# 2. Clonar e instalar
git clone https://github.com/D4nitrix13/Ecommerce.git
cd Ecommerce
cd backend && composer install
cd ../frontend && pnpm install

# 3. Bases de datos
cd docker/dev
docker compose up -d
cd ../../backend
php artisan migrate --seed

# 4. Iniciar todo
cd ../backend && php artisan serve &
cd ../frontend && pnpm run dev &
sudo systemctl start caddy &
```

## Archivos de detalle

| Archivo                                              | Contenido                          |
| ---------------------------------------------------- | ---------------------------------- |
| [prerequisites.md](prerequisites.md "prerequisites.md") | Requisitos del sistema            |
| [installation.md](installation.md "installation.md")    | Clonar repo + instalar deps       |
| [databases.md](databases.md "databases.md")             | Levantar Postgres + Redis         |
| [backend-setup.md](backend-setup.md "backend-setup.md") | Configurar Laravel                |
| [frontend-setup.md](frontend-setup.md "frontend-setup.md") | Configurar Astro                |
| [local-domains.md](local-domains.md "local-domains.md") | Hosts locales multi-tienda        |
| [caddy.md](caddy.md "caddy.md")                         | Reverse proxy para dev            |
| [seed.md](seed.md "seed.md")                            | Datos iniciales                    |

## Verificación post-setup

```bash
# Backend health
curl -sI http://127.0.0.1:8000/up
# HTTP/1.1 200 OK

# Frontend
curl -sI http://127.0.0.1:4321/
# HTTP/1.1 302 Found (redirect a /es)

# Caddy
curl -sI http://panda-estampados.com/login
# HTTP/1.1 200 OK

# API auth
curl -s -X POST http://panda-estampados.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@panda-estampados.test","password":"password"}'
# {"token":"...","user":{...}}
```

## Próximos pasos

- [development/](../development/README.md "development/README.md")
  — Workflow de desarrollo diario.
- [architecture/](../architecture/overview.md "architecture/overview.md")
  — Cómo está armado el sistema.
