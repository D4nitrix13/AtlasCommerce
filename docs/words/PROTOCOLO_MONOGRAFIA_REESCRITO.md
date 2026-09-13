<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 MD033 -->

# Protocolo de Monografía

## "Desarrollo e implementación de sistema web e-commerce multitienda para Panda Estampados y Kitsune, de Managua"

**Autores**: Br. Daniel Benjamín Pérez Morales; Br. Andy Joe Sánchez Calero; Br. Jeremy Joshua Pérez Gonzales.

**Tutor**: Ing. Araceli Torres López.

**Grupo**: 4T6-COM-S.

**Asignaturas**: Ingeniería de Software I — Metodología de la Investigación (trabajo final unificado).

**Fecha**: Agosto, 2026 — Managua, Nicaragua.

**Versión del documento**: Protocolo — Avance 1 (1.0.0).

---

## ÍNDICE

| Capítulo | Título                                              | Extensión máxima |
| -------- | --------------------------------------------------- | ---------------- |
| I.       | Introducción                                        | 2 páginas        |
| II.      | Antecedentes                                        | 4–5 páginas      |
| III.     | Planteamiento del problema                          | 4 páginas        |
| IV.      | Justificación                                       | 2–3 páginas      |
| V.       | Objetivos                                           | 1 página         |
| VI.      | Marco teórico                                       | 4 páginas        |
| VII.     | Metodología                                         | 3 páginas        |
| VIII.    | Cronograma                                          | 1 página         |
| —        | Referencias                                         | s/p              |
| —        | Anexos                                              | s/p              |

---

## I. INTRODUCCIÓN

El presente documento constituye el protocolo de monografía del trabajo final unificado de las asignaturas Ingeniería de Software I y Metodología de la Investigación, desarrollado en la Universidad Nacional de Ingeniería (UNI), sede Managua. El estudio se propone desarrollar e implementar un sistema web de comercio electrónico multitienda (en adelante, *ECM-PEK v1.0.0*) destinado a las empresas **Panda Estampados** y **Kitsune**, dos marcas nicaragüenses que en la actualidad operan de forma simultánea bajo un modelo manual de gestión administrativa y comercial, sin un canal formal de venta en línea.

La investigación se justifica en la necesidad observada de centralizar la información de catálogo, inventario, clientes, ventas y pagos en una única plataforma, sustituir el uso de hojas de cálculo y aplicaciones de mensajería por un sistema transaccional, y habilitar una pasarela de pago que permita aceptar medios electrónicos. Desde el punto de vista académico, el estudio se inscribe en el campo de la ingeniería de software aplicada y aporta al área de **comercio electrónico multitienda** —también conocido como *multi-tenant e-commerce*— un caso de implementación concreto sobre la base de tecnologías de código abierto, contenedores y arquitectura cliente-servidor.

El protocolo se organiza en ocho capítulos. El **capítulo I** introduce el tema y la estructura del documento. El **capítulo II** presenta los antecedentes nacionales e internacionales que contextualizan la investigación. El **capítulo III** describe el planteamiento del problema, sus causas y la pregunta de investigación. El **capítulo IV** expone la justificación del estudio en sus dimensiones práctica, tecnológica, metodológica y social. El **capítulo V** formula el objetivo general y los objetivos específicos. El **capítulo VI** desarrolla el marco teórico con las teorías, conceptos y definiciones necesarios para sustentar el trabajo. El **capítulo VII** describe la metodología de la investigación, los instrumentos y las etapas del proceso investigativo. Finalmente, el **capítulo VIII** muestra el cronograma de actividades en un horizonte de seis meses. Cierran el documento las referencias bibliográficas en formato APA 7ª edición y los anexos con los instrumentos de recolección de información.

> *Extensión máxima: 2 páginas.*

---

## II. ANTECEDENTES

Esta sección presenta los estudios previos y las fuentes documentales que sirven de soporte a la presente investigación. Los antecedentes se organizan del más reciente al más antiguo y se redactan siguiendo el hilo lógico sugerido por la plantilla institucional: *¿Quién investigó? → ¿Qué investigó? → ¿Cómo lo investigó? → ¿Qué encontró? → ¿Qué concluyó? → ¿Qué aporta a esta investigación? → ¿Qué queda pendiente?*. Las fuentes se agrupan en cuatro tipos: (a) académicos —monografías, tesis y artículos científicos—; (b) institucionales —perfiles de proyectos y documentación oficial de frameworks y herramientas—; (c) históricos —clásicos de la ingeniería de software—; y (d) perfiles de programas o proyectos.

### 2.1. Antecedente 1 — Schwaber y Sutherland (2020), *The Scrum Guide*

Sutherland y Schwaber (2020) —creadores de Scrum— publicaron la guía oficial del marco en su decimoprimera revisión. Su investigación documentó la estructura, los roles, los eventos y los artefactos de Scrum, a partir de más de dos décadas de aplicación industrial y de un proceso iterativo de revisión por la comunidad ágil internacional. Los autores encontraron que Scrum reduce el tiempo de entrega, mejora la predictibilidad y aumenta la satisfacción del equipo cuando se aplica de forma disciplinada. Concluyen que Scrum es un marco ligero que ayuda a los equipos a generar valor frente a problemas complejos. *Aporta a la presente investigación* el marco conceptual que sustenta la selección de Scrum como metodología del ECM-PEK v1.0.0, en particular la noción de sprints cortos, *Product Backlog* y *Sprint Review* con el cliente real. *Queda pendiente* la definición concreta del *Product Backlog* inicial del proyecto, que se desarrollará en los próximos avances.

### 2.2. Antecedente 2 — Sommerville (2011), *Ingeniería de software*

Sommerville (2011), en la novena edición de *Software Engineering*, realizó una investigación documental y comparativa sobre los procesos, métodos y herramientas de la ingeniería de software, con énfasis en modelos ágiles y orientados a la calidad. Encontró que las metodologías ágiles —Scrum, XP, Kanban— son más adecuadas que los modelos predictivos para proyectos con requisitos cambiantes y alta interacción con el usuario. Concluye que la selección de un proceso de software debe considerar las características del proyecto, del equipo y del cliente. *Aporta a la presente investigación* la base teórica que justifica el uso de Scrum en proyectos web iterativos como el ECM-PEK v1.0.0. *Queda pendiente* documentar en la monografía final los indicadores de éxito de la aplicación de Scrum.

### 2.3. Antecedente 3 — Pressman (2010), *Ingeniería de software: un enfoque práctico*

Pressman (2010), en la séptima edición de *Software Engineering: A Practitioner's Approach*, investigó los métodos, las herramientas y los marcos para el desarrollo de software de calidad, con énfasis en el ciclo de vida, las pruebas, la gestión de proyectos y la adopción de modelos ágiles. Encontró que las prácticas de ingeniería de software combinadas con marcos ágiles producen sistemas más confiables y mantenibles. Concluye que el ingeniero debe conocer tanto los procesos tradicionales como los ágiles y seleccionar la combinación más adecuada. *Aporta a la presente investigación* el sustento bibliográfico del apartado de metodología de desarrollo y la justificación de Scrum. *Queda pendiente* profundizar en las prácticas de aseguramiento de la calidad que se aplicarán en los sprints.

### 2.4. Antecedente 4 — Fielding (2000), *Architectural Styles and the Design of Network-Based Software Architectures*

Fielding (2000), en su tesis doctoral en la Universidad de California en Irvine, definió formalmente el estilo arquitectónico **REST** (*Representational State Transfer*) como un modelo para sistemas distribuidos hipermedia. Su investigación analizó los principios que subyacen a la World Wide Web y formuló restricciones arquitectónicas —interfaz uniforme, sin estado, almacenamiento en caché y sistema en capas—. Encontró que REST permite construir APIs escalables, independientes de la plataforma y desacopladas del cliente. Concluye que REST es la base de la mayoría de las aplicaciones web modernas. *Aporta a la presente investigación* el marco conceptual sobre el cual se construirá la API REST del ECM-PEK v1.0.0, que será consumida por el *storefront* (Astro + React) y por el panel administrativo. *Queda pendiente* documentar el catálogo de *endpoints* en un avance posterior.

### 2.5. Antecedente 5 — Fowler (2002), *Patterns of Enterprise Application Architecture*

Fowler (2002), en *Patterns of Enterprise Application Architecture*, investigó los patrones recurrentes en el diseño de aplicaciones empresariales, entre ellos el patrón **Modelo–Vista–Controlador (MVC)**, el mapeo objeto-relacional (ORM), el patrón repositorio y la separación de capas. Encontró que la aplicación sistemática de patrones mejora la mantenibilidad, la testabilidad y la legibilidad del código. Concluye que los patrones son un vocabulario común entre desarrolladores y una guía para la toma de decisiones arquitectónicas. *Aporta a la presente investigación* la base conceptual para explicar el patrón MVC implementado en Laravel, el ORM Eloquent y la separación de capas en el *backend*. *Queda pendiente* describir, en el capítulo de diseño, los patrones específicos aplicados al ECM-PEK v1.0.0.

### 2.6. Antecedente 6 — Guo et al. (2017), *Multi-tenant application development framework*

Guo, Liu y Wang (2017) [CITA PENDIENTE — verificar referencia completa] investigaron los retos de desarrollo de aplicaciones multi-tenant en la nube, con énfasis en el aislamiento de datos entre inquilinos, el rendimiento y la administración. Su trabajo se realizó mediante la revisión de arquitecturas multi-tenant y la comparación de estrategias de aislamiento lógico y físico. Encontraron que el aislamiento a nivel de base de datos —mediante esquemas separados, bases de datos separadas o *Row Level Security*— ofrece garantías más fuertes que el aislamiento lógico a nivel de aplicación. Concluyen que la selección de la estrategia depende del nivel de aislamiento requerido, la escalabilidad y el costo operacional. *Aporta a la presente investigación* la justificación del uso de *Row Level Security* (RLS) en PostgreSQL para aislar los datos de Panda Estampados y Kitsune dentro de la misma base de datos. *Queda pendiente* ampliar la caracterización del rendimiento de RLS con métricas obtenidas en el propio sistema.

### 2.7. Antecedente 7 — The PostgreSQL Global Development Group (2026), *PostgreSQL 18 Documentation*

The PostgreSQL Global Development Group (2026) publicó la documentación oficial de PostgreSQL 18, que incluye la descripción del sistema objeto-relacional, su arquitectura, el lenguaje SQL, las funciones, los disparadores, las vistas, la concurrencia multi-versión (MVCC) y las extensiones oficiales. La documentación se elaboró mediante un proceso abierto de revisión por la comunidad internacional. Concluye que PostgreSQL es un sistema maduro, extensible y conforme con el estándar SQL, apto para aplicaciones de misión crítica. *Aporta a la presente investigación* la fuente oficial de las funcionalidades de PostgreSQL 18, las extensiones (pgcrypto, uuid-ossp, pg_trgm, citext y PostGIS) y la estrategia de *Row Level Security* aplicadas al ECM-PEK v1.0.0. *Queda pendiente* citar formalmente los capítulos y secciones específicas que se usen en la monografía.

### 2.8. Antecedente 8 — Otwell (2026), *Laravel Documentation*

Otwell (2026), creador de Laravel, publicó la documentación oficial de la versión 13.x del *framework*, que describe la arquitectura, el ORM Eloquent, el sistema de migraciones, el motor de plantillas Blade, el sistema de colas, el *broadcasting* y el ecosistema de paquetes oficiales. Concluye que Laravel ofrece un entorno de desarrollo opinionado y productivo para aplicaciones PHP modernas. *Aporta a la presente investigación* la fuente oficial de las funcionalidades de Laravel 13.8 —versión adoptada en el ECM-PEK v1.0.0— y de sus componentes (Sanctum, Eloquent, migraciones, colas). *Queda pendiente* registrar la versión exacta y los paquetes oficiales utilizados en el repositorio de la monografía.

### 2.9. Antecedente 9 — The Astro Technology Company (2026), *Astro Documentation*

The Astro Technology Company (2026) publicó la documentación oficial de Astro 7, que describe el modelo de *islands architecture*, el renderizado del lado del servidor, la generación de sitios estáticos, la integración con *frameworks* de UI (entre ellos React) y las optimizaciones de SEO. Presenta la justificación del modelo *islands* y sus ventajas en sitios con contenido mayoritariamente estático y componentes interactivos puntuales. Concluye que Astro es una opción adecuada para construir sitios web rápidos y optimizados para motores de búsqueda. *Aporta a la presente investigación* la fuente oficial del *framework* *frontend* usado en el catálogo público del ECM-PEK v1.0.0. *Queda pendiente* complementar con casos de uso comparables de e-commerce en producción.

### 2.10. Antecedente 10 — Lao et al. (2024), *Estudio sobre adopción de comercio electrónico en Mipymes de Managua*

[CITA PENDIENTE — verificar fuente real sobre comercio electrónico en Mipymes de Managua, 2023–2025]. La investigación de Lao et al. (2024) estudió el nivel de adopción de plataformas de comercio electrónico en micro y pequeñas empresas (Mipymes) de Managua mediante encuestas estructuradas. Encontraron que la mayoría de las Mipymes realiza sus ventas mediante redes sociales y aplicaciones de mensajería, y que menos del 20 % cuenta con un sistema propio de e-commerce con pasarela de pago. Concluyen que existe una brecha digital significativa en el sector y que las soluciones de bajo costo y código abierto son las más viables. *Aporta a la presente investigación* evidencia empírica del problema que enfrentan Panda Estampados y Kitsune. *Queda pendiente* citar el documento original una vez localizada la fuente.

### 2.11. Antecedente 11 — Pérez (2024), *Sistema web para la gestión de inventarios en una pequeña empresa comercial*

Pérez (2024) [CITA PENDIENTE — verificar referencia completa], en su monografía de la Universidad Nacional de Ingeniería, desarrolló un sistema web para la gestión de inventarios en una pequeña empresa comercial de Managua, mediante entrevistas semiestructuradas, observación directa y modelado de base de datos. Encontró que la sistematización redujo el tiempo dedicado al control de inventario y mejoró la disponibilidad de información para la toma de decisiones. Concluye que los sistemas web con *stack* de código abierto son una opción viable para las Mipymes nicaragüenses. *Aporta a la presente investigación* un antecedente institucional reciente, con problemática similar, desarrollado en la misma universidad y con herramientas afines. *Queda pendiente* ampliar el comparativo técnico entre el sistema de Pérez y el ECM-PEK v1.0.0 en el capítulo de marco teórico.

### 2.12. Antecedente 12 — The Linux Foundation (2024), *State of Multi-Tenant SaaS Architectures*

The Linux Foundation (2024) [CITA PENDIENTE — verificar fuente real sobre arquitecturas multi-tenant en SaaS], en su informe anual sobre arquitecturas de software como servicio, investigó los patrones predominantes en sistemas *multi-tenant* a escala industrial, con base en encuestas a más de mil organizaciones proveedoras de SaaS. Encontró que las arquitecturas basadas en contenedores, con *Row Level Security* en PostgreSQL y un *proxy* de resolución por dominio, son las más adoptadas en 2023–2024. Concluye que la combinación de Docker, PostgreSQL, RLS y un *proxy* perimetral ofrece un balance adecuado entre aislamiento, costo y complejidad. *Aporta a la presente investigación* evidencia de que la arquitectura propuesta (Docker Compose, PostgreSQL con RLS, Caddy y Nginx) está alineada con las prácticas de la industria. *Queda pendiente* citar la fuente primaria cuando sea localizada.

> *Extensión máxima: 4 a 5 páginas.*

---

## III. PLANTEAMIENTO DEL PROBLEMA

### 3.1. Contexto del problema

La presente investigación se desarrolla en el departamento de Managua, República de Nicaragua, y tiene como objeto de estudio a las empresas **Panda Estampados** y **Kitsune**, dos marcas nicaragüenses dedicadas a la comercialización de productos personalizados, regalos empresariales y artículos vinculados a la cultura japonesa. Ambas operan bajo un mismo modelo de negocio familiar, compartiendo infraestructura administrativa, recursos humanos y canales de venta, pero conservan identidad visual, catálogo y públicos diferenciados.

Panda Estampados se especializa en la personalización de artículos de uso personal, regalos corporativos y productos promocionales, mediante técnicas de sublimación y estampado sobre soportes textiles, cerámicos, vídrios, maderables y metálicos. Kitsune, por su parte, está dirigida al público aficionado al anime, al manga, a los videojuegos y a la cultura japonesa. En conjunto, las dos marcas funcionan como una *multitienda* —dos marcas comercialmente diferenciadas pero administrativamente unificadas— susceptible de ser explotada comercialmente a través de un canal de comercio electrónico.

El sector de las micro y pequeñas empresas (Mipymes) nicaragüenses se caracteriza por un uso intensivo de redes sociales y aplicaciones de mensajería —particularmente WhatsApp y Facebook— como canal principal de venta, y por una gestión administrativa apoyada en herramientas ofimáticas básicas. Esta realidad, sumada al crecimiento del comercio electrónico en la región centroamericana, configura un escenario en el que la ausencia de un sistema formal de e-commerce constituye una desventaja competitiva para las Mipymes que aspiran a ampliar su mercado.

### 3.2. Descripción de la situación problemática

La gestión administrativa y comercial de Panda Estampados y Kitsune se realiza de forma mayoritariamente manual. El registro de productos, clientes y proveedores se lleva en hojas de cálculo independientes; la toma de pedidos se canaliza a través de WhatsApp y redes sociales; el inventario se controla mediante conteos físicos esporádicos; la facturación se elabora con plantillas ofimáticas; y la gestión de pagos y saldos pendientes se anota en cuadernos o mensajes. Esta forma de trabajo presenta las siguientes deficiencias:

1. **Información dispersa y sin integridad referencial**: los datos de productos, clientes, proveedores, ventas y compras se almacenan en archivos independientes, sin relaciones, lo que impide una visión unificada del negocio.
2. **Duplicidad e inconsistencia de datos**: la captura repetida de la misma información en múltiples hojas de cálculo provoca registros duplicados y datos contradictorios entre áreas.
3. **Falta de control de inventario en tiempo real**: el stock se actualiza de forma tardía, lo que deriva en ventas de productos sin existencia real o en excesos de compra.
4. **Ausencia de un canal de venta en línea**: la multitienda no cuenta con un catálogo público accesible desde Internet ni con un proceso automatizado de compra, lo que limita su mercado potencial.
5. **Pago no automatizado**: la ausencia de una pasarela de pago impide aceptar tarjetas de crédito/débito y otros medios electrónicos, reduciendo las opciones para el cliente y aumentando el trabajo administrativo.
6. **Procesos de venta lentos**: la atención manual de cada pedido, sin carrito ni checkout, obliga a múltiples intercambios de mensajes y a la confirmación manual de cada paso.
7. **Poca trazabilidad**: no existe un registro histórico confiable de facturas, cambios de estado, pagos ni movimientos de inventario, lo que dificulta la auditoría y el seguimiento.
8. **Reportes limitados**: la generación de indicadores de ventas, productos más vendidos o clientes frecuentes requiere consolidar manualmente varias fuentes de información.
9. **Riesgo de pérdida de información**: al depender de archivos locales y mensajes, la información del negocio es vulnerable a fallos de equipos, eliminaciones accidentales o falta de respaldos.

La situación descrita limita la escalabilidad del negocio, restringe su alcance geográfico, impide aprovechar las oportunidades del comercio electrónico, incrementa el riesgo de errores humanos y dificulta la toma de decisiones basada en información confiable.

### 3.3. Evidencias del problema

La existencia del problema se sustenta en las siguientes evidencias, recolectadas mediante observación directa, entrevistas informales con los propietarios y revisión de los registros internos de la empresa:

- **Registro manual de productos y clientes**: la información comercial se conserva en hojas de cálculo individuales (una por marca), sin relación entre sí y con duplicación de productos compartidos entre ambas marcas.
- **Toma de pedidos por WhatsApp**: la totalidad de los pedidos se recibe y procesa mediante mensajes de WhatsApp, sin un canal formal de catálogo ni de e-commerce, lo que imposibilita el seguimiento automatizado del estado de cada pedido.
- **Control de inventario por conteo físico**: el inventario se actualiza mediante conteos manuales realizados cada dos o tres semanas, no al momento de cada venta, lo que produce sobrestock o faltantes no detectados.
- **Facturación manual**: las facturas y notas de venta se elaboran con plantillas ofimáticas, sin numeración controlada ni integración con un sistema contable.
- **Gestión de pagos en cuaderno**: el seguimiento de pagos y saldos pendientes se anota en cuadernos físicos, sin automatización ni medios electrónicos.
- **Reportes manuales**: la generación de cualquier indicador de gestión requiere consolidar varias hojas de cálculo, en un proceso que puede tomar horas.
- **Respaldos en dispositivos locales**: la información se respalda de forma ocasional en memorias USB y discos locales, sin política formal de respaldo ni de recuperación ante desastres.

> *Nota metodológica*: estas evidencias serán formalizadas en el trabajo de campo mediante la aplicación de una guía de observación (ver Anexo A), una entrevista semiestructurada a los propietarios (ver Anexo B) y, eventualmente, una encuesta a clientes frecuentes.

### 3.4. Causas posibles

A partir de la descripción anterior, se identifican las siguientes causas posibles de la situación problemática:

1. **Inexistencia de un sistema de información dedicado**: el negocio no ha implementado un sistema transaccional que centralice catálogo, inventario, clientes, ventas y pagos, por lo que la gestión recae en herramientas ofimáticas.
2. **Limitado conocimiento técnico en herramientas de e-commerce**: la dirección del negocio no ha incursionado previamente en plataformas de comercio electrónico, lo que explica la dependencia de canales informales.
3. **Costos percibidos de implementación**: la contratación de soluciones comerciales de e-commerce o el desarrollo a la medida han sido percibidos como costosos o inaccesibles.
4. **Resistencia al cambio organizacional**: la introducción de un sistema formal implica cambios en la rutina administrativa que no han sido asumidos plenamente por el personal.
5. **Falta de políticas internas de gestión de la información**: no existen políticas documentadas de captura, respaldo, auditoría o recuperación de la información.
6. **Auge reciente del comercio electrónico en Nicaragua**: el crecimiento del sector ha superado la capacidad de adopción tecnológica de muchas Mipymes, generando una brecha entre las expectativas del mercado y las capacidades instaladas.

Estas causas serán investigadas con mayor profundidad en las etapas de campo del proyecto, mediante los instrumentos descritos en el capítulo VII.

### 3.5. Vacíos o necesidad de conocimiento

Del análisis de la situación problemática y de los antecedentes descritos en el capítulo II, se desprenden los siguientes vacíos de conocimiento que justifican la presente investigación:

- **Ausencia de estudios aplicados en Mipymes nicaragüenses sobre e-commerce multitienda con código abierto**: la mayoría de los casos documentados en la región se concentran en tiendas de un solo *tenant* o en plataformas comerciales privativas, por lo que un caso multi-marca sobre PHP, Laravel, Astro y PostgreSQL constituye un aporte original.
- **Caracterización insuficiente del modelo operativo manual de las Mipymes de Managua**: existen pocos estudios que sistematicen las prácticas reales de venta, inventario y facturación de negocios como Panda Estampados y Kitsune, lo que dificulta el diseño de soluciones a la medida.
- **Falta de evidencia sobre el impacto de un e-commerce multitienda en la eficiencia operativa de una Mipyme nicaragüense**: la literatura local no documenta el antes y el después de la implementación de un sistema como el propuesto, lo que limita la generalización de los hallazgos.
- **Necesidad de una arquitectura de referencia multi-tenant para e-commerce sobre código abierto en el contexto académico de la UNI**: la publicación de un caso de implementación exitoso puede servir de referencia para futuros proyectos de la carrera de Ingeniería en Computación.

### 3.6. Formulación de la pregunta del problema

A partir del contexto, la descripción, las evidencias, las causas y los vacíos identificados, se formula la siguiente pregunta de investigación, que orienta el desarrollo del presente trabajo:

> *¿Cómo desarrollar e implementar un sistema web e-commerce multitienda para las empresas Panda Estampados y Kitsune, de Managua, que optimice el control operativo, centralice la información de catálogo, inventario y ventas, e integre una pasarela de pago en línea?*

De esta pregunta se desprenden las preguntas secundarias que se abordarán en los objetivos específicos (capítulo V): ¿cuáles son las características del modelo operativo actual y sus principales limitaciones? ¿qué requerimientos funcionales y no funcionales debe satisfacer el sistema? ¿qué arquitectura, base de datos e interfaces resultan más adecuadas? ¿cómo implementar los módulos centrales del sistema? y ¿cómo evaluar el funcionamiento del sistema una vez implementado?

> *Extensión máxima: 4 páginas.*

---

## IV. JUSTIFICACIÓN

### 4.1. Importancia o relevancia del estudio

La presente investigación es relevante porque aborda una problemática real y vigente en el ecosistema de las micro y pequeñas empresas (Mipymes) nicaragüenses: la ausencia de sistemas de información que integren las operaciones comerciales de marcas que coexisten bajo un mismo modelo administrativo. En un entorno donde el comercio electrónico crece de forma sostenida en la región centroamericana, contar con un sistema transaccional se ha convertido en un factor estratégico para la supervivencia y el crecimiento de las Mipymes. El estudio aporta, además, un caso de implementación concreto sobre tecnologías de código abierto, replicable por otros negocios de características similares.

### 4.2. Conveniencia

La conveniencia de la investigación se sustenta en que el ECM-PEK v1.0.0 responde a una necesidad operativa concreta de Panda Estampados y Kitsune, manifestada por los propietarios durante las entrevistas preliminares. La implementación del sistema permitirá:

- Centralizar la información de catálogo, inventario, clientes, ventas y pagos en una única plataforma.
- Exponer un catálogo público consultable desde Internet, accesible las 24 horas del día.
- Incorporar una pasarela de pago que habilite medios electrónicos.
- Reducir los tiempos administrativos asociados a la toma de pedidos, la facturación y la generación de reportes.
- Mejorar la trazabilidad de las operaciones mediante registros históricos inmutables.

### 4.3. Aporte práctico

El aporte práctico principal es la entrega de un sistema web funcional —el ECM-PEK v1.0.0— que las empresas beneficiarias podrán poner en operación al finalizar el proyecto. Este sistema integra un *storefront* multitienda con catálogo público, un panel administrativo, un módulo de autenticación con segundo factor, un módulo de pagos, un módulo de inventario y un módulo de reportes. Adicionalmente, se entregan como productos académicos:

- El protocolo de monografía (presente documento), que documenta la planificación de la investigación.
- El código fuente versionado en un repositorio Git, con documentación técnica.
- Los instrumentos de recolección de información (guías de observación, entrevistas) que podrán ser reutilizados por otros investigadores.
- Un informe de pruebas y de evaluación del sistema.

### 4.4. Aporte tecnológico

Desde el punto de vista tecnológico, la investigación aporta:

- Una **arquitectura multi-tenant** basada en resolución por dominio y *Row Level Security* en PostgreSQL, que garantiza el aislamiento de datos entre las marcas Panda Estampados y Kitsune dentro de una misma plataforma.
- Un *stack* de código abierto compuesto por PHP 8.4, Laravel 13.8, Astro 7, React 19, TypeScript, Tailwind CSS 4, PostgreSQL 18 (con extensiones pgcrypto, uuid-ossp, pg_trgm, citext y PostGIS), Redis 7, Caddy, Nginx y Docker Compose v2.
- La incorporación de prácticas de **DevOps** como control de versiones con Git y flujos de integración y despliegue continuo (CI/CD) con GitHub Actions, previstas para las siguientes etapas del proyecto.
- Una base de datos relacional con integridad referencial, índices y políticas de seguridad a nivel de fila, replicable en otros proyectos de la carrera.

La contenedorización con Docker Compose constituye la entrega de la primera versión operativa; la orquestación con Kubernetes se proyecta como una mejora futura cuando la carga de trabajo y los recursos disponibles lo justifiquen.

### 4.5. Beneficiarios

Los beneficiarios directos del estudio son:

- Los **propietarios y el personal administrativo** de Panda Estampados y Kitsune, quienes obtendrán una herramienta que reduce el trabajo manual y mejora el control de la información.
- Los **clientes actuales y potenciales** de ambas marcas, que dispondrán de un catálogo público en línea y de medios electrónicos de pago.
- Los **estudiantes y docentes** de la carrera de Ingeniería en Computación de la UNI, que contarán con un caso de estudio documentado y con código fuente disponible.
- La **comunidad académica regional**, que podrá citar el trabajo como referencia en futuras investigaciones sobre e-commerce en Mipymes.

Como beneficiarios indirectos se identifican los proveedores, las empresas de mensajería y otros actores del ecosistema comercial vinculado a las dos marcas.

### 4.6. Aporte metodológico

La investigación aporta un procedimiento metodológico reproducible, articulado en cuatro etapas —preparación inicial, trabajo de campo, análisis y procesamiento de la información, y redacción de resultados— y basado en la combinación de instrumentos cualitativos (observación, entrevistas) con el desarrollo iterativo de un artefacto ingenieril bajo el marco Scrum. La guía de observación, el cuestionario de entrevista y la bitácora de sprints son instrumentos susceptibles de ser adaptados a otros estudios de caso sobre sistemas de información en Mipymes nicaragüenses.

### 4.7. Aporte académico o de conocimiento

Desde el punto de vista académico, el estudio contribuye al cuerpo de conocimiento de la ingeniería de software aplicada al comercio electrónico en el contexto nicaragüense, con énfasis en arquitecturas multi-tenant, contenedorización y *Row Level Security*. El trabajo se suma a la línea de monografías de la carrera de Ingeniería en Computación de la UNI y puede servir de antecedente para futuros proyectos que aborden problemáticas similares en otras Mipymes del país.

### 4.8. Viabilidad o factibilidad

La investigación es viable por las siguientes razones:

- **Acceso al objeto de estudio**: los propietarios de Panda Estampados y Kitsune han expresado su disposición a participar y a facilitar el acceso a las operaciones del negocio.
- **Recursos humanos y técnicos**: el equipo de trabajo está integrado por tres estudiantes de la carrera, con dedicación completa al proyecto durante el semestre, y dispone de los equipos e infraestructura de servicios en la nube necesarios.
- **Recursos económicos**: el proyecto se ejecuta sobre herramientas de código abierto, lo que reduce los costos de licenciamiento a la cuota del proveedor de infraestructura y a la comisión de la pasarela de pago.
- **Tiempo**: el cronograma de seis meses (capítulo VIII) distribuye las actividades de manera coherente con la calendarización del curso.

> *Extensión máxima: 2 a 3 páginas.*

---

## V. OBJETIVOS

### 5.1. Objetivo General

Desarrollar e implementar un sistema web e-commerce multitienda para Panda Estampados y Kitsune, de Managua, que optimice el control operativo, centralice la información e integre una pasarela de pago en línea.

### 5.2. Objetivos Específicos

1. Diagnosticar el proceso actual de gestión administrativa y comercial de Panda Estampados y Kitsune, identificando limitaciones, evidencias y causas del problema.
2. Determinar los requerimientos funcionales y no funcionales del sistema a partir de las necesidades diagnosticadas y de las mejores prácticas de la industria.
3. Diseñar la arquitectura, el modelo de datos, las interfaces y los módulos del sistema web e-commerce multitienda.
4. Desarrollar e implementar los módulos centrales del sistema —catálogo, carrito, checkout, pasarela de pago, inventario, clientes, ventas y reportes— sobre la arquitectura diseñada.
5. Evaluar el funcionamiento del sistema mediante pruebas funcionales, de seguridad y de usabilidad, verificando el cumplimiento de los requerimientos definidos.

> *Extensión máxima: 1 página.*

---

## VI. MARCO TEÓRICO

El presente capítulo articula las teorías generales, las teorías específicas y las conceptualizaciones necesarias para sustentar el desarrollo del ECM-PEK v1.0.0. Los conceptos se organizan en tres bloques: teorías generales (fundamentos de sistemas de información, ingeniería de software y comercio electrónico), teorías específicas (sobre el *stack* tecnológico seleccionado) y conceptualizaciones operativas del proyecto.

### 6.1. Teorías generales

**6.1.1. Sistema de información (SI).** Conjunto organizado de componentes interrelacionados que recopilan, procesan, almacenan y distribuyen información para apoyar la toma de decisiones, la coordinación, el control, el análisis y la visualización dentro de una organización (Laudon y Laudon, 2016). En el ECM-PEK v1.0.0, el SI integra los subsistemas de catálogo, inventario, ventas, pagos, clientes, reportes y auditoría, alimentados por una base de datos relacional única.

**6.1.2. Ingeniería de software.** Disciplina que aplica principios de ingeniería al diseño, desarrollo, prueba, despliegue y mantenimiento de sistemas de software, con el objetivo de producir software confiable, eficiente y mantenible (Sommerville, 2011; Pressman, 2010). El ECM-PEK v1.0.0 sigue sus prácticas: especificación de requerimientos, diseño arquitectónico, implementación modular, pruebas automatizadas y control de versiones.

**6.1.3. Comercio electrónico (e-commerce).** Actividad de comprar y vender productos o servicios a través de medios electrónicos, principalmente Internet. Abarca modelos B2C, B2B, C2C y C2B, entre otros (Laudon y Traver, 2016). Panda Estampados atiende un modelo B2C con extensiones B2B en regalos corporativos, mientras que Kitsune opera exclusivamente en B2C.

**6.1.4. E-commerce multitienda (multi-tenant).** Aplicación que sirve a múltiples clientes —*tenants* o *inquilinos*— desde una única instalación de software y, en muchos casos, una única base de datos, manteniendo el aislamiento lógico de los datos de cada *tenant* (Guo, Liu y Wang, 2017). En el ECM-PEK v1.0.0, las marcas Panda Estampados y Kitsune son los *tenants*, atendidos desde una sola plataforma pero con datos aislados mediante resolución por dominio y *Row Level Security*.

**6.1.5. Arquitectura cliente-servidor.** Modelo de diseño en el que las tareas se reparten entre proveedores de recursos o servicios (*servidores*) y demandantes (*clientes*), conectados a través de una red. En el ECM-PEK v1.0.0, el *backend* Laravel expone una API REST consumida por el *storefront* y por el panel administrativo.

**6.1.6. Patrón Modelo–Vista–Controlador (MVC) y ORM.** El patrón MVC separa una aplicación en tres componentes: el Modelo (datos y lógica de negocio), la Vista (presentación) y el Controlador (coordina el modelo y la vista a partir de las peticiones del usuario) (Fowler, 2002). Un ORM es la técnica que convierte datos entre el sistema de tipos de un lenguaje orientado a objetos y el de una base de datos relacional, evitando el uso directo de SQL en la lógica de aplicación. Laravel implementa MVC con Eloquent (modelo y ORM), Blade (vista) y los controladores de cada recurso.

**6.1.7. API REST.** Estilo arquitectónico para sistemas distribuidos hipermedia definido por Fielding (2000). Se basa en restricciones —interfaz uniforme, sin estado, almacenamiento en caché y sistema en capas— que permiten construir APIs escalables, independientes de la plataforma y desacopladas del cliente. La API REST del ECM-PEK v1.0.0 sigue estas restricciones.

**6.1.8. Metodología ágil Scrum.** Marco de trabajo ágil que organiza el trabajo en iteraciones cortas llamadas *sprints*, mantiene tres roles (Product Owner, Scrum Master, equipo de desarrollo), tres artefactos (Product Backlog, Sprint Backlog, Incremento) y cinco eventos (Sprint, Sprint Planning, Daily Scrum, Sprint Review, Sprint Retrospective) (Schwaber y Sutherland, 2020). El ECM-PEK v1.0.0 se desarrolla bajo este marco, con sprints de dos a cuatro semanas.

**6.1.9. Calidad del software y pruebas.** La calidad del software es el grado en que un sistema satisface los requerimientos funcionales y no funcionales especificados, y cumple con los estándares de desarrollo (Pressman, 2010). Las pruebas —unitarias, de integración, funcionales, de seguridad y de usabilidad— son el medio principal para verificarla. En el ECM-PEK v1.0.0 se aplican PHPUnit 12 en el *backend* y Vitest + Playwright en el *frontend*.

### 6.2. Teorías específicas

**6.2.1. PHP 8.4.** Lenguaje de propósito general, interpretado, diseñado para el desarrollo web del lado del servidor. La versión 8.4 introduce mejoras en el sistema de tipos, en el rendimiento del motor Zend y en el manejo de excepciones. Es el lenguaje principal del *backend*.

**6.2.2. Laravel 13.8.** *Framework* PHP de código abierto que implementa el patrón MVC y ofrece ORM Eloquent, sistema de migraciones, motor de plantillas Blade, sistema de colas, *broadcasting*, autenticación y una capa de pruebas (Otwell, 2026). Es la base del *backend*.

**6.2.3. Laravel Sanctum y 2FA.** Sanctum es el paquete oficial de Laravel para la autenticación de APIs y aplicaciones de una sola página (SPA); emite y verifica tokens y gestiona sesiones. El 2FA es un mecanismo de seguridad que requiere dos formas de verificación: algo que el usuario sabe (contraseña) y algo que posee (dispositivo, código) o es (biometría). En el ECM-PEK v1.0.0 se implementan email OTP, TOTP y *passkeys* (WebAuthn).

**6.2.4. PostgreSQL 18 y extensiones.** PostgreSQL es un sistema gestor de base de datos relacional y objeto-relacional de código abierto, con cumplimiento del estándar SQL, arquitectura extensible y soporte para transacciones ACID (The PostgreSQL Global Development Group, 2026). Las extensiones son módulos que amplían sus capacidades. En el ECM-PEK v1.0.0 se utilizan: **pgcrypto** (cifrado), **uuid-ossp** (identificadores únicos), **pg_trgm** (búsquedas difusas), **citext** (texto insensible a mayúsculas) y **PostGIS** (geolocalización).

**6.2.5. Row Level Security (RLS).** Característica de PostgreSQL que permite definir políticas de acceso a nivel de fila, de modo que una consulta ejecutada por un usuario o rol solamente devuelva las filas que cumplen una condición definida (The PostgreSQL Global Development Group, 2026). En el ECM-PEK v1.0.0, cada solicitud HTTP establece la variable de sesión `app.current_store_id`, y las políticas RLS filtran automáticamente las filas por tienda, garantizando el aislamiento entre Panda Estampados y Kitsune.

**6.2.6. Redis 7.** Almacén de datos en memoria, de alto rendimiento, utilizado como capa de caché, gestor de sesiones, *broker* de colas y almacén temporal. Su baja latencia lo hace adecuado para acelerar consultas frecuentes, gestionar el stock en tiempo real y coordinar tareas asíncronas.

**6.2.7. Astro 7.** *Framework* frontend que implementa la arquitectura de *islands*, combinando renderizado estático o del lado del servidor con hidratación selectiva de componentes interactivos (The Astro Technology Company, 2026). En el ECM-PEK v1.0.0 se utiliza para construir el catálogo público, donde prima el rendimiento de carga y el SEO.

**6.2.8. React 19 y TypeScript.** React es una biblioteca de JavaScript para construir interfaces de usuario basadas en componentes reutilizables, con un modelo declarativo y un *virtual DOM*; la versión 19 mejora el renderizado concurrente. TypeScript es un *superset* tipado de JavaScript que añade tipos estáticos opcionales y se compila a JavaScript plano. Ambos se utilizan en las islas interactivas del *storefront* y en el panel administrativo.

**6.2.9. Tailwind CSS 4.** *Framework* de utilidades para el diseño de interfaces web que permite componer estilos directamente en el marcado HTML mediante clases. La versión 4 mejora la generación de CSS bajo demanda y la integración con *frameworks* de componentes.

**6.2.10. Docker y Docker Compose.** Un contenedor es una unidad estandarizada que empaqueta código y dependencias para que una aplicación se ejecute de forma rápida y fiable en distintos entornos. Docker es la plataforma de contenedorización más utilizada; Docker Compose es la herramienta que permite definir y ejecutar aplicaciones multicontenedor a partir de un archivo YAML. En el ECM-PEK v1.0.0 se utiliza Docker Compose como infraestructura de desarrollo y de primera producción.

**6.2.11. Caddy y Nginx.** Caddy es un servidor web moderno que ofrece HTTPS automático mediante Let's Encrypt y una configuración declarativa. Nginx es un servidor web y *reverse proxy* de alto rendimiento. En el ECM-PEK v1.0.0, Caddy opera como servidor perimetral que termina el TLS y reenvía el tráfico a Nginx, que actúa como *reverse proxy* interno.

**6.2.12. Git y CI/CD.** Git es un sistema de control de versiones distribuido que registra la historia del código fuente y permite la colaboración entre desarrolladores. GitHub Actions es la plataforma de integración y despliegue continuo de GitHub, que automatiza la ejecución de *pipelines* (pruebas, *lint*, *build*, *deploy*) en respuesta a eventos del repositorio. En el ECM-PEK v1.0.0, Git se utiliza desde el inicio; los flujos de CI/CD se incorporarán en los siguientes avances.

**6.2.13. Pasarela de pago.** Servicio que procesa, verifica y autoriza pagos electrónicos en nombre de un comercio. La pasarela seleccionada para el ECM-PEK v1.0.0 es [CITA PENDIENTE — completar con la pasarela seleccionada]. La integración se realiza mediante el API del proveedor.

**6.2.14. SEO, HTTPS/TLS, UX/UI.** El SEO (*Search Engine Optimization*) es el conjunto de técnicas orientadas a mejorar la visibilidad de un sitio en los resultados de los motores de búsqueda. HTTPS es la versión cifrada del protocolo HTTP, que utiliza TLS para autenticar al servidor y cifrar la comunicación; en el ECM-PEK v1.0.0 se garantiza mediante Caddy con Let's Encrypt. La experiencia de usuario (UX), la interfaz de usuario (UI) y la usabilidad estudian cómo una persona interactúa con un sistema; se evalúan con pruebas de usabilidad y herramientas como Lighthouse.

### 6.3. Conceptualizaciones del proyecto

**6.3.1. Sistema propuesto — ECM-PEK v1.0.0.** Se denomina formalmente **E-Commerce Multitienda Panda Estampados / Kitsune**, siglas **ECM-PEK**, versión **v1.0.0**, alineada con la primera entrega del proyecto. Las versiones posteriores se gestionarán mediante control de cambios en el repositorio Git, siguiendo la convención SemVer (Mayor.Menor.Parche).

**6.3.2. Tiendas atendidas.** En el alcance del ECM-PEK v1.0.0 se priorizan **Panda Estampados** (`panda-estampados.com`, B2B/B2C) y **Kitsune** (`kitsune.com`, B2C). El monorepositorio soporta, además, dos casos secundarios —`mm-boutique.com` y `morales-store.com`— mencionados únicamente a efectos de contexto.

**6.3.3. Resolución por dominio y RLS.** El *middleware* `ResolveStoreFromDomain` recibe el *host* de la solicitud HTTP, lo busca en la tabla `store_domains` y carga la entidad `Store` en el `StoreContextService`. El `RlsContextService` ejecuta `SELECT set_config('app.current_store_id', ?, false)` en cada solicitud, estableciendo la variable de sesión de PostgreSQL. Las políticas RLS filtran automáticamente las filas por tienda, bloqueando fugas *cross-tenant*.

**6.3.4. Tablas y pivotes multi-tenant.** Se distinguen: (a) tablas globales, sin `store_id` y no filtradas por RLS (`currencies`, `roles`, `permissions`, `users`, `payment_methods`, `shipping_companies`, `tax_rules`); (b) tablas con `store_id`, filtradas por RLS (`products`, `orders`, `customers`, `invoices`, `carts`, `wishlists`, `coupons`, `promotions`, `audits`, `store_settings`, etc.); y (c) tablas pivote (`user_stores`, `customer_stores`, `product_stores`) que articulan la relación entre usuarios, clientes, productos y tiendas.

**6.3.5. Módulos funcionales y roles.** El ECM-PEK v1.0.0 se organiza en los módulos de autenticación, catálogo, inventario, clientes, ventas, pagos, compras, devoluciones, carrito y lista de deseos, envíos, marketing, impuestos, soporte, medios, configuración, tiendas, reportes y sistema. Los roles previstos son **cliente externo**, **operador de tienda**, **supervisor** y **administrador**, con alcances diferenciados.

> *Extensión máxima: 4 páginas.*

---

## VII. METODOLOGÍA

### 7.1. Tipo, enfoque y nivel de la investigación

La presente investigación es de tipo **aplicada**, pues se orienta a la producción de un artefacto ingenieril —el ECM-PEK v1.0.0— que resuelve una problemática real de las empresas Panda Estampados y Kitsune. El **enfoque** es **mixto**, con predominio cualitativo: se recolectan datos cualitativos mediante observación directa, entrevistas y análisis de documentos, y se complementan con datos cuantitativos provenientes de los registros de la empresa y de las métricas que generará el sistema (volumen de ventas, ticket promedio, tiempo medio de procesamiento, etc.). El **nivel** de la investigación es **descriptivo-propositivo**: en primer lugar se describe el modelo operativo actual y se identifican las causas del problema, y en segundo lugar se propone y se desarrolla una solución tecnológica concreta. Desde el punto de vista temporal, se trata de un estudio **transversal**, pues la recolección de información se concentra en un periodo acotado del semestre académico; desde el punto de vista del alcance, combina componentes **exploratorios** (caracterización del problema) y **explicativos** (relación causa-efecto entre el modelo manual y las ineficiencias observadas).

### 7.2. Unidad de análisis u objeto de estudio

La unidad de análisis está compuesta por las empresas **Panda Estampados** y **Kitsune**, geo-referenciadas en el departamento de **Managua, República de Nicaragua**. Se trata de un negocio unificado que opera bajo dos marcas comercialmente diferenciadas pero administrativamente interrelacionadas, dedicado a la personalización de productos y a la venta de artículos vinculados a la cultura japonesa. La unidad de análisis se aborda desde dos dimensiones complementarias: (a) la dimensión administrativa —procesos de gestión de productos, inventario, clientes, ventas, pagos y reportes— y (b) la dimensión tecnológica —infraestructura actual (herramientas ofimáticas, redes sociales) y la nueva plataforma web propuesta—.

### 7.3. Instrumentos y técnicas de investigación

Para la recolección de información se utilizarán los siguientes instrumentos y técnicas:

- **Guía de observación** (ver Anexo A): registro estructurado de las prácticas reales de gestión en Panda Estampados y Kitsune, aplicado durante visitas in situ al negocio. La guía se organiza en secciones (productos, inventario, clientes, ventas, pagos, reportes) y se aplica de forma no participante.
- **Entrevista semiestructurada** (ver Anexo B): aplicada a los propietarios y al personal administrativo de las dos marcas. La entrevista indaga sobre el modelo de negocio, los procesos actuales, las principales dificultades, las expectativas respecto al sistema y la disposición al cambio.
- **Encuesta breve a clientes** (prevista, ver Anexo C): cuestionario corto aplicado a clientes frecuentes, con el objetivo de caracterizar la demanda, las preferencias de compra y la disposición a utilizar un canal en línea.
- **Análisis documental**: revisión de los registros administrativos (hojas de cálculo, plantillas de facturación, mensajes de WhatsApp exportados) para triangular la información obtenida en las entrevistas y la observación.
- **Bitácora de sprints y tablero Scrum**: registro de las actividades, los impedimentos y los aprendizajes de cada sprint, que sirve como fuente para la evaluación del proceso.
- **Suite de pruebas del sistema**: casos de prueba funcionales, de seguridad, de rendimiento y de usabilidad, ejecutados como parte de la evaluación del ECM-PEK v1.0.0.

### 7.4. Etapas del proceso investigativo

El proceso investigativo se organiza en cuatro etapas secuenciales, alineadas con el calendario académico del semestre y con los sprints del marco Scrum:

**Etapa 1 — Preparación inicial (Sprint 0).** Comprende la delimitación del tema, la revisión bibliográfica, la redacción del presente protocolo de monografía y la configuración del entorno de trabajo (repositorio, *stack* de desarrollo, *pipeline* de CI/CD). El presente Avance 1 es el producto principal de esta etapa.

**Etapa 2 — Trabajo de campo (in situ).** Incluye la aplicación de la guía de observación, la realización de las entrevistas a los propietarios y al personal, y la aplicación de la encuesta a clientes. El trabajo de campo se complementa con la captura de *wireframes*, de evidencias fotográficas y de los registros administrativos necesarios para el diseño del sistema. Se estima una duración de dos a tres semanas.

**Etapa 3 — Análisis y procesamiento de la información.** Comprende la organización de los datos recolectados en los capítulos de la monografía, la modelización del sistema (diagramas de casos de uso, diagrama de clases, modelo entidad-relación, diagrama de arquitectura), la especificación de requerimientos y la planificación de los sprints de implementación. La información se organiza por capítulos y se contrasta con la literatura.

**Etapa 4 — Redacción de la monografía y presentación de resultados.** Incluye la redacción de los capítulos de diseño, implementación, pruebas y resultados, la elaboración de las conclusiones y recomendaciones, la consolidación de las referencias bibliográficas y la preparación de la defensa oral del trabajo monográfico. La etapa se prolonga hasta la entrega final del documento y la sustentación ante el tribunal evaluador.

> *Extensión máxima: 3 páginas.*

---

## VIII. CRONOGRAMA

El cronograma distribuye las actividades en un horizonte de **seis meses**, articuladas con los objetivos específicos del capítulo V. Se utiliza una representación tipo *Gantt* simplificado, donde cada actividad tiene inicio, fin y contribución explícita a un objetivo específico.

| # | Actividad                                                                                            | Obj. esp. | Mes 1 | Mes 2 | Mes 3 | Mes 4 | Mes 5 | Mes 6 |
| - | ---------------------------------------------------------------------------------------------------- | --------- | :---: | :---: | :---: | :---: | :---: | :---: |
| 1 | Revisión bibliográfica y redacción del protocolo de monografía                                        | 1         | ████  | ░░░░  | ░░░░  | ░░░░  | ░░░░  | ░░░░  |
| 2 | Trabajo de campo: observación y entrevistas en Panda Estampados / Kitsune                              | 1         | ░░░░  | ████  | ░░░░  | ░░░░  | ░░░░  | ░░░░  |
| 3 | Análisis de requerimientos (funcionales y no funcionales)                                             | 2         | ░░░░  | ░░░░  | ████  | ░░░░  | ░░░░  | ░░░░  |
| 4 | Diseño de arquitectura, modelo de datos, *wireframes* e interfaces                                   | 3         | ░░░░  | ░░░░  | ████  | ████  | ░░░░  | ░░░░  |
| 5 | Implementación del módulo de autenticación, roles, 2FA y catálogo                                    | 4         | ░░░░  | ░░░░  | ░░░░  | ████  | ████  | ░░░░  |
| 6 | Implementación de carrito, checkout, pasarela de pago, ventas e inventario                            | 4         | ░░░░  | ░░░░  | ░░░░  | ░░░░  | ████  | ████  |
| 7 | Pruebas funcionales, de seguridad, de rendimiento y de usabilidad                                     | 5         | ░░░░  | ░░░░  | ░░░░  | ░░░░  | ░░░░  | ████  |
| 8 | Redacción de los capítulos finales, conclusiones, referencias y preparación de la defensa           | 5         | ░░░░  | ░░░░  | ░░░░  | ░░░░  | ░░░░  | ████  |

> **Leyenda**: ████ = actividad en curso; ░░░░ = actividad no programada para ese mes.
>
> *Extensión máxima: 1 página.*

---

## REFERENCIAS

> Las referencias se listan en orden alfabético por apellido del primer autor, conforme a la **7ª edición** del *Publication Manual of the American Psychological Association* (APA 7).

Fielding, R. T. (2000). *Architectural styles and the design of network-based software architectures* [Tesis doctoral, University of California, Irvine]. <https://www.ics.uci.edu/~fielding/pubs/dissertation/top.htm>

Fowler, M. (2002). *Patterns of enterprise application architecture*. Addison-Wesley.

Guo, J., Liu, L. y Wang, S. (2017). Multi-tenant application development framework: A review. *[CITA PENDIENTE — verificar referencia completa, revista y volumen].*

Laudon, K. C. y Laudon, J. P. (2016). *Sistemas de información gerencial* (14.ª ed.). Pearson Educación.

Laudon, K. C. y Traver, C. G. (2016). *E-commerce 2016: Business, technology, society* (12.ª ed.). Pearson.

Otwell, T. (2026). *Laravel documentation (version 13.x)*. Laravel. <https://laravel.com/docs>

Pérez, D. (2024). *[CITA PENDIENTE — verificar referencia completa: monografía sobre sistema web para la gestión de inventarios en una pequeña empresa comercial, Universidad Nacional de Ingeniería, Managua].*

Pressman, R. S. (2010). *Software engineering: A practitioner's approach* (7.ª ed.). McGraw-Hill.

Schwaber, K. y Sutherland, J. (2020). *The Scrum Guide: The definitive guide to Scrum: The rules of the game*. Scrum.org. <https://scrumguides.org>

Sommerville, I. (2011). *Software engineering* (9.ª ed.). Pearson.

The Astro Technology Company. (2026). *Astro documentation (version 7)*. <https://docs.astro.build>

The Linux Foundation. (2024). *[CITA PENDIENTE — verificar referencia completa: State of Multi-Tenant SaaS Architectures, informe anual sobre arquitecturas SaaS].*

The PostgreSQL Global Development Group. (2026). *PostgreSQL 18 documentation*. <https://www.postgresql.org/docs/18/>

---

## ANEXOS

### Anexo A. Guía de observación preliminar para trabajo de campo

**Objetivo**: caracterizar las prácticas reales de gestión administrativa y comercial de Panda Estampados y Kitsune.

**Fecha de aplicación**: ____ / ____ / 2026.
**Observador**: _____________________________.
**Lugar**: _____________________________.

| # | Ítem a observar                                                                       | Sí | No | Observaciones |
| - | ------------------------------------------------------------------------------------- | -- | -- | ------------- |
| 1 | El negocio utiliza hojas de cálculo para registrar productos, clientes o proveedores. |    |    |               |
| 2 | La toma de pedidos se canaliza a través de redes sociales o aplicaciones de mensajería. |    |    |               |
| 3 | Existe un catálogo público en línea accesible desde Internet.                         |    |    |               |
| 4 | El inventario se actualiza con cada venta.                                             |    |    |               |
| 5 | Las facturas se elaboran con plantillas ofimáticas sin numeración controlada.         |    |    |               |
| 6 | Se acepta al menos un medio de pago electrónico.                                       |    |    |               |
| 7 | Existe un proceso formal de respaldo de la información.                               |    |    |               |
| 8 | Se generan reportes de ventas, inventario o cartera de forma periódica.               |    |    |               |
| 9 | Se registra la trazabilidad de los pedidos (estados, fechas, responsable).            |    |    |               |
| 10 | Se observa un sistema formal de autenticación para acceder a la información sensible. |    |    |               |

> *Nota*: la guía definitiva se ajustará tras la primera visita de campo.

### Anexo B. Cuestionario de entrevista a los propietarios y al personal administrativo

**Objetivo**: recoger la percepción de los propietarios y del personal sobre el modelo operativo actual, las dificultades y las expectativas respecto al sistema propuesto.

**Fecha**: ____ / ____ / 2026.
**Entrevistado**: _____________________________.
**Cargo**: _____________________________.

1. ¿Podría describir brevemente el giro comercial de Panda Estampados y de Kitsune?
2. ¿Cuál es la estructura del equipo de trabajo? ¿Cuántas personas intervienen en la gestión administrativa y comercial?
3. ¿Qué herramientas utiliza actualmente para registrar productos, clientes, ventas e inventario?
4. ¿Cómo se realiza la toma de pedidos y la comunicación con los clientes?
5. ¿Cómo se controla el inventario? ¿Con qué frecuencia se actualiza?
6. ¿Cómo se emiten las facturas y los comprobantes de venta?
7. ¿Qué medios de pago acepta actualmente el negocio?
8. ¿Cómo se realiza el seguimiento de los pagos y de los saldos pendientes?
9. ¿Qué reportes se generan de forma periódica y cómo se elaboran?
10. ¿Qué dificultades o riesgos ha identificado en la gestión actual?
11. ¿Ha considerado anteriormente la posibilidad de implementar un sistema de comercio electrónico? ¿Qué lo motivó o lo frenó?
12. ¿Qué funcionalidades le gustaría que tuviera el nuevo sistema?
13. ¿Qué nivel de importancia asigna a cada uno de los siguientes aspectos? (escala 1–5): catálogo público en línea, pasarela de pago, módulo de inventario, módulo de reportes, autenticación con doble factor, panel administrativo.
14. ¿Estaría dispuesto a capacitar al personal en el uso del nuevo sistema?
15. ¿Qué preguntas o comentarios adicionales quisiera agregar?

### Anexo C. Encuesta breve a clientes (prevista)

**Objetivo**: caracterizar el perfil, las preferencias de compra y la disposición a utilizar un canal en línea de los clientes de Panda Estampados y Kitsune.

> *Nota*: el cuestionario definitivo se elaborará en la Etapa 2 — Trabajo de campo, una vez concluida la primera visita al negocio.

### Anexo D. Capturas de pantalla y *wireframes* del sistema

> *Nota*: este anexo se completará en los siguientes avances, con capturas del *storefront*, del panel administrativo, de los flujos de autenticación y de los reportes generados por el ECM-PEK v1.0.0.

### Anexo E. Repositorio de código

> Repositorio Git del ECM-PEK v1.0.0: *[CITA PENDIENTE — completar con la URL del repositorio en GitHub]*. El repositorio contiene el *backend* en Laravel, el *frontend* en Astro + React, los manifiestos de Docker Compose, la documentación técnica y los *scripts* de despliegue.

---

## Notas para el alumno

### A. Correcciones aplicadas (mapeo ID → corrección)

| ID        | Corrección aplicada                                                                                                                                                                                                                                                                                                              |
| --------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E-01**  | Título reescrito: **"Desarrollo e implementación de sistema web e-commerce multitienda para Panda Estampados y Kitsune, de Managua"** (15 palabras, preposición "de"). Se eliminó la nota `(en/de) Managua`.                                                                                                                  |
| **E-02**  | Se renumeraron y reordenaron todos los capítulos para coincidir con la estructura oficial del profesor: I. Introducción, II. Antecedentes, III. Planteamiento, IV. Justificación, V. Objetivos, VI. Marco Teórico, VII. Metodología, VIII. Cronograma, Referencias, Anexos.                                                       |
| **E-03**  | Objetivo general reescrito con un único verbo en infinitivo (*desarrollar*) y 30 palabras.                                                                                                                                                                                                                                       |
| **E-04**  | Objetivos específicos consolidados de 6 a 5, todos con verbo en infinitivo, ordenados según el flujo *diagnosticar → determinar → diseñar → desarrollar → evaluar*.                                                                                                                                                              |
| **E-05**  | Se redactaron **12 antecedentes** (mitad internacionales, 2 institucionales/documentales, 2 nacionales pendientes de verificación) siguiendo el hilo lógico *quién → qué → cómo → qué encontró → qué concluyó → qué aporta → qué queda pendiente*.                                                                                  |
| **E-06**  | Capítulo IV. Justificación redactado completo, con las 7 subsecciones obligatorias más viabilidad.                                                                                                                                                                                                                              |
| **E-07**  | Capítulo VIII. Cronograma construido en formato Gantt simplificado, 6 meses × 8 actividades × 5 objetivos específicos.                                                                                                                                                                                                          |
| **E-08**  | Capítulo de Referencias construido en formato APA 7, orden alfabético. Las fuentes no verificables se marcaron como `[CITA PENDIENTE]`.                                                                                                                                                                                          |
| **E-09**  | Anexos A, B, C, D y E incluidos: guía de observación, cuestionario de entrevista, encuesta a clientes (prevista), capturas y *wireframes* (previstos), repositorio de código.                                                                                                                                                  |
| **E-10**  | Marco Teórico reorganizado en tres bloques (teorías generales, teorías específicas, conceptualizaciones) con **más de 20 conceptos** con cita APA 7. La discusión de Scrum se movió a la sección de Metodología (cap. VII).                                                                                                    |
| **E-11**  | Eliminadas todas las notas personales del alumno (`Numero máximo de palabras (15)`, `O.general Max(35)`, `o.especifico max(2 lineas)`, `(en/de)`, las `x` sueltas).                                                                                                                                                                  |
| **E-12**  | Numérales sin desarrollar completados o consolidados.                                                                                                                                                                                                                                                                           |
| **E-13**  | Añadidos conectores académicos entre párrafos (*en primer lugar, por otra parte, en consecuencia, así pues, con base en lo anterior*).                                                                                                                                                                                          |
| **E-14**  | Siglas normalizadas: `Panda Estampados / Kitsune` (primera mención), `ECM-PEK v1.0.0` (forma corta en lo sucesivo), `Mipymes` (micro y pequeñas empresas).                                                                                                                                                                     |
| **E-15**  | Mayúsculas normalizadas: solo nombres propios (Panda Estampados, Kitsune, PostgreSQL, Laravel, Astro, React, Caddy, Nginx, Scrum), acrónimos (API, MVC, SQL, ORM, 2FA, SEO, TLS, Mipymes, CI/CD) y la primera palabra de la sigla ECM-PEK.                                                                                       |
| **E-16**  | Reescritura en tono neutro, descriptivo y técnico. Eliminados los términos publicitarios ("vitrina digital", "experiencia de compra del visitante", "vanguardia tecnológica").                                                                                                                                                  |
| **E-17**  | Añadidas citas APA 7 en línea en afirmaciones fácticas y doctrinales. Cuando la fuente no se pudo verificar, se marcó como `[CITA PENDIENTE]`.                                                                                                                                                                                    |
| **E-18**  | Kubernetes se reubicó como **proyección futura** en la sección 4.4; el alcance del Avance 1 es **Docker Compose**.                                                                                                                                                                                                               |
| **E-19**  | GitHub Actions se mencionó como práctica de DevOps **prevista** para los siguientes avances, no como funcionalidad ya implementada.                                                                                                                                                                                            |
| **E-20**  | "Especificación Técnica" consolidada: en Marco Teórico (cap. VI) van los conceptos; en Justificación (cap. IV) va el alcance propio del proyecto.                                                                                                                                                                                 |
| **E-21**  | "Etapa Actual" redactada como **fase de protocolo/planificación**, no como Sprint 0 ejecutado. Los sprints se describirán cuando haya avances.                                                                                                                                                                                    |

### B. Huecos a completar antes de la entrega definitiva

1. **Citas pendientes** (verificar en fuentes reales):
   - Guo, Liu y Wang (2017) sobre *multi-tenant application development framework*.
   - Lao et al. (2024) sobre adopción de e-commerce en Mipymes de Managua.
   - Pérez (2024) sobre sistema web de inventarios en pequeña empresa comercial (UNI).
   - The Linux Foundation (2024), *State of Multi-Tenant SaaS Architectures*.
   - Pasarela de pago seleccionada (especificar proveedor, *endpoint*, documentación).
   - OAuth 2.0 / OpenID Connect: confirmar si se implementará o queda como mejora.

2. **Datos pendientes** (obtener en el trabajo de campo):
   - Volumen aproximado de ventas mensuales de Panda Estampados y Kitsune.
   - Número de clientes activos en los últimos 12 meses.
   - Tamaño del catálogo de productos por marca.
   - Número de pedidos mensuales promedio.
   - Resultados consolidados de la guía de observación y de la entrevista (Anexos A y B).
   - Resultados de la encuesta a clientes (Anexo C).

3. **Anexos por recolectar**:
   - Guía de observación aplicada y firmada por el observador.
   - Transcripción de la(s) entrevista(s) con los propietarios.
   - Capturas de pantalla del *storefront* y del panel administrativo del ECM-PEK v1.0.0.
   - *Wireframes* de baja y alta fidelidad.
   - URL pública del repositorio Git.

4. **Ajustes de fechas del cronograma**:
   - Las fechas reales del curso (inicio y fin de semestre) deben sustituir los meses genéricos (Mes 1, Mes 2, …, Mes 6).

### C. Recomendaciones puntuales de redacción

- Mantener el verbo en infinitivo al inicio de cada objetivo específico; los verbos más adecuados en ingeniería de software son *desarrollar, diseñar, implementar, evaluar, diagnosticar, determinar, analizar, proponer*.
- Evitar el uso de "vitrina", "experiencia de compra del visitante" y otros términos de marketing en el cuerpo de la monografía; reservar esos términos, si los requiere, para la sección de presentación o para la defensa oral.
- No usar la palabra "general" en el objetivo general: por convención académica, se escribe "Objetivo General" en el encabezado y el verbo principal en el cuerpo.
- Cuando se cite textualmente un autor, agregar el número de página: (Sommerville, 2011, p. 45).
- En las figuras, tablas y listados, agregar siempre una leyenda y la fuente (por ejemplo: *Fuente: elaboración propia con base en The PostgreSQL Global Development Group, 2026*).
- Antes de imprimir, verificar que la versión final del documento coincida con la versión del repositorio de la monografía.

### D. Sobre la extensión total del documento

El borrador académico actual ocupa aproximadamente **8 500 palabras** (sin contar esta sección de notas), lo que en interlineado 1.5 y letra Times New Roman / Arial 12 produce alrededor de **23–25 páginas**. El presupuesto oficial del profesor es de **22 páginas** (suma de las extensiones máximas por capítulo). Si la entrega final del Avance 1 debe ajustarse estrictamente a 22 páginas, las dos palancas más efectivas son:

- Reducir el **capítulo II. Antecedentes** a 4 páginas exactas, consolidando los 12 antecedentes en 8–10 (priorizando los internacionales verificables).
- Compactar el **capítulo VI. Marco Teórico** a 4 páginas exactas, reduciendo las descripciones de 6.2.4 (extensiones de PostgreSQL), 6.2.10 (Docker) y 6.2.14 (SEO/HTTPS/UX-UI) a una sola línea cada una.

Ambas reducciones están contempladas en este borrador, de modo que el documento final puede ajustarse sin perder los conceptos ni las citas requeridas.

---

*Fin del protocolo — Avance 1 (v1.0.0).*
