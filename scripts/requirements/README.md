# scripts/requirements/

Datos de gestion de requisitos del proyecto Panda Estampados + Kitsune
migrados a [Doorstop](https://doorstop.readthedocs.io/) (100% scriptable,
versionado en git, genera reportes HTML/PDF de traceability).

## Estructura

```
scripts/requirements/
├── README.md                    este archivo
├── .doorstop.yml                config de Doorstop
├── migrate_to_doorstop.py       genera req/ desde los seeds JSON
├── seed_stakeholders.json       3 integrantes del equipo Panda+Kitsune
├── seed_rf.json                 48 requisitos funcionales
├── seed_rnf.json                30 requisitos no funcionales
├── req/                         requirements en formato Doorstop (.yml)
│   ├── stakeholder_001_daniel.yml
│   ├── stakeholder_002_andy.yml
│   ├── stakeholder_003_jeremy.yml
│   ├── rf_001_RF-AUTH-001.yml
│   ├── ... (48 RF)
│   └── rnf_001_... (30 RNF)
├── docs/requirements/            reporte HTML generado por Doorstop
│   ├── index.html
│   ├── traceability.html
│   └── traceability.csv
└── .venv/                        venv con doorstop instalado
```

## Uso

### Generar los requirements

```bash
cd scripts/requirements
source .venv/bin/activate
python migrate_to_doorstop.py
```

### Validar la base

```bash
doorstop
```

### Generar reporte HTML de traceability

```bash
doorstop publish all docs/requirements
```

Esto regenera `docs/requirements/` con:
- `index.html` — vista por item
- `traceability.html` — matriz de traceability entre todos los items
- `traceability.csv` — misma matriz en CSV

## Estructura de cada .yml

Doorstop usa el prefijo `REQ` configurado en `.doorstop.yml`. Cada
archivo .yml tiene un UID unico (`stakeholder_001`, `rf_001`, `rnf_001`).
Los links entre items se hacen con campos especiales como `verified-by`,
`satisfies`, `links`.

Ejemplo `req/rf_001_RF-AUTH-001.yml`:

```yaml
rf_001:
  title: "Login con email y contraseña"
  rationale: "El usuario puede autenticarse en la tienda..."
  priority: "H"
  status: "implemented"
  module: "Autenticacion"
  code: "RF-AUTH-001"
  links: []
  verified-by:
    - stakeholder_001
```

## Regenerar todo desde cero

```bash
python migrate_to_doorstop.py   # borra req/ y regenera los .yml
doorstop                        # valida
doorstop publish all docs/requirements  # regenera docs/
```

Los `seed_*.json` son la fuente de verdad y no se tocan.

## Por que Doorstop (no REM / Tuleap)

- **100% scriptable**: todo son archivos .yml en git
- **Sin UI**: cero clicks, todo desde CLI
- **Versionado**: cada change de un requirement es un commit
- **Reportes automaticos**: traceability HTML/CSV
- **Cero infra**: no requiere DB ni servidor

REM era de 2004, requeria Wine/OTVDM, tenia UI fragil y no permitia
scripting. Tuleap era mas moderno pero su REST API no soportaba
crear trackers programaticamente. Doorstop evita ambos problemas.

## Para visualizar el reporte

Una vez generados los HTML en `docs/requirements/`:

```bash
xdg-open docs/requirements/index.html
# o servir con python:
cd docs/requirements && python3 -m http.server 8000
# luego abrir http://localhost:8000/
```
