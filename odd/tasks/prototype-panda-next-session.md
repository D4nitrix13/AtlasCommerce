# Siguiente sesión — prototipo Panda (cliente)

Fecha: 2026-11-02. El pipeline de catálogo (`is_hover`, E1.5) sigue en pausa
en el working tree. No mezclarlo con este trabajo.

## 0. Por qué `https://panda-estampados.com/` no abre

Medido en esta máquina:

| Hecho | Estado |
|---|---|
| `caddy.service` | inactive |
| Puertos 80 y 443 | nadie escucha |
| `/etc/caddy/Caddyfile` | el de bienvenida del paquete (39 líneas, `file_server` de `/usr/share/caddy`) |
| Caddyfile del repo | `docker/caddy/Caddyfile` (no hay `Caddyfile` en la raíz) |
| `/etc/hosts` | no tiene `panda-estampados.com`. `kitsune.com` resuelve a internet, no a `127.0.0.1` |
| Frontend dev | `127.0.0.1:5321` responde 200 |
| Backend `/up` | `127.0.0.1:9000/up` responde 200 |
| TLS | el Caddyfile del repo es `http://` con `auto_https off`. No hay listener :443 |

`sudo` pide contraseña, así que la instalación queda para Daniel:

```bash
sudo cp docker/caddy/Caddyfile /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl restart caddy
```

Y al final de `/etc/hosts`:

```text
127.0.0.1  panda-estampados.com www.panda-estampados.com admin.panda-estampados.com
127.0.0.1  kitsune.com www.kitsune.com admin.kitsune.com
```

La URL local que va a funcionar es **`http://panda-estampados.com`**.
`https://` no tiene certificado ni puerto 443. Si el prototipo tiene que
verse con candado, la sesión siguiente agrega un sitio `https://` con
`tls internal` y se instala la CA local de Caddy en el navegador. No
activar `auto_https on`: Let's Encrypt no emite certs para un dominio
que apunta a loopback.

## 1. Caddy: host, no dentro del compose de una tienda

Decisión para este repo: **Caddy corre en el host** (`systemd`), y el
archivo fuente es `docker/caddy/Caddyfile`.

Por qué no meterlo en el `docker-compose` de Panda:

- Tiene que ocupar el :80/:443 del host y mandar a varias instancias
  (Panda+Kitsune, MM, Morales, La Voz). Un compose de una sola tienda
  no ve las redes de las otras.
- En dev el frontend ya publica `localhost:5321`. Caddy en el host
  enruta el nombre hacia ese puerto. Los handles `@panda_dev` van
  antes que los de producción y ganan si ambos matchean el mismo host.

Qué no hacer: volver a copiar el Caddyfile a la raíz solo por el doc
viejo. El doc (`docs/getting-started/caddy.md`) todavía dice
`./Caddyfile` en la raíz. La próxima sesión corrige ese puntero para
que apunte a `docker/caddy/Caddyfile`.

## 2. Qué se ve hoy en Panda

Revisado en `http://127.0.0.1:5321` (sin host, la marca por defecto es
Panda).

| Vista | Estado visual |
|---|---|
| Home | Presentable. Titular Fraunces, papel, franja teal, flujo Cotizá → Entrega. |
| Catálogo `/es/products` | Roto para el prototipo. **0 productos**, columna derecha vacía. Filtros de Fender/Gibson junto a tallas CH–XG: mezcla instrumentos con ropa. |
| Carrito y checkout | Componen bien, pero los ítems son figuras de anime del mock (`data/products.ts`), no prendas de Panda. |
| PDP | Depende del slug del API. Con el catálogo en cero no hay ficha que enseñar. |
| RFQ, volumen, confirmación | La confirmación ya es un ticket. RFQ y volumen hay que mirarlos con productos reales al lado. |
| Login / registro | Shell de auth existente. No es el hueco. |
| Cuenta y pedidos | Siguen detrás del login. No se rediseñan hasta poder verlos con sesión. |

Las rutas del prototipo ya existen. Lo que falta no es otra página:
es **un solo catálogo de Panda** que home, grilla, ficha, carrito y
checkout lean igual.

## 3. Productos del prototipo Panda

Ocho piezas, todas de estampado. Nada de guitarras ni figuras en este
tenant. Dos acabados como máximo, para que el swatch tenga sentido.

| Pieza | Acabados | Precio ref. |
|---|---|---|
| Polo piqué | negro, crema | 24 USD |
| Playera | negro, blanco | 18 USD |
| Hoodie | marino, gris | 42 USD |
| Taza 11 oz | blanco, negro | 12 USD |
| Tote | crudo, negro | 15 USD |
| Gorra | negro, marino | 16 USD |
| Bolígrafo | negro | 4 USD |
| Libreta | kraft | 9 USD |

Fotos: los mockups de prenda que ya existen (`ProductMockup`) o fotos
reales si hay assets de Panda. Una foto de portada por pieza. Sin
portada, la card no entra a la grilla.

Filtros del catálogo Panda, y solo esos: categoría (textil, drinkware,
accesorio), color, precio. Marcas Fender/Gibson salen de esta tienda.

Kitsune, en otra pasada: figuras y manga, solo cuando el host es
`kitsune.com`. El carrito deja de leer `data/products.ts` fijo.

## 4. Orden de la próxima sesión

Hecho en la sesión del 2026-11-02 (tarde):

2. Módulo `frontend/src/data/panda-catalog.ts` (8 piezas).
3. Catálogo con foto, nombre, precio y color. Filtros de textil / drinkware / accesorio, color y precio. Sin marcas ni tallas. Vacío con mensaje.
4. Ficha `PandaProductDetail`: foto, color, cantidad, enlace al carrito y nota de volumen.
5. Carrito y checkout muestran polo (12) y taza (24). Siguen siendo demo fijo, no un carrito que acumule.
7. El doc de Caddy apunta a `docker/caddy/Caddyfile`.

Sigue pendiente:

1. Confirmar `http://panda-estampados.com` con el Caddy del host (`/etc/hosts` no se tocó).

Hecho en la continuación: volumen, cotización y ticket usan las mismas ocho piezas. La calculadora arma el enlace a la cotización con producto y cantidad. 50 tazas salen en USD 528 (12% de descuento). El ticket muestra «Taza 11 oz · 50». En 390px el volumen no desborda.

Los filtros del catálogo se ven, pero todavía no recortan la grilla. El carrito sigue siendo demo fijo.

Fuera de esta sesión: admin, búsqueda, wishlist, 2FA, HTTPS con CA
local. E1.5 sigue en pausa.
