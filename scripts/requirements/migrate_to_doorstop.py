#!/usr/bin/env python3
"""
Migra los seeds JSON (stakeholders + RF + RNF) a archivos .yml en
formato Doorstop.

Doorstop es 100% scriptable, sin UI: los requirements viven como
archivos YAML versionados en git, y `doorstop` genera reportes HTML/PDF
de traceability.

USO:
  cd scripts/requirements
  source .venv/bin/activate
  python migrate_to_doorstop.py
  doorstop                # valida los requirements
  doorstop publish all docs/requirements  # genera HTML de traceability
"""

from pathlib import Path
import json
import shutil

HERE = Path('.').resolve()
SEEDS = {
    "stakeholders": HERE / "seed_stakeholders.json",
    "rf": HERE / "seed_rf.json",
    "rnf": HERE / "seed_rnf.json",
}
REQ_DIR = HERE / "req"
DOC_DIR = HERE / "docs" / "requirements"

PRIORITY_MAP = {"alta": "H", "media": "M", "baja": "L"}
STATUS_MAP = {
    "pendiente": "pending",
    "en_analisis": "in-analysis",
    "aprobado": "approved",
    "implementado": "implemented",
    "verificado": "verified",
}


def role_value(value):
    return "developer" if str(value).lower() == "si" else ""


def write_yml(path, uid, data, comment=""):
    path.parent.mkdir(parents=True, exist_ok=True)
    lines = []
    if comment:
        for line in comment.split("\n"):
            lines.append(f"# {line}" if line else "#")
    lines.append(f"{uid}:")
    for k, v in data.items():
        if isinstance(v, list):
            if not v:
                lines.append(f"  {k}: []")
            else:
                lines.append(f"  {k}:")
                for item in v:
                    lines.append(f"    - {item}")
        elif isinstance(v, bool):
            lines.append(f"  {k}: {'true' if v else 'false'}")
        else:
            sv = str(v).replace("|", "\\|").replace('"', '\\"')
            lines.append(f'  {k}: "{sv}"')
    path.write_text("\n".join(lines) + "\n")


def main():
    seeds = {k: json.loads(p.read_text()) for k, p in SEEDS.items()}

    if REQ_DIR.exists():
        shutil.rmtree(REQ_DIR)
    REQ_DIR.mkdir(parents=True)

    # 1. Stakeholders (UIDs: stakeholder_001, ...)
    name_to_uid = {}
    for i, s in enumerate(seeds["stakeholders"], start=1):
        uid = f"stakeholder_{i:03d}"
        name_to_uid[s["__key"]] = uid
        data = {
            "name": s["participante"],
            "title": s.get("rol", ""),
            "role": role_value(s.get("es_desarrollador", "no")),
            "organization": s.get("organizacion", ""),
            "notes": s.get("comentarios", ""),
        }
        fname = f"{uid}_{s['__key']}.yml"
        write_yml(
            REQ_DIR / fname,
            uid,
            data,
            f"Stakeholder: {s['participante']}\nGenerado por migrate_to_doorstop.py",
        )

    # 2. RF (UIDs: rf_001, ...)
    for i, rf in enumerate(seeds["rf"], start=1):
        uid = f"rf_{i:03d}"
        verified_by = [
            name_to_uid[k]
            for k in rf.get("stakeholders", [])
            if k in name_to_uid
        ]
        data = {
            "title": rf.get("titulo", rf.get("codigo", "")),
            "rationale": rf.get("descripcion", ""),
            "priority": PRIORITY_MAP.get(rf.get("prioridad", "").lower(), "M"),
            "status": STATUS_MAP.get(rf.get("estado", "").lower(), "pending"),
            "module": rf.get("modulo", ""),
            "code": rf.get("codigo", ""),
            "verified-by": verified_by,
            # DP1=A: 4 new REM 1.2.2 fields with sensible defaults.
            # `version` defaults to "1.0"; `autores` is the stakeholder UIDs
            # already resolved via name_to_uid; `fuentes`/`dependencias` mirror
            # the seed JSON arrays (empty list when seed omits them).
            "version": rf.get("version", "1.0"),
            "autores": [
                name_to_uid[k]
                for k in rf.get("autores", [])
                if k in name_to_uid
            ],
            "fuentes": list(rf.get("fuentes", [])),
            "dependencias": list(rf.get("dependencias", [])),
            # `estabilidad` is derived from `verifiedBy`: at least one
            # verifier => "alta"; otherwise "PD" (Por Definir).
            "estabilidad": "alta" if verified_by else "PD",
        }
        fname = f"{uid}_{rf['__key']}.yml"
        write_yml(
            REQ_DIR / fname,
            uid,
            data,
            f"RF: {rf.get('codigo', '')} - {rf.get('titulo', '')}\nGenerado por migrate_to_doorstop.py",
        )

    # 3. RNF (UIDs: rnf_001, ...)
    for i, rnf in enumerate(seeds["rnf"], start=1):
        uid = f"rnf_{i:03d}"
        data = {
            "title": rnf.get("titulo", rnf.get("codigo", "")),
            "rationale": rnf.get("descripcion", ""),
            "priority": PRIORITY_MAP.get(rnf.get("prioridad", "").lower(), "M"),
            "status": STATUS_MAP.get(rnf.get("estado", "").lower(), "pending"),
            "category": rnf.get("categoria", ""),
            "code": rnf.get("codigo", ""),
            # DP1=A: 4 new REM 1.2.2 fields. RNFs carry no verified-by
            # so `estabilidad` is always "PD" until one is assigned.
            "version": rnf.get("version", "1.0"),
            "autores": [
                name_to_uid[k]
                for k in rnf.get("autores", [])
                if k in name_to_uid
            ],
            "fuentes": list(rnf.get("fuentes", [])),
            "dependencias": list(rnf.get("dependencias", [])),
            "estabilidad": rnf.get("estabilidad", "PD"),
        }
        fname = f"{uid}_{rnf['__key']}.yml"
        write_yml(
            REQ_DIR / fname,
            uid,
            data,
            f"RNF: {rnf.get('codigo', '')} - {rnf.get('titulo', '')}\nGenerado por migrate_to_doorstop.py",
        )

    files = list(REQ_DIR.iterdir())
    n_stk = sum(1 for f in files if f.name.startswith("stakeholder_"))
    n_rf = sum(1 for f in files if f.name.startswith("rf_"))
    n_rnf = sum(1 for f in files if f.name.startswith("rnf_"))
    print(f"Migrados: {n_stk} stakeholders, {n_rf} RF, {n_rnf} RNF")
    print(f"Ubicacion: {REQ_DIR}")
    print()
    print("Para validar y generar el reporte de traceability:")
    print("  source .venv/bin/activate")
    print("  doorstop                                # valida")
    print(f"  doorstop publish all {DOC_DIR}    # genera HTML")


if __name__ == "__main__":
    main()
