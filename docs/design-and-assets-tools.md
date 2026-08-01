# Herramientas de Diseño y Recursos Visuales

Este documento resume herramientas útiles para diseño visual, optimización de
imágenes e integración de iconos en proyectos web.

Está pensado para proyectos de ecommerce, dashboards administrativos,
landing pages y plantillas de correo electrónico.

## Herramientas incluidas

### Dribbble

Referencia visual para diseños de interfaz y plantillas de correo.

Uso recomendado:

- Inspiración para correos transaccionales.
- Inspiración para correos promocionales.
- Referencias visuales para interfaces mobile-first.

Enlace:

```txt
https://dribbble.com/shots/26188641-Responsive-Email-Template-Scalable-Mobile-First-Design
```

### Flaticon

Plataforma para descargar iconos, stickers y recursos vectoriales.

Uso recomendado:

- Iconos decorativos.
- Iconos de categorías.
- Ilustraciones para estados vacíos.
- Recursos visuales para landing pages.

Enlace:

```txt
https://www.flaticon.es
```

### Squoosh

Herramienta web para comprimir y optimizar imágenes.

Uso recomendado:

- Imágenes de productos.
- Banners.
- Miniaturas.
- Imágenes para correos.
- Imágenes de categorías.

Enlace:

```txt
https://squoosh.app
```

### Heroicons

Librería de iconos SVG creada por los desarrolladores de Tailwind CSS.

Uso recomendado:

- Iconos de interfaz.
- Menús de dashboard.
- Botones.
- Formularios.
- Estados activos.
- Alertas.

Enlace:

```txt
https://heroicons.com
```

---

## Dribbble como referencia de diseño

Dribbble sirve como fuente de inspiración visual. En este caso, la referencia se
usa para analizar una plantilla de correo electrónico responsiva y mobile-first.

No se debe copiar el diseño directamente, a menos que la licencia o el autor lo
permitan. Lo correcto es tomar la estructura visual como guía y adaptarla a la
identidad del proyecto.

### Uso de Dribbble en el proyecto

Puedes usar esta referencia para diseñar correos como:

- Verificación de cuenta.
- Restablecimiento de contraseña.
- Confirmación de pedido.
- Confirmación de pago.
- Estado de envío.
- Promociones de la tienda.

### Buenas prácticas con Dribbble

- Usar la referencia solo como inspiración.
- Adaptar colores y tipografía a la marca.
- Mantener una estructura simple.
- Probar el diseño en escritorio y móvil.
- Evitar copiar recursos visuales sin revisar la licencia.

---

## Flaticon para recursos visuales

Flaticon permite descargar iconos y recursos gráficos en varios formatos. Es útil
cuando el proyecto necesita elementos más decorativos que los iconos básicos de
interfaz.

### Uso de Flaticon en el proyecto

Se recomienda usar Flaticon para:

- Categorías de productos.
- Ilustraciones de estados vacíos.
- Secciones informativas.
- Elementos decorativos.
- Recursos visuales para landing pages.

### Formatos de Flaticon

Formatos recomendados:

- SVG para iconos escalables.
- PNG para imágenes estáticas.
- EPS para edición en herramientas de diseño.
- CSS cuando se usen fuentes de iconos.

### Buenas prácticas con Flaticon

- Revisar siempre la licencia.
- Usar SVG cuando sea posible.
- No mezclar demasiados estilos visuales.
- Renombrar los archivos con nombres claros.
- Optimizar los recursos antes de subirlos al proyecto.

Ejemplos de nombres correctos:

```txt
shopping-cart.svg
delivery-truck.svg
credit-card.svg
user-profile.svg
product-category-shirt.svg
```

---

## Squoosh para optimizar imágenes

Squoosh se usa para reducir el peso de las imágenes sin perder demasiada calidad
visual. Esto es importante en ecommerce porque las imágenes influyen mucho en la
velocidad de carga.

### Uso de Squoosh en el proyecto

Se recomienda optimizar imágenes antes de agregarlas al repositorio.

Flujo recomendado:

1. Descargar o exportar la imagen original.
2. Abrir la imagen en Squoosh.
3. Redimensionarla si es demasiado grande.
4. Comprimirla en un formato moderno.
5. Comparar la imagen original con la optimizada.
6. Guardar el archivo final.
7. Usar la imagen optimizada en el proyecto.

### Formatos recomendados en Squoosh

Formatos útiles:

- WebP para imágenes generales de ecommerce.
- AVIF cuando se busca mayor compresión.
- PNG cuando se necesita transparencia.
- JPEG para fotografías con alta compatibilidad.

### Tamaños sugeridos

Tamaños recomendados:

- Miniatura de producto: `400x400` o `600x600`.
- Detalle de producto: `800x800` o `1200x1200`.
- Banner principal: `1600x600` o similar.
- Imagen de blog: `1200x700`.
- Imagen de correo: lo más pequeña posible.

### Buenas prácticas con Squoosh

- No subir imágenes enormes al proyecto.
- Preferir WebP para productos.
- Usar nombres descriptivos.
- Comprimir imágenes antes de hacer commit.
- Revisar la calidad visual después de comprimir.

Ejemplos de nombres correctos:

```txt
product-shirt-black.webp
banner-summer-sale.webp
category-mugs.webp
email-password-reset-header.webp
```

---

## Heroicons para iconos de interfaz

Heroicons es recomendable para iconos funcionales de interfaz. Combina muy bien
con Tailwind CSS y permite mantener un diseño limpio y consistente.

### Uso de Heroicons en el proyecto

Se recomienda usar Heroicons para:

- Sidebar del dashboard.
- Botones de acción.
- Formularios.
- Navegación.
- Alertas.
- Configuración.
- Perfil de usuario.
- Panel administrativo.

### Estilos de Heroicons

Heroicons ofrece varios estilos:

- Outline para navegación e interfaz general.
- Solid para estados activos o acciones importantes.
- Mini para interfaces compactas.
- Micro para elementos pequeños.

### Ejemplo de uso en Astro o HTML

```html
<button class="inline-flex items-center gap-2 rounded-lg px-4 py-2">
  <svg
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    class="size-5"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M2.25 3h1.386c.51 0 .955.343 1.087.835"
    />
  </svg>

  Agregar al carrito
</button>
```

### Buenas prácticas con Heroicons

- Usar `currentColor` para heredar el color del texto.
- Mantener tamaños consistentes.
- Usar `aria-hidden="true"` en iconos decorativos.
- Agregar texto accesible cuando el icono represente una acción.
- No mezclar Heroicons con muchos estilos de iconos diferentes.

---

## Flujo recomendado

Orden sugerido de uso:

1. Usar Dribbble como inspiración visual.
2. Usar Heroicons para iconos funcionales de interfaz.
3. Usar Flaticon para recursos decorativos o categorías.
4. Usar Squoosh para optimizar imágenes antes del commit.

---

## Casos de uso en ecommerce

### Dashboard administrativo

Usar Heroicons para:

- Dashboard.
- Productos.
- Pedidos.
- Clientes.
- Inventario.
- Configuración.
- Reportes.

Usar Flaticon solo cuando se necesite un recurso más ilustrativo.

### Catálogo de productos

Usar Squoosh para optimizar imágenes de productos.

Usar Flaticon para representar categorías como:

- Camisetas.
- Tazas.
- Bolsos.
- Pósters.
- Accesorios.

### Correos transaccionales

Usar Dribbble como referencia visual.

Usar Squoosh para optimizar imágenes de encabezado o banners.

Usar iconos simples solo si son compatibles con clientes de correo.

### Landing page

Usar Flaticon para secciones visuales.

Usar Squoosh para reducir el peso de imágenes.

Usar Heroicons para detalles de interfaz, beneficios y llamadas a la acción.

---

## Convención para nombres de archivos

Usar nombres en inglés, en minúsculas y separados por guiones.

Nombres correctos:

```txt
shopping-cart.svg
product-shirt.webp
email-reset-password-banner.webp
delivery-truck.svg
customer-support.svg
```

Nombres a evitar:

```txt
Icono final nuevo.svg
imagen1.png
descarga (3).svg
foto bonita.jpg
```

---

## Estructura de carpetas sugerida

```txt
public/
└── assets/
    ├── icons/
    │   ├── heroicons/
    │   ├── flaticon/
    │   ├── categories/
    │   └── dashboard/
    ├── images/
    │   ├── products/
    │   ├── banners/
    │   ├── emails/
    │   └── thumbnails/
    └── illustrations/
```

---

## Recomendaciones finales

Usa Heroicons como sistema principal de iconos para la interfaz. Usa Flaticon
solo para recursos decorativos o iconos específicos de categorías.

Optimiza las imágenes con Squoosh antes de subirlas al repositorio. Usa Dribbble
como referencia visual, no como fuente para copiar diseños completos.

Antes de usar recursos externos en producción, revisa siempre sus licencias.
