# Despliegue

## Pasos de despliegue en VPS

### 1. Preparar el VPS

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Instalar Docker Compose
sudo apt install docker-compose-plugin -y

# Instalar Git
sudo apt install git -y
```

### 2. Clonar el repositorio

```bash
cd /var/www
git clone <repo-url> ecommerce
cd ecommerce
```

### 3. Configurar variables de entorno

```bash
# Backend
cp backend/.env.example backend/.env
nano backend/.env

# Frontend
cp frontend/.env.example frontend/.env
nano frontend/.env

# Docker
cp docker/dev/.env.example docker/dev/.env
nano docker/dev/.env
```

### 4. Configurar DNS

Configurar los registros DNS en el proveedor
de dominios:

- **A `@`** — IP del VPS
- **A `www`** — IP del VPS
- **A `api`** — IP del VPS
- **A `admin`** — IP del VPS
- **A `kitsune`** — IP del VPS

### 5. Iniciar servicios

```bash
docker compose -f docker-compose.dev.yml up -d
```

### 6. Ejecutar migraciones

```bash
docker compose exec backend php artisan migrate --force
```

### 7. Sembrar datos iniciales (opcional)

```bash
docker compose exec backend php artisan db:seed --force
```

### 8. Build del frontend

```bash
docker compose exec frontend pnpm install
docker compose exec frontend pnpm build
```

### 9. Configurar `Caddy`

```bash
# Crear Caddyfile
nano docker/Caddyfile

# Reiniciar Caddy
docker compose restart caddy
```

### 10. Verificar

```bash
# Verificar que todos los servicios estén corriendo
docker compose ps

# Verificar logs
docker compose logs -f

# Verificar API
curl -I https://api.pandas-estampados.com/api/products

# Verificar storefront
curl -I https://pandas-estampados.com
curl -I https://kitsune.com

# Verificar admin
curl -I https://admin.pandas-estampados.com
```

## Despliegue de actualizaciones

```bash
# Pull de cambios
cd /var/www/ecommerce
git pull

# Migraciones
docker compose exec backend php artisan migrate --force

# Build del frontend
docker compose exec frontend pnpm build

# Reiniciar servicios
docker compose restart backend frontend
```

## Verificación post-despliegue

- **Servicios Docker** — `docker compose ps`
- **Logs sin errores** —
  `docker compose logs --tail=50`
- **API responde** —
  `curl https://api.pandas-estampados.com/api/products`
- **TLS funciona** —
  `curl -I https://pandas-estampados.com`
- **Admin accesible** — Abrir
  `admin.pandas-estampados.com`
