# Política de Seguridad — AtlasCommerce

> Cómo reportar vulnerabilidades de forma responsable.
> El reporte responsable protege a los tenants (Panda Estampados, Kitsune
> Store, MM Boutique, Morales Store) y a sus clientes.

## Versiones soportadas

Solo la rama `master` (rama de release estable) recibe parches de
seguridad. Las ramas de feature (`feat/*`) y los tags experimentales se
consideran código en desarrollo y NO son soportadas para disclosure
coordinado.

| Rama / Versión | Soporte           |
| -------------- | ----------------- |
| `master`       | ✅ Parches activos |
| `feat/*`       | ❌ Sin soporte     |
| Tags antiguos  | ❌ Sin soporte     |

## Reporte de vulnerabilidades

**NO** abras un issue público ni un PR con detalles de una vulnerabilidad.
Esto daría ventaja a un atacante antes de que exista un parche.

En su lugar, reportá de forma privada a través de uno de estos canales:

- **Email:** `security@atlas-commerce.dev`
  Asunto sugerido: `[SECURITY] <resumen breve>`
- **GitHub Security Advisories:** desde la pestaña **Security** del
  repositorio → **Report a vulnerability** (Disclosure privado,
  cifrado en tránsito).
- **Contacto directo** con el mantenedor: `@D4nitrix13` vía DM en GitHub,
  solo para triage inicial.

### Qué incluir en el reporte

Cuanto más contexto, mejor — ayudá a reproducir y a priorizar:

1. **Tipo de vulnerabilidad** (SQLi, XSS, auth bypass, RCE, etc.).
2. **Componente afectado** (ruta del archivo, servicio, endpoint).
3. **Pasos para reproducir** o PoC (cuanto más concreto, mejor).
4. **Impacto observado** (qué datos se exponen, qué se rompe).
5. **Entorno**: branch, commit SHA, versión de PHP/Laravel/Postgres, modo
   (dev / staging / prod).
6. **Severidad estimada** (CVSS si lo calculaste).

## Tiempos de respuesta

| Etapa | Compromiso |
| ----- | ---------- |
| Acuse de recibo del reporte | 48 horas hábiles |
| Triage inicial + plan de mitigación | 5 días hábiles |
| Parche para vulnerabilidades de severidad alta/crítica | ASAP, dentro de un sprint |
| Parche para severidad media/baja | próximo ciclo de release |
| Divulgación pública (CVE + advisory) | coordinada, ~90 días después del parche o por acuerdo |

Estos plazos son **objetivos**, no garantías formales. En incidentes
activos ajustamos el calendario.

## Proceso de divulgación

Seguimos el modelo de **coordinated disclosure**:

1. Reporte privado → triage del mantenedor.
2. Reproducción y análisis de impacto.
3. Desarrollo de parche en rama privada.
4. Coordinación con el reportador para validar el fix.
5. Merge a `master` + release con CVE assigned.
6. Anuncio público en un advisory de GitHub, con crédito al reportador
   (si lo desea).
7. Si la vulnerabilidad es explotada activamente *antes* del parche, se
   podrá emitir un aviso anticipado a tenants afectados.

No hacemos disclosure público de la vulnerabilidad hasta que exista un
parche disponible en `master`.

## Política de reporte seguro

Por tu parte, al reportar:

- No explotés la vulnerabilidad más allá de lo necesario para reproducirla.
- No revelés la vulnerabilidad a terceros antes del aviso público.
- No uses herramientas automatizadas de fuzzing que degraden el servicio.
- Eliminá cualquier artefacto de tu PoC que pueda dejar huella en logs
  de terceros.

## Hallazgos fuera de alcance

Por claridad y para enfocar el esfuerzo:

- Denegación de servicio volumétrica o por saturación de red
  (responsabilidad del WAF / CDN).
- Reportes automatizados de scanners genéricos sin PoC.
- Vulnerabilidades en versiones no soportadas.
- Vulnerabilidades que requieren acceso físico al servidor.
- Problemas de configuración del entorno del usuario final (no del
  código del proyecto).

## Reconocimientos

Las personas que reportan vulnerabilidades responsablemente son listadas
en el aviso público de la CVE (a menos que prefieran el anonimato). La
labor profesional de security research es valorada y agradecida.

## Alcance de los tenants

AtlasCommerce es software multi-tienda. Una vulnerabilidad en el core
afecta potencialmente a las cuatro tiendas. Por eso el proceso de triage
involucra evaluar el impacto tenant por tenant antes del parche.

---

Última actualización: 2026-09-09
