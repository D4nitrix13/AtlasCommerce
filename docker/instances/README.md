# Instancias del sistema Multi-Instance

Cada carpeta es un **deploy independiente** del sistema. Comparten la
misma base de código (este repo) pero tienen:

- BD propia (Postgres aislado).
- Redis propio.
- Caddy con sus dominios.
- Tenants declarados en su propio `seeds/*.json`.

## Instancias actuales

| Instancia | Tiendas | Dominios | Archivo de seed |
| --------- | ------- | -------- | ---------------- |
| `panda-estampados-and-kitsune/` | Panda + Kitsune | `panda-estampados.com`, `kitsune.com` | `seeds/panda.json` |
| `mm-boutique/` | MM Boutique | `mm-boutique.com` | `seeds/mm-boutique.json` |
| `morales-store/` | Morales Store | `morales-store.com` | `seeds/morales-store.json` |

## Estructura de cada instancia

```txt
<instancia>/
├── .env.example        # Variables de entorno (template)
├── Caddyfile            # Reverse proxy con dominios
├── docker-compose.yml   # 5 servicios: app, frontend, caddy, db, redis
├── seeds/
│   └── *.json           # Config de tenants para tenants:bootstrap
└── README.md (este archivo es el de la raíz, no se repite)
```

## Deploy de una instancia

```bash
# 1. Clonar el repo en el servidor
git clone https://github.com/D4nitrix13/AtlasCommerce.git
cd AtlasCommerce

# 2. Configurar la instancia
cd docker/instances/mm-boutique
cp .env.example .env
# Editar .env: cambiar passwords, dominios, APP_KEY

# 3. Levantar
docker compose up -d --build

# 4. Verificar
docker compose ps
docker compose logs -f app
```

El primer arranque ejecuta automáticamente (via entrypoint):

- Espera a que Postgres esté listo.
- Genera `APP_KEY` si no está en `.env`.
- Corre migraciones.
- Corre seeders (currencies + permissions).
- Corre `tenants:bootstrap` con el JSON de `seeds/`.
- Arranca PHP-FPM.

## Agregar una instancia nueva

1. Crear la carpeta: `mkdir -p docker/instances/<nombre>/seeds`
2. Copiar `.env.example` y `docker-compose.yml` de otra instancia.
3. Copiar el `Caddyfile` y ajustar los dominios.
4. Crear `seeds/<nombre>.json` con la config de los tenants.
5. Editar `.env` con passwords y nombres únicos.
6. Documentar en este README.

## Configurar DNS

Para cada dominio en producción, configurar registros A que
apunten al servidor de la instancia:

```dns
A   mm-boutique.com          <ip-servidor-mm-boutique>
A   www.mm-boutique.com      <ip-servidor-mm-boutique>
A   admin.mm-boutique.com    <ip-servidor-mm-boutique>
```

En Caddy el `auto_https` se activa por defecto y obtiene
certificados Let's Encrypt automáticamente. Para dev local
(`auto_https off`), usar `/etc/hosts` para mapear.

## Actualizar

```bash
ssh usuario@servidor
cd AtlasCommerce
git pull
cd docker/instances/<instancia>
docker compose pull
docker compose up -d --build
```

## Backups

Cada instancia tiene su propio volumen `db-data`. Configurar cron
o servicio de backup con snapshot de ese volumen + push a S3.

## Multi-tenancy dentro de una instancia

Es posible tener 2+ tiendas en la misma instancia. Por ejemplo
`panda-estampados-and-kitsune/` tiene Panda + Kitsune juntas porque son del mismo
cliente. El JSON de seed declara ambos tenants:

```json
{
  "tenants": [
    { "slug": "panda-estampados", "domains": ["panda-estampados.com"] },
    { "slug": "kitsune", "domains": ["kitsune.com"] }
  ],
  "admin_user": { "email": "...", "password_env": "..." }
}
```

Caddyfile lista ambos sets de dominios. La BD es compartida.
