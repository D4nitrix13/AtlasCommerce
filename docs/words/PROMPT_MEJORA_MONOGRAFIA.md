# Prompt — Mejora del Protocolo de Monografía

> **Cómo usar este prompt**: copialo COMPLETO (sin quitar ni una línea) y pegalo en
> el chat con la IA. La IA que lo reciba tendrá TODO el contexto que necesita para
> reescribir el documento: la estructura que exige el profesor, el contenido que ya
> redactaste, los errores concretos que hay que corregir y los datos técnicos reales
> del proyecto (para que NO invente módulos, marcas o tecnologías).

---

## 1. ROL Y OBJETIVO

Sos un **redactor técnico-académico senior** especializado en protocolos de
monografía de ingeniería en computación, con experiencia en la rúbrica y formato
de la UNI (Universidad Nacional de Ingeniería) — Nicaragua. Tu tarea es **reescribir
por completo** el documento `Avance 1` que te entrego al final, corrigiendo los
errores que listo en la sección 6 y respetando **al pie de la letra** la
estructura oficial del profesor (sección 4).

**Reglas duras**:

- No inventés datos técnicos del sistema. Todo lo que existe REALmente está en la
  sección 7; usalo como única fuente de verdad.
- No cambies el tema, los autores, el tutor ni el grupo.
- No inventes citas ni referencias bibliográficas. Si necesitás una, marcala
  como `[CITA PENDIENTE — buscar fuente real sobre X]` para que el alumno la
  complete después.
- Mantené el idioma **español** (el documento es para la UNI Managua).
  Términos técnicos van en inglés cuando es el uso estándar (API, framework,
  middleware, etc.).
- Respetá las **extensiones máximas** que indica el profesor (páginas por
  capítulo).
- Redactá en tercera persona académica, sin opiniones personales, sin
  coloquialismos, sin voseo, sin emojis.

---

## 2. DATOS DEL PROYECTO (entregados por el alumno — NO modificar)

| Campo                                             | Valor                                                                                                                                                                |
| ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Título (provisional, ajustar a ≤ 15 palabras)** | `Desarrollo e Implementación de un Sistema Web E-Commerce Multitienda para las empresas Panda Estampados y Kitsune, (en/de) Managua.` ⚠ Ver error E-01 en sección 6. |
| **Asignaturas**                                   | Ingeniería de Software I + Metodología de la Investigación (trabajo final unificado)                                                                                 |
| **Autores**                                       | Daniel Benjamín Pérez Morales; Andy Joe Sánchez Calero; Jeremy Joshua Pérez Gonzales                                                                                 |
| **Tutor**                                         | Ing. Araceli Torres López                                                                                                                                            |
| **Grupo**                                         | 4T6-COM-S                                                                                                                                                            |
| **Fecha**                                         | Agosto, 2026 (Managua, Nicaragua)                                                                                                                                    |
| **Planteamiento del problema (pregunta)**         | ¿Cómo desarrollar e implementar un sistema web e-commerce multitienda para las empresas Panda Estampados y Kitsune, que optimice el control operativo?               |

---

## 3. OBJETIVO GENERAL Y ESPECÍFICOS (entregados por el alumno — usar como base)

> Estos objetivos ya están redactados en el Avance 1. **Mantenelos** como base,
> pero mejorá su redacción si hay errores de sintaxis, gramática o coherencia.
> Si un verbo no está en infinitivo, corregilo.

**Objetivo general (Avance 1, p. 4)**:

> Identificar las características generales de la empresa Panda Estampados /
> Kitsune, su giro comercial, su forma de trabajo actual y la problemática
> existente, con el fin de establecer las necesidades que justifican el
> desarrollo de un E-Commerce multitienda; así como definir los fundamentos
> teóricos, la metodología de desarrollo, los lenguajes de programación, el
> gestor de base de datos y las herramientas de soporte que serán utilizadas
> como base para la construcción del sistema propuesto.

⚠ **Nota**: este objetivo general tiene ~80 palabras y mezcla dos verbos
(`identificar` + `definir`). El profesor exige **un solo verbo en infinitivo**
y un alcance claro. Sugerencia de mejora (NO la apliques sin razonarla):

> **Propuesta mejorada**:
> _Desarrollar e implementar un sistema web e-commerce multitienda para las
> empresas Panda Estampados y Kitsune, de Managua, que optimice el control
> operativo, centralice la información de catálogo, inventario y ventas, e
> integre una pasarela de pago en línea._

**Objetivos específicos (Avance 1, p. 4)** — son 6, el profesor pide 3–5.
Sugerencia: **consolidar** los 6 en 4–5 usando los verbos del profesor
(`diagnosticar → determinar → diseñar → desarrollar → evaluar`).

---

## 4. ESTRUCTURA OFICIAL DEL PROFESOR (NO TOCAR, SEGUIR AL PIE DE LA LETRA)

> Esta es la estructura que el profesor publicó en
> `PLANTILLA PROTOCOLO DE MONOGRAFÍA.docx`. **El documento final TIENE que
> tener estos capítulos en este orden, con estos nombres y estas extensiones
> máximas.** Donde la plantilla dice "Secciones internas", respetalas.

```
ÍNDICE
I. INTRODUCCIÓN                                  (máx. 2 páginas)
II. ANTECEDENTES                                 (máx. 4–5 páginas)
III. PLANTEAMIENTO DEL PROBLEMA                  (máx. 4 páginas)
IV. JUSTIFICACIÓN                                (máx. 2–3 páginas)
V. OBJETIVOS                                     (máx. 1 página)
    5.1 Objetivo General
    5.2 Objetivos Específicos (3 a 5, verbo infinitivo)
VI. MARCO TEÓRICO                                (máx. 4 páginas)
VII. METODOLOGÍA                                 (máx. 3 páginas)
VIII. CRONOGRAMA                                 (máx. 1 página)
REFERENCIAS                                      (formato APA 7ª ed.)
ANEXOS
```

### 4.1. ANTECEDENTES — subsecciones obligatorias

- 8 a 12 fuentes, del más reciente al más antiguo, preferiblemente últimos 5
  años (máx 10 años).
- Tipos: académicos (monografías, tesis, artículos), institucionales,
  históricos, perfiles de programas/proyectos.
- Hilo lógico por antecedente (siguiendo el ejemplo de la plantilla):
  _¿Quién investigó? → ¿Qué investigó? → ¿Cómo lo investigó? → ¿Qué encontró?
  → ¿Qué concluyó? → ¿Qué aporta a mi investigación? → ¿Qué queda pendiente?_

### 4.2. PLANTEAMIENTO DEL PROBLEMA — subsecciones obligatorias

1. Contexto del problema
2. Descripción de la situación problemática
3. Evidencias del problema (datos, observaciones, entrevistas, registros)
4. Causas posibles
5. Vacíos o necesidad de conocimiento
6. Formulación de la pregunta del problema

### 4.3. JUSTIFICACIÓN — subsecciones obligatorias

1. Importancia o relevancia del estudio
2. Conveniencia
3. Aporte práctico
4. Aporte tecnológico
5. Beneficiarios
6. Aporte metodológico
7. Aporte académico o de conocimiento
8. Viabilidad o factibilidad (puede ir integrado, no obligatorio como sección
   propia)

### 4.4. MARCO TEÓRICO — requisitos

- Mínimo **20 teorías, conceptos o definiciones** (preferiblemente más).
- Estructura sugerida: teorías generales → teorías específicas → conceptualizaciones.
- Uso intensivo de citas y referencias (APA 7).
- Temas mínimos a cubrir (no exhaustivo): sistemas de información, ingeniería
  de software, comercio electrónico, e-commerce multitienda, bases de datos
  relacionales y PostgreSQL, frameworks PHP y Laravel, frontend moderno y
  Astro/React, arquitectura multi-tenant, resolución por dominio,
  autenticación y autorización (2FA, OAuth, Sanctum), pasarelas de pago,
  contenedores y orquestación (Docker, Kubernetes), control de versiones y
  CI/CD, metodologías ágiles y Scrum, calidad del software y pruebas,
  arquitectura cliente-servidor, patrón MVC, ORM, caché y rendimiento
  (Redis), seguridad y cifrado (pgcrypto, HTTPS/TLS), UX/UI y SEO.

### 4.5. METODOLOGÍA — subsecciones obligatorias

1. **Tipo, enfoque y nivel** de la investigación
   (cualitativo/cuantitativo/mixto; longitudinal/transversal;
   descriptivo/exploratorio/explicativo/analítico/propositivo).
2. **Unidad de análisis u objeto de estudio** (en este caso: las empresas
   Panda Estampados y Kitsune, geo-referenciadas en Managua).
3. **Instrumentos y técnicas de investigación** (guías de observación,
   entrevistas, encuestas, fichas, levantamientos, etc.).
4. **Etapas del proceso investigativo**:
   - Preparación inicial (delimitar el tema, alcance, protocolo).
   - Trabajo de campo (in situ): artefacto ingenieril, aplicación de
     instrumentos, recolección de datos.
   - Análisis y procesamiento de la información (organización por capítulos).
   - Redacción de la monografía y presentación de resultados.

### 4.6. CRONOGRAMA — requisitos

- Tabla con 3 ejes: **Objetivos específicos × Actividad × Mes** (6 meses
  como máximo).
- Formato tipo Gantt simplificado, donde cada actividad tenga inicio, fin
  y contribuya a un objetivo específico.

### 4.7. REFERENCIAS — formato APA 7ª ed

- Orden alfabético por autor.
- Cada referencia: autor, año, título, fuente.
- Ejemplo del profesor:
  `Olgyay, V. (2015). Design with Climate: Bioclimatic Approach to Architectural Regionalism. Princeton University Press.`

### 4.8. ANEXOS — incluir al menos

- Guía de observación.
- Encuesta(s).
- Entrevista(s).
- Capturas o referencias del sistema (cuando aplique).

---

## 5. CONTENIDO YA REDACTADO POR EL ALUMNO (Avance 1) — reutilizar, mejorar

> Estos párrafos están en el documento entregado. Reutilizá el contenido
> técnico, pero **reescribí** la redacción para que sea académico, sin notas
> del alumno embebidas, sin coloquialismos, y respetando la estructura de la
> sección 4.

### 5.1. Texto extraído del Avance 1 (resumen por sección)

- **Introducción**: ya existe un párrafo introductorio (p. 1). Reusá la idea
  pero reescribila según las pautas de la sección I.
- **Marco Teórico — Metodología de Desarrollo** (p. 5–6): habla de
  metodologías tradicionales vs ágiles, justifica Scrum. Cita a Sommerville
  (2011), Pressman (2010) y Schwaber & Sutherland (2020). Mover a
  Metodología (cap. VII) y mantener citas.
- **Marco Teórico — Lenguajes de Programación** (p. 5–6): justifica PHP 8.4,
  TypeScript, JavaScript, Bash, SQL. Reubicar como teorías específicas en
  Marco Teórico.
- **Marco Teórico — Gestor de Base de Datos** (p. 6–7): PostgreSQL 18 +
  extensiones (pgcrypto, uuid-ossp, pg_trgm, citext, PostGIS) + Redis.
  Reubicar como teorías específicas en Marco Teórico.
- **Marco Teórico — Frameworks y herramientas** (p. 7–9): Laravel 13.8,
  React 19, Astro 7, Caddy, Nginx, Docker, Kubernetes, Composer, pnpm, Git,
  GitHub, pgAdmin, Redis Manager. Reubicar como teorías específicas.
- **Giro Comercial** (p. 10): descripción de Panda Estampados y Kitsune.
  Reubicar en Planteamiento del Problema → Contexto.
- **Forma de Trabajo Actual** (p. 10–11): 8 puntos sobre la operación
  manual. Reubicar en Planteamiento del Problema → Descripción.
- **Planteamiento del Problema** (p. 11–12): lista de 9 problemas. Mover a
  Planteamiento del Problema → Evidencias y Causas.
- **Especificación Técnica** (p. 13–14): arquitectura cliente-servidor,
  usuarios, módulos, delimitación. Reubicar en Marco Teórico (alcance) y
  en Justificación (aporte práctico/tecnológico).
- **Justificación de Metodología** (p. 15–16): 7 criterios por los que
  Scrum es adecuada. Reubicar en Metodología (cap. VII).
- **Etapa Actual** (p. 16): Sprint 0 / fase de planificación. Reubicar en
  Metodología → Etapas del proceso investigativo.

---

## 6. ERRORES Y DEFICIENCIAS DETECTADOS (corregir todos)

### 6.1. Errores estructurales (graves)

| ID       | Error                                                                                                                                                                                                                                                                                                                                                                                                                                         | Dónde                    | Corrección                                                                                                                                                                                                                             |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E-01** | Título dice `(en/de) Managua` — no se sabe si va "en" o "de", y supera 15 palabras.                                                                                                                                                                                                                                                                                                                                                           | Portada y §4.4           | Reescribir el título a ≤ 15 palabras. Ejemplo: `Desarrollo e implementación de un sistema web e-commerce multitienda para Panda Estampados y Kitsune, de Managua.` (15 palabras, preposición "de").                                    |
| **E-02** | El documento **no sigue la estructura del profesor**. Tiene: 1. Introducción, 2. Objetivos, 3. Marco Teórico, 4. Generalidades, 5. Implementación de Metodología. Le faltan: **II. Antecedentes, IV. Justificación, VIII. Cronograma, Referencias, Anexos**. Y los capítulos existentes están mal renombrados (III. Planteamiento del Problema está mezclado dentro de §4 "Generalidades"; VI. Marco Teórico está mezclado con herramientas). | Todo el documento        | Renumerar y reordenar TODOS los capítulos para que coincidan con la sección 4 de este prompt.                                                                                                                                          |
| **E-03** | El **objetivo general** mezcla dos verbos en una sola oración, excede la extensión recomendada y describe "actividades" en vez de un "propósito".                                                                                                                                                                                                                                                                                             | §2.1                     | Reescribir como UNA sola oración, con UN solo verbo en infinitivo, ≤ 35 palabras.                                                                                                                                                      |
| **E-04** | Los **objetivos específicos** son 6 y mezclan niveles (algunos son tareas administrativas, no objetivos).                                                                                                                                                                                                                                                                                                                                     | §2.2                     | Consolidar en 4–5 objetivos, todos con verbo en infinitivo, ≤ 2 líneas cada uno, en orden lógico: diagnosticar → determinar → diseñar → desarrollar → evaluar.                                                                         |
| **E-05** | **Falta el capítulo II. ANTECEDENTES** completo. Sin antecedentes no se puede defender la justificación ni la originalidad del proyecto.                                                                                                                                                                                                                                                                                                      | Después de Introducción  | Redactar 8–12 antecedentes (mitad internacionales, 2–3 nacionales/latinoamericanos, 1–2 institucionales). Usar el hilo lógico de la plantilla (¿Quién? ¿Qué? ¿Cómo? ¿Qué encontró? ¿Qué concluyó? ¿Qué aporta? ¿Qué queda pendiente?). |
| **E-06** | **Falta el capítulo IV. JUSTIFICACIÓN** completo. Está disfrazado dentro de §4 (Generalidades) mezclado con la descripción del problema.                                                                                                                                                                                                                                                                                                      | Después de Planteamiento | Redactar las 7–8 subsecciones obligatorias (Importancia, Conveniencia, Aporte práctico/tecnológico, Beneficiarios, Aporte metodológico/académico, Viabilidad).                                                                         |
| **E-07** | **Falta el capítulo VIII. CRONOGRAMA**.                                                                                                                                                                                                                                                                                                                                                                                                       | Antes de Referencias     | Construir tabla objetivos × actividades × 6 meses, en formato Gantt simplificado.                                                                                                                                                      |
| **E-08** | **Faltan las REFERENCIAS** en formato APA 7. Solo hay 3 citas sueltas en el cuerpo (Sommerville, Pressman, Schwaber).                                                                                                                                                                                                                                                                                                                         | Final del documento      | Listar TODAS las fuentes citadas en APA 7, ordenadas alfabéticamente. Marcar como `[CITA PENDIENTE]` las que no se puedan verificar.                                                                                                   |
| **E-09** | **Faltan los ANEXOS** (guías de observación, encuestas, entrevistas).                                                                                                                                                                                                                                                                                                                                                                         | Final del documento      | Incluir al menos: (a) guía de observación preliminar para trabajo de campo; (b) cuestionario de entrevista a los dueños; (c) capturas o wireframes si los hay.                                                                         |
| **E-10** | El **Marco Teórico** (§3) no cumple el mínimo de 20 teorías/conceptos y mezcla "metodología Scrum" (que va en cap. VII) con lenguajes, frameworks, infraestructura, etc.                                                                                                                                                                                                                                                                      | §3                       | Reorganizar en Marco Teórico puro (≥ 20 conceptos con citas) y mover "Scrum" a Metodología.                                                                                                                                            |

### 6.2. Errores de redacción / académicos

| ID       | Error                                                                                                                                                                         | Corrección                                                                                                                                                     |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| **E-11** | Hay **notas del alumno embebidas en el texto**: `Numero máximo de palabras (15)`, `O.general Max(35)                                                                          | o.especifico max(2 lineas)`, `(en/de)`.                                                                                                                        | Eliminar TODAS las notas personales / instrucciones que el alumno dejó dentro del cuerpo del documento. |
| **E-12** | Varios **numérales** del objetivo específico terminan con `x` (sin desarrollar el contenido).                                                                                 | O eliminarlos o completarlos con la actividad descrita.                                                                                                        |
| **E-13** | Falta de **conectores académicos** entre párrafos. Muchos párrafos arrancan con "Actualmente" o "Para" sin articular con lo anterior.                                         | Añadir conectores (en primer lugar, por otra parte, en consecuencia, así pues, etc.) y referencias cruzadas entre capítulos.                                   |
| **E-14** | **Siglas inconsistentes**: en algunos lados dice "Panda Estampados / Kitsune", en otros "Panda/Kitsune", en otros "ECM-PEK".                                                  | Normalizar: `Panda Estampados / Kitsune` (forma extendida en la primera mención), `ECM-PEK v1.0.0` (forma corta después).                                      |
| **E-15** | Uso de **mayúsculas innecesarias** (por ejemplo, "E-Commerce", "Multitienda", "Backend", "Frontend" en contextos donde no son nombres propios).                               | Mantener mayúsculas SOLO en nombres propios, marcas (Panda Estampados, Kitsune), acrónimos conocidos (API, MVC, SQL) y la primera palabra de la sigla ECM-PEK. |
| **E-16** | El texto tiene **sesgo publicitario** ("vitrina digital", "experiencia de compra del visitante", "vanguardia tecnológica") en un documento académico.                         | Reescribir en tono neutro, descriptivo, técnico. Evitar superlativos.                                                                                          |
| **E-17** | Falta de **citas explícitas** en afirmaciones técnicas (por ejemplo: "Scrum organiza el trabajo en sprints cortos generalmente de dos a cuatro semanas" — sin cita en línea). | Toda afirmación fáctica o doctrinal debe llevar `(Autor, año)` o `(Autor, año, p. X)` si se transcribe.                                                        |

### 6.3. Errores de contenido técnico

| ID       | Error                                                                                                                            | Corrección                                                                                                                                                                                    |
| -------- | -------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E-18** | El alumno dice que se desplegará en **Kubernetes**, pero el sistema actual se ejecuta en Docker Compose (ver §7 de este prompt). | Ajustar: el alcance del proyecto es Docker Compose en desarrollo y en primera producción; Kubernetes queda como **escala/proyección futura**, no como entrega del Avance 1.                   |
| **E-19** | El alumno lista **GitHub Actions** como pipeline CI/CD pero no menciona que es para qué fases (lint, tests, build, deploy).      | Mencionar GitHub Actions como práctica de DevOps (CI/CD) sin asegurar funcionalidades que el proyecto todavía no implementa. Si no hay workflows reales, marcar como "previsto".              |
| **E-20** | La sección "4.5 Especificación Técnica" repite lo ya dicho en Marco Teórico.                                                     | Consolidar: en Marco Teórico van los conceptos (qué es Laravel, qué es PostgreSQL, etc.); en Planteamiento/Justificación va el alcance propio del proyecto (qué módulos tendrá ESTE sistema). |
| **E-21** | "Etapa Actual del Proyecto" (§5.2) dice "Sprint 0 o fase de pre-proyecto" — en este protocolo todavía NO hay sprints ejecutados. | Reconocer que estamos en la **fase de protocolo / planificación**. Los sprints se describirán en capítulos posteriores cuando haya avances.                                                   |

---

## 7. INFORMACIÓN TÉCNICA REAL DEL PROYECTO (fuente de verdad — no inventar nada fuera de aquí)

> Esta es la realidad del sistema **AtlasCommerce** que el alumno tiene en
> el repositorio `/home/d4nitrix13/Code/AtlasCommerce/`. La IA tiene permitido
> referenciar esto en la monografía como descripción del proyecto **ya en
> desarrollo**, pero NO tiene permitido inventar módulos, endpoints o
> características que NO estén listados abajo.

### 7.1. Identidad del proyecto

- **Repositorio**: monorepo `/home/d4nitrix13/Code/AtlasCommerce/`.
- **Stack global**:
  - Backend: PHP 8.4, Laravel 13.8, Sanctum (autenticación).
  - Frontend: Astro 7, React 19, Tailwind CSS 4, TypeScript.
  - DB: PostgreSQL 18 + PostGIS.
  - Caché / colas: Redis 7.
  - Almacenamiento: volúmenes Docker (bind mount en dev).
  - Reverse proxy: Caddy (perimetral, TLS automático) + Nginx (interno).
  - Infraestructura: Docker Compose v2.
  - Package managers: Composer (PHP), pnpm 9 (Node).
  - Testing: PHPUnit 12 (backend), Vitest + Playwright (frontend).
- **Modelo de despliegue**: 4 tiendas como **instancias Docker independientes**
  que comparten código y configuración pero tienen DB, volúmenes y dominio
  propios.

### 7.2. Tiendas soportadas (multi-tienda)

| Tienda               | Dominio                | Tipo                               | Enfoque                                                              |
| -------------------- | ---------------------- | ---------------------------------- | -------------------------------------------------------------------- |
| **Panda Estampados** | `panda-estampados.com` | Multi-tenant (convive con Kitsune) | B2B / corporativo — estampados personalizados, regalos empresariales |
| **Kitsune**          | `kitsune.com`          | Multi-tenant (convive con Panda)   | B2C — cultura anime, manga, japonesa, videojuegos                    |
| **MM Boutique**      | `mm-boutique.com`      | Single-tenant                      | —                                                                    |
| **Morales Store**    | `morales-store.com`    | Single-tenant                      | —                                                                    |

> ⚠ Para la monografía, **solo interesan Panda Estampados y Kitsune** (las
> otras dos son casos secundarios que se mencionan solo para contextualizar el
> alcance multi-tenant del monorepo).

### 7.3. Arquitectura multi-tienda

El sistema implementa multi-tenancy con **dos capas de aislamiento**:

1. **Resolución por dominio (HTTP layer)** — Middleware
   `ResolveStoreFromDomain` que recibe el host del request
   (`panda-estampados.com`, `kitsune.com`, `admin.panda-estampados.com`,
   etc.), busca el `StoreDomain` activo en la tabla `store_domains`, y carga
   la entidad `Store` correspondiente en un singleton de request
   (`StoreContextService`).
2. **Aislamiento a nivel de base de datos (DB layer)** — Postgres **Row
   Level Security (RLS)**. Cada request setea una variable de sesión
   `app.current_store_id` mediante `RlsContextService::setStoreId()`,
   ejecutando `SELECT set_config('app.current_store_id', ?, false)`. Las
   policies RLS en cada tabla transaccional filtran automáticamente por esa
   variable. Esto bloquea fugas cross-tenant a nivel de query.

Tablas globales (sin `store_id`): `currencies`, `roles`, `permissions`,
`users`, `payment_methods`, `shipping_companies`, `tax_rules`.

Tablas con `store_id` (filtradas por RLS): `products`, `product_stores`,
`customers`, `customer_stores`, `orders`, `invoices`, `carts`, `wishlists`,
`coupons`, `promotions`, `product_reviews`, `product_questions`,
`support_tickets`, `notifications`, `audits`, `store_settings`,
`store_themes`, `store_features`, `store_social_links`, `sales_channels`.

Tablas pivote multi-tenant: `user_stores` (vincula usuario ↔ tienda ↔ rol),
`customer_stores` (vincula cliente ↔ tienda), `product_stores` (visibilidad,
precio público y descripción por tienda).

### 7.4. Módulos del sistema (organización por dominio de negocio)

| Módulo            | Alcance                                                                                                                         |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| **Auth**          | Usuarios, roles, permisos, autenticación (Sanctum), 2FA (email OTP, TOTP, passkeys), recuperación de contraseña, recovery codes |
| **Catálogo**      | Marcas, categorías, productos, variantes, atributos, imágenes                                                                   |
| **Inventario**    | Stock, movimientos, almacenes                                                                                                   |
| **Clientes**      | Registro, direcciones, historial de compras                                                                                     |
| **Ventas**        | Órdenes, facturas, historial de estados                                                                                         |
| **Pagos**         | Métodos de pago, transacciones, cuotas                                                                                          |
| **Compras**       | Órdenes de compra, proveedores, ítems                                                                                           |
| **Devoluciones**  | Solicitudes, reembolsos                                                                                                         |
| **Carrito**       | Carrito de compras, lista de deseos (wishlist)                                                                                  |
| **Envíos**        | Empresas de envío, zonas, seguimiento                                                                                           |
| **Marketing**     | Cupones, promociones                                                                                                            |
| **Impuestos**     | Reglas y tasas por tienda                                                                                                       |
| **Soporte**       | Tickets, reseñas, preguntas                                                                                                     |
| **Medios**        | Gestión de imágenes y archivos (Spatie MediaLibrary)                                                                            |
| **Configuración** | Ajustes generales por tienda                                                                                                    |
| **Tiendas**       | Multi-tienda, dominios, temas, features por tienda                                                                              |
| **Reportes**      | Dashboard, métricas, exportación a Excel                                                                                        |
| **Sistema**       | Auditoría, logs, respaldos, notificaciones internas                                                                             |

### 7.5. Roles de usuario previstos

- **Cliente externo (comprador)** — navega el catálogo público, crea cuenta,
  agrega productos al carrito, hace checkout y paga. Consulta el estado de
  sus pedidos.
- **Operador de tienda** — gestiona productos, categorías, inventario,
  pedidos y atención al cliente de una o ambas marcas.
- **Supervisor** — permisos intermedios: supervisa operaciones, consulta
  reportes y valida cierres.
- **Administrador** — acceso completo: configuración general, gestión de
  usuarios y roles, respaldos, restauración, auditoría, parámetros de la
  pasarela de pago y despliegues.

### 7.6. Módulos funcionales del storefront y panel

**Storefront (catálogo público)** — Astro 7 + React (islands), SEO-friendly:

- Catálogo multitienda con búsqueda, filtros y SEO optimizado.
- Cuenta de cliente, direcciones y autenticación (con 2FA).
- Carrito persistente y checkout paso a paso.
- Integración con pasarela de pago y gestión de estados de pago.
- Gestión de pedidos, estados, envíos y facturación (vista cliente).

**Panel administrativo** — Astro 7 + React sobre el subdominio `admin.*`:

- Gestión de productos, categorías, variantes, proveedores e inventario.
- Gestión de compras a proveedores y actualización de stock.
- Reportes administrativos y exportación a Excel.
- Seguridad, roles, auditoría, notificaciones y recuperación de contraseña.
- Configuración por tienda (tema, features, dominios).

### 7.7. Infraestructura y despliegue

- **Desarrollo local**: Docker Compose (`scripts/docker-stack.sh`) con
  servicios `backend` (Laravel + PHP 8.4 con Xdebug), `frontend` (Astro +
  pnpm), `postgres` (PostgreSQL 18 + PostGIS), `redis` (Redis 7), `ftp`
  (vsftpd), `pgadmin`. Puertos shifted +1000 para no colisionar con
  servicios del host.
- **Producción**: 3 instancias independientes (`panda-estampados`,
  `mm-boutique`, `morales-store`) — la instancia `panda-estampados` hospeda
  a las dos marcas (Panda + Kitsune) dentro de la misma plataforma.
  Caddy como reverse proxy perimetral con TLS automático (Let's Encrypt).
- **CI/CD**: GitHub Actions (previsto/según configure el equipo).
- **Versionado**: SemVer (Mayor.Menor.Parche) — `ECM-PEK v1.0.0` es la
  versión inicial.
- **Documentación interna**: `/docs/architecture/`, `/docs/operations/`,
  `/docs/deployment/`.

---

## 8. INSTRUCCIONES ESPECÍFICAS PARA LA IA

Cuando recibas este prompt, **tu entrega debe ser**:

1. **Un único archivo Markdown** llamado `PROTOCOLO_MONOGRAFIA_REESCRITO.md`
   que contenga el documento entero siguiendo la estructura de la sección 4
   de este prompt, con todos los capítulos numerados como indica el
   profesor.

2. **El documento completo debe caber en ≤ 22 páginas** (suma de las
   extensiones máximas de cada capítulo), escrito en interlineado 1.5 y
   letra Times New Roman / Arial 12 — salvo que el profesor indique otro
   formato, en cuyo caso usar el indicado.

3. **Cada capítulo debe respetar la extensión máxima** indicada en la
   sección 4. Si te pasás, recortá. Si te quedás corto, expandí con
   ejemplos, definiciones o citas relevantes.

4. **Tono y registro**: tercera persona, formal, descriptivo, técnico,
   sin coloquialismos ni sesgo publicitario. Conectores académicos entre
   párrafos.

5. **Citas**: usá formato APA 7 en línea `(Autor, año, p. X)`. Si una cita
   no se puede verificar, marcala con `[CITA PENDIENTE — verificar: <descripción>]`.

6. **Referencias**: lista final en formato APA 7, orden alfabético.
   Incluí todas las fuentes que cites en el cuerpo.

7. **No inventes**: módulos, endpoints, marcas, frameworks, porcentajes,
   cifras de mercado o testimonios. Si necesitás datos para llenar una
   sección, marcalos como `[DATO PENDIENTE — completar con trabajo de
campo]` y explicá cómo se obtendría.

8. **Al final**, agregá una sección `## Notas para el alumno` con:
   - Lista de las correcciones aplicadas (mapeo ID error → corrección).
   - Lista de huecos que el alumno debe completar antes de entregar
     (citas pendientes, datos pendientes, anexos por recolectar).
   - Recomendaciones de redacción puntuales.

---

## 9. FORMATO DE SALIDA (entregá esto, en este orden)

```markdown
# Protocolo de Monografía

## "Desarrollo e implementación de un sistema web e-commerce multitienda

## para Panda Estampados y Kitsune, de Managua"

**Autores**: Daniel Benjamín Pérez Morales; Andy Joe Sánchez Calero;
Jeremy Joshua Pérez Gonzales.
**Tutor**: Ing. Araceli Torres López.
**Grupo**: 4T6-COM-S.
**Fecha**: Agosto, 2026 — Managua, Nicaragua.

---

## I. INTRODUCCIÓN

[…]

## II. ANTECEDENTES

[…]

## III. PLANTEAMIENTO DEL PROBLEMA

### 3.1 Contexto del problema

[…]

### 3.2 Descripción de la situación problemática

[…]

### 3.3 Evidencias del problema

[…]

### 3.4 Causas posibles

[…]

### 3.5 Vacíos o necesidad de conocimiento

[…]

### 3.6 Formulación de la pregunta del problema

[…]

## IV. JUSTIFICACIÓN

### 4.1 Importancia o relevancia del estudio

[…]

### 4.2 Conveniencia

[…]

### 4.3 Aporte práctico

[…]

### 4.4 Aporte tecnológico

[…]

### 4.5 Beneficiarios

[…]

### 4.6 Aporte metodológico

[…]

### 4.7 Aporte académico o de conocimiento

[…]

### 4.8 Viabilidad o factibilidad

[…]

## V. OBJETIVOS

### 5.1 Objetivo General

[…]

### 5.2 Objetivos Específicos

1. […]
2. […]
3. […]
4. […]

## VI. MARCO TEÓRICO

### 6.1 Teorías generales

[…]

### 6.2 Teorías específicas

[…]

### 6.3 Conceptualizaciones

[…]

## VII. METODOLOGÍA

### 7.1 Tipo, enfoque y nivel de la investigación

[…]

### 7.2 Unidad de análisis u objeto de estudio

[…]

### 7.3 Instrumentos y técnicas de investigación

[…]

### 7.4 Etapas del proceso investigativo

[…]

## CRONOGRAMA (tabla 6 meses × objetivos × actividades)

[…]

## REFERENCIAS

[…]

## ANEXOS

[…]

## Notas para el alumno

- E-01 corregido: […]
- E-02 corregido: […]
- Huecos a completar: […]
- Recomendaciones: […]
```

---

## 10. CHECKLIST DE VALIDACIÓN (verificar antes de devolver)

Antes de entregar el documento, la IA tiene que revisar TODOS estos puntos:

- [ ] ¿El título final tiene ≤ 15 palabras y usa "de Managua" (no "en")?
- [ ] ¿El objetivo general tiene UN solo verbo en infinitivo y ≤ 35 palabras?
- [ ] ¿Hay entre 3 y 5 objetivos específicos, todos con verbo en infinitivo?
- [ ] ¿Hay al menos 8 antecedentes (idealmente 10–12)?
- [ ] ¿El planteamiento del problema tiene las 6 subsecciones obligatorias?
- [ ] ¿La justificación tiene al menos 6 de las 8 subsecciones obligatorias?
- [ ] ¿El marco teórico tiene ≥ 20 teorías/conceptos con cita APA 7?
- [ ] ¿La metodología declara tipo/enfoque/nivel explícitamente?
- [ ] ¿La metodología nombra instrumentos (encuesta/entrevista/observación)?
- [ ] ¿El cronograma cubre 6 meses con actividades vinculadas a objetivos?
- [ ] ¿Las referencias están en APA 7, orden alfabético, sin URLs rotas?
- [ ] ¿Los anexos incluyen al menos guía de observación y entrevista?
- [ ] ¿Se eliminaron las notas personales del alumno (`Numero máximo de
palabras (15)`, `O.general Max(35)`, `(en/de)`, las `x` sueltas)?
- [ ] ¿Se normalizaron las siglas (siempre "Panda Estampados / Kitsune" la
      primera vez, después "ECM-PEK")?
- [ ] ¿No se menciona Kubernetes como entregado, solo como proyección?
- [ ] ¿No se inventaron módulos, endpoints, marcas o cifras?
- [ ] ¿El tono es académico, sin coloquialismos ni sesgo publicitario?
- [ ] ¿Todas las afirmaciones fácticas llevan cita APA en línea?

---

## 11. RECORDATORIO FINAL

El objetivo de este prompt es que la IA produzca un documento **listo para
que el alumno lo revise, complete los huecos y entregue al profesor**. NO es
un documento terminado; es un **borrador académico de alta calidad** con la
estructura correcta, las correcciones aplicadas y los huecos marcados
explícitamente.

El alumno sigue siendo responsable de:

- Verificar las citas pendientes en fuentes reales.
- Recolectar los datos faltantes en el trabajo de campo.
- Completar los anexos (entrevistas, encuestas, capturas).
- Adaptar el cronograma a las fechas reales del curso.
- Validar el documento con el tutor (Ing. Araceli Torres López) antes de
  la entrega definitiva.
