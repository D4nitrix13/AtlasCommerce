#!/usr/bin/env python3
"""
Genera un PDF con la presentacion del Avance 2 estilo pizarra de clase:
- Lista numerada con la metodologia (Organizacion, Developers, Customer, User, RF, RNF)
- Tabla de stakeholders estilo REM
- Lista de los 48 RF y 30 RNF

USO:
  python generate_avance2_pdf.py
  # Genera scripts/requirements/Avance2.pdf
"""

from pathlib import Path
import json

HERE = Path('.').resolve()
SEEDS = {
    "stakeholders": HERE / "seed_stakeholders.json",
    "rf": HERE / "seed_rf.json",
    "rnf": HERE / "seed_rnf.json",
}
OUT = HERE / "Avance2.pdf"

from reportlab.lib.pagesizes import letter, A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm, mm
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
)


def main():
    seeds = {k: json.loads(p.read_text()) for k, p in SEEDS.items()}

    doc = SimpleDocTemplate(
        str(OUT), pagesize=A4,
        leftMargin=2*cm, rightMargin=2*cm,
        topMargin=2*cm, bottomMargin=2*cm,
        title="Proyecto Final IS1 - Avance 2",
        author="Daniel - Full Stack",
    )

    styles = getSampleStyleSheet()
    H1 = styles["Heading1"]
    H2 = styles["Heading2"]
    H3 = styles["Heading3"]
    N = styles["Normal"]

    story = []

    # === Portada ===
    story.append(Paragraph("Proyecto Final IS1", H1))
    story.append(Paragraph("Avance 2 — Ingenieria de Requisitos", H2))
    story.append(Spacer(1, 0.5*cm))
    pizarra = (
        "<b>Metodologia (pizarra):</b><br/>"
        "<b>Obligatorio:</b> Project Manager, Analista, Disenador, Customer-Cliente<br/>"
        "<b>No obligatorio:</b> Usuario<br/><br/>"
        "<b>Lista numerada:</b><br/>"
        "1. Organization &nbsp;&nbsp; 4. User<br/>"
        "2. Developers (Integrantes equipo ISI) &nbsp;&nbsp; 5. RF<br/>"
        "3. Customer &nbsp;&nbsp; 6. RNF"
    )
    story.append(Paragraph(pizarra, N))
    story.append(Spacer(1, 0.5*cm))

    # === Tabla de Stakeholders ===
    story.append(Paragraph("Stakeholders del Proyecto", H2))

    rows = [["Participante", "Organizacion", "Rol", "Es dev", "Es cliente", "Es usuario", "Comentarios"]]
    for s in seeds["stakeholders"]:
        rows.append([
            s["participante"],
            s.get("organizacion", ""),
            s.get("rol", ""),
            "Si" if str(s.get("es_desarrollador", "no")).lower() == "si" else "No",
            "Si" if str(s.get("es_cliente", "no")).lower() == "si" else "No",
            "Si" if str(s.get("es_usuario", "no")).lower() == "si" else "No",
            Paragraph((s.get("comentarios", "") or "")[:80] + ("..." if len(s.get("comentarios", "")) > 80 else ""), N),
        ])

    tbl = Table(rows, colWidths=[3.5*cm, 3*cm, 2.8*cm, 1.2*cm, 1.2*cm, 1.2*cm, 4*cm])
    tbl.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#1a5276")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, 0), 9),
        ("FONTSIZE", (0, 1), (-1, -1), 7),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("GRID", (0, 0), (-1, -1), 0.25, colors.grey),
        ("VALIGN", (0, 0), (-1, 0), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 3),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3),
    ]))
    story.append(tbl)

    story.append(PageBreak())

    # === Requisitos Funcionales ===
    story.append(Paragraph(f"Requisitos Funcionales (RF) — {len(seeds['rf'])} items", H2))
    story.append(Spacer(1, 0.3*cm))

    for i, rf in enumerate(seeds["rf"], start=1):
        story.append(Paragraph(
            f"<b>{rf.get('codigo', f'RF-{i:03d}')}</b> — {rf.get('titulo', '')}",
            H3
        ))
        story.append(Paragraph(
            f"<b>Modulo:</b> {rf.get('modulo', 'N/A')} | "
            f"<b>Prioridad:</b> {rf.get('prioridad', 'N/A')} | "
            f"<b>Estado:</b> {rf.get('estado', 'N/A')}",
            N
        ))
        story.append(Paragraph(rf.get("descripcion", ""), N))
        if rf.get("stakeholders"):
            stk_names = ", ".join(
                s["participante"] for s in seeds["stakeholders"]
                if s["__key"] in rf["stakeholders"]
            )
            story.append(Paragraph(f"<b>Stakeholders:</b> {stk_names}", N))
        if rf.get("cumple_rnf"):
            story.append(Paragraph(
                f"<b>Cumple RNF:</b> {', '.join(rf['cumple_rnf'])}",
                N
            ))
        story.append(Spacer(1, 0.2*cm))

    story.append(PageBreak())

    # === Requisitos No Funcionales ===
    story.append(Paragraph(f"Requisitos No Funcionales (RNF) — {len(seeds['rnf'])} items", H2))
    story.append(Spacer(1, 0.3*cm))

    for i, rnf in enumerate(seeds["rnf"], start=1):
        story.append(Paragraph(
            f"<b>{rnf.get('codigo', f'RNF-{i:03d}')}</b> — {rnf.get('titulo', '')}",
            H3
        ))
        story.append(Paragraph(
            f"<b>Categoria ISO 25010:</b> {rnf.get('categoria', 'N/A')} | "
            f"<b>Prioridad:</b> {rnf.get('prioridad', 'N/A')} | "
            f"<b>Estado:</b> {rnf.get('estado', 'N/A')}",
            N
        ))
        story.append(Paragraph(rnf.get("descripcion", ""), N))
        story.append(Spacer(1, 0.2*cm))

    doc.build(story)
    print(f"PDF generado: {OUT}")
    print(f"Size: {OUT.stat().st_size} bytes")


if __name__ == "__main__":
    main()
