# scripts/requirements/

Fuente de verdad de los requisitos del proyecto Panda Estampados + Kitsune.
Versionada en git como archivos YAML individuales, sin tooling intermedio.

## Estructura

```text
scripts/requirements/
├── README.md            este archivo
├── STAKEHOLDERS.md      tabla de los 3 integrantes del equipo
└── req/                 requirements funcionales y no funcionales (.yml)
    ├── rf_001_RF-AUTH-001.yml    (48 requisitos funcionales)
    ├── ...
    └── rnf_030_RNF-PORT-082.yml  (30 requisitos no funcionales)
```text

Total: **48 RF + 30 RNF = 78 requirements**. Los 3 stakeholders del
equipo se documentan en `STAKEHOLDERS.md` y se referencian desde los
campos `autores` / `verified-by` / `fuentes` de los YAML.

## Formato de cada .yml

Los YAMLs siguen la tabla del reporte de avance (REM 1.2.2): cada
requisito tiene un UID, módulo, categoría, versión, autores, fuentes,
dependencias, descripción (rationale), importancia (priority), urgencia,
estado (status), estabilidad y comentarios.

Ejemplo `req/rf_001_RF-AUTH-001.yml`:

```yaml
# RF: RF-AUTH-001 - Login con email y contrasena
rf_001:
  title: "Login con email y contrasena"
  rationale: "El usuario puede autenticarse en la tienda..."
  priority: "H"
  status: "implemented"
  module: "Autenticacion"
  category: "Identidad y Acceso"
  code: "RF-AUTH-001"
  verified-by:
    - stakeholder_001
  version: "1.0"
  autores:
    - stakeholder_001
  fuentes: []
  dependencias:
    - RF-AUTH-002
  estabilidad: "alta"
  urgencia: "importante"
  comentarios: "Ninguno"
```text

## Categorias

Los **48 RF** se agrupan en 6 categorias funcionales:

| Categoria | Modulos |
| --- | --- |
| Identidad y Acceso | Autenticacion, Multi-Store |
| Gestion de Usuarios | Personalizacion |
| Catalogo y Compras | Catalogo, Carrito, Ventas, Compras |
| Logistica y Post-venta | Envios, Devoluciones, Inventario |
| Transacciones | Pagos, Impuestos, Monedas |
| Plataforma | Sistema, Reportes |

Los **30 RNF** usan categorias ISO 25010 (Seguridad, Mantenibilidad,
Usabilidad, Fiabilidad, Rendimiento, Portabilidad, Observabilidad,
Escalabilidad).

## Campos

| Campo | Descripcion |
| --- | --- |
| `title` | Titulo corto del requisito |
| `rationale` | Descripcion / proposito del requisito |
| `priority` | Importancia: `H` (alta), `M` (media), `L` (baja) |
| `status` | Estado: `pending`, `in-analysis`, `approved`, `implemented`, `verified` |
| `module` | Modulo funcional del RF (no aplica a RNF) |
| `category` | Categoria funcional (RF) o ISO 25010 (RNF) |
| `code` | Codigo legible: `RF-AUTH-001`, `RNF-SEC-010` |
| `verified-by` | Stakeholders UID que verifican (RF) |
| `version` | Version del requisito (`1.0`) |
| `autores` | Stakeholders UID que escribieron |
| `fuentes` | Codigos de historias de usuario fuente (`HU-NN`) |
| `dependencias` | Codigos de otros requisitos prerequisito |
| `estabilidad` | `alta` o `PD` (Por Definir) |
| `urgencia` | `critica`, `importante`, `puede esperar` |
| `comentarios` | Notas libres (default `Ninguno`) |
