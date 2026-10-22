#!/usr/bin/env python3
"""
Diagrama Entidad-Relacion en notacion CHEN  —  AtlasCommerce

Primitivas de Chen:
  ENTIDAD   -> rectangulo
  ATRIBUTO  -> elipse (clave primaria subrayada, convencion de Chen)
  RELACION  -> rombo
  CARDINALIDAD -> (min,max) sobre cada arista entidad-relacion

Notas de layout:
  * Los rombos NO se fijan en el punto medio de la arista: se busca
    greedily la primera posicion libre probando fracciones del segmento
    y desplazamientos perpendiculares.
  * El desplazamiento horizontal de cada atributo se calcula POR
    ENTIDAD a partir de su atributo mas ancho, de modo que el abanico
    de dos entidades vecinas en la misma fila nunca se solape.
  * El lienzo se auto-ajusta al contenido y se estira al ratio A4
    vertical (1.375).
"""
from __future__ import annotations
import html
import sys
from dataclasses import dataclass

# ---------------------------------------------------------------- modelo

@dataclass
class Attr:
    name: str
    key: bool = False

@dataclass
class Entity:
    name: str
    table: str
    attrs: list
    x: float
    y: float

@dataclass
class Rel:
    name: str
    a: str
    b: str
    ma: str
    mb: str
    self_ref: bool = False

# Rejilla de 3 columnas x 6 filas. Las columnas siguen el dominio:
# izq. multitenant/cliente, centro identidad/pedido, der. catalogo.
COL_X = [350, 910, 1470]
ROW_Y = [150, 545, 940, 1335, 1730, 2125]

SPEC = [
    # (nombre, tabla, atributos, col, fila)
    ("TIENDA",            "stores",             'Attr("id",1),Attr("nombre"),Attr("slug"),Attr("tasa_impuesto")', 0, 0),
    ("ROL",               "roles",              'Attr("id",1),Attr("nombre"),Attr("slug"),Attr("activo")', 1, 0),
    ("CATEGORIA",         "categories",         'Attr("id",1),Attr("categoria_padre_id"),Attr("nombre"),Attr("slug"),Attr("activo")', 2, 0),
    ("TIENDA_DOMINIO",    "store_domains",      'Attr("id",1),Attr("tienda_id"),Attr("dominio"),Attr("tipo"),Attr("es_principal")', 0, 1),
    ("USUARIO",           "users",              'Attr("id",1),Attr("rol_id"),Attr("nombre"),Attr("correo"),Attr("bloqueado_hasta")', 1, 1),
    ("MARCA",             "brands",             'Attr("id",1),Attr("nombre"),Attr("slug"),Attr("url_logo")', 2, 1),
    ("CLIENTE",           "customers",          'Attr("id",1),Attr("usuario_id"),Attr("tipo_cliente"),Attr("correo"),Attr("documento_identidad")', 0, 2),
    ("PRODUCTO",          "products",           'Attr("id",1),Attr("categoria_id"),Attr("marca_id"),Attr("codigo"),Attr("precio_base")', 1, 2),
    ("PRODUCTO_VARIANTE", "product_variants",   'Attr("id",1),Attr("producto_id"),Attr("talla_id"),Attr("codigo_barras"),Attr("precio_centavos")', 2, 2),
    ("CLIENTE_DIRECCION", "customer_addresses", 'Attr("id",1),Attr("cliente_id"),Attr("tipo_direccion"),Attr("direccion_detallada"),Attr("es_principal")', 0, 3),
    ("INVENTARIO",        "inventories",        'Attr("id",1),Attr("almacen_id"),Attr("variante_id"),Attr("stock_actual"),Attr("stock_minimo")', 1, 3),
    ("PEDIDO",            "orders",             'Attr("id",1),Attr("cliente_id"),Attr("tienda_id"),Attr("numero_pedido"),Attr("estado")', 0, 4),
    ("FACTURA",           "invoices",           'Attr("id",1),Attr("pedido_id"),Attr("tienda_id"),Attr("numero_factura"),Attr("estado_factura")', 1, 4),
    ("PAGO",              "payments",           'Attr("id",1),Attr("tienda_id"),Attr("cliente_id"),Attr("monto"),Attr("estado")', 2, 4),
    ("DETALLE_PEDIDO",    "order_items",        'Attr("id",1),Attr("pedido_id"),Attr("variante_id"),Attr("cantidad"),Attr("precio_unitario")', 0, 5),
    ("ENVIO",             "shipments",          'Attr("id",1),Attr("pedido_id"),Attr("codigo_seguimiento"),Attr("costo_envio"),Attr("estado")', 1, 5),
]

ENTITIES = [Entity(n, t, list(eval("[" + a + "]")), COL_X[c], ROW_Y[r])
            for n, t, a, c, r in SPEC]

RELS = [
    Rel("resuelve_dominio",   "TIENDA", "TIENDA_DOMINIO",      "(0,N)", "(1,1)"),
    Rel("asigna_rol",         "ROL", "USUARIO",               "(0,N)", "(1,1)"),
    Rel("clasifica",          "CATEGORIA", "PRODUCTO",         "(0,N)", "(1,1)"),
    Rel("marca_producto",     "MARCA", "PRODUCTO",             "(0,N)", "(0,1)"),
    Rel("autentica",          "USUARIO", "CLIENTE",            "(0,1)", "(0,1)"),
    Rel("registra_direccion", "CLIENTE", "CLIENTE_DIRECCION",  "(0,N)", "(1,1)"),
    Rel("envia_a",            "CLIENTE_DIRECCION", "PEDIDO",  "(0,N)", "(0,1)"),
    Rel("posee_variante",     "PRODUCTO", "PRODUCTO_VARIANTE", "(0,N)", "(1,1)"),
    Rel("controla_stock",     "PRODUCTO", "INVENTARIO",        "(0,N)", "(1,1)"),
    Rel("contiene_linea",     "PEDIDO", "DETALLE_PEDIDO",      "(0,N)", "(1,1)"),
    Rel("genera_envio",       "PEDIDO", "ENVIO",               "(0,N)", "(1,1)"),
    Rel("subcategoriza",      "CATEGORIA", "CATEGORIA",        "(0,N)", "(0,1)", True),
    Rel("realiza_pedido",     "CLIENTE", "PEDIDO",             "(0,N)", "(1,1)"),
    Rel("factura_pedido",     "PEDIDO", "FACTURA",             "(0,N)", "(0,1)"),
    Rel("delimita_pedidos",   "TIENDA", "PEDIDO",              "(0,N)", "(1,1)"),
    Rel("crea_pedido",        "USUARIO", "PEDIDO",             "(0,N)", "(1,1)"),
    Rel("figura_en_pedido",   "PRODUCTO", "DETALLE_PEDIDO",    "(0,N)", "(1,1)"),
    Rel("variante_en_pedido", "PRODUCTO_VARIANTE", "DETALLE_PEDIDO", "(0,N)", "(0,1)"),
    Rel("recibe_factura",     "CLIENTE", "FACTURA",            "(0,N)", "(1,1)"),
    Rel("emite_factura",      "USUARIO", "FACTURA",            "(0,N)", "(1,1)"),
    Rel("realiza_pago",       "CLIENTE", "PAGO",               "(0,N)", "(1,1)"),
    Rel("delimita_pagos",     "TIENDA", "PAGO",                "(0,N)", "(1,1)"),
    Rel("cubre",              "PAGO", "FACTURA",               "(0,N)", "(0,N)"),
    Rel("delimita_facturas",  "TIENDA", "FACTURA",             "(0,N)", "(1,1)"),
    Rel("stock_por_variante", "PRODUCTO_VARIANTE", "INVENTARIO", "(0,N)", "(0,1)"),
]

# ----------------------------------------------------------- parametros

EW, EH    = 200, 70
A_DX_BASE = 14          # aire entre el borde de la entidad y la elipse
A_DY      = 52
A_H       = 40
R_H       = 52
RATIO     = 297.0/210.0 # A4 vertical exacto (297mm x 210mm)
PAD       = 46

def ell_w(t): return max(104.0, len(t) * 6.15 + 30)
def dia_w(t): return max(132.0, len(t) * 6.05 + 42)
def esc(s):   return html.escape(s, quote=True)
def tw(t):    return len(t) * 6.2

def aabb(cx, cy, w, h): return (cx - w/2, cy - h/2, cx + w/2, cy + h/2)
def overlap(p, q):      return not (p[2] <= q[0] or q[2] <= p[0] or p[3] <= q[1] or q[3] <= p[1])

# ------------------------------------------------------------- colocacion

def build():
    E    = {e.name: (e.x, e.y) for e in ENTITIES}
    occ  = []
    attr_pos = {}

    for e in ENTITIES:
        occ.append(aabb(e.x, e.y, EW, EH))
        widest = max(ell_w(a.name) for a in e.attrs)
        adx    = EW/2 + A_DX_BASE + widest/2
        n      = len(e.attrs)
        left   = (n + 1) // 2
        slots  = []
        for i in range(left):
            slots.append((-1, (i - (left - 1)/2.0) * A_DY, e.attrs[i]))
        rest = n - left
        for i in range(rest):
            slots.append((+1, (i - (rest - 1)/2.0) * A_DY, e.attrs[left + i]))
        attr_pos[e.name] = (adx, slots)
        for side, off, at in slots:
            occ.append(aabb(e.x + side*adx, e.y + off, ell_w(at.name), A_H))

    # rombos: primero los mas largos, que son los mas restringidos.
    # Un candidato solo se acepta si el ROMBO y las dos etiquetas de
    # cardinalidad quedan libres; si no, se prueba otra posicion.
    def span(r):
        if r.self_ref: return 0.0
        return (((E[r.a][0]-E[r.b][0])**2 + (E[r.a][1]-E[r.b][1])**2) ** .5)

    CHIP_D = (24, 48, 74, 102, 134)      # distancias probadas para la etiqueta

    def chip_at(ent, dcx, dcy, txt, d):
        """Caja de la etiqueta de cardinalidad pegada a `ent` mirando a (dcx,dcy)."""
        ex, ey = E[ent]
        dxv, dyv = ex - dcx, ey - dcy
        L = (dxv*dxv + dyv*dyv) ** .5 or 1.0
        ux, uy = dxv/L, dyv/L
        return aabb(ex - ux*(EW/2 + d), ey - uy*(EH/2 + d*0.72), tw(txt)+6, 15)

    def free(boxes):
        return all(not overlap(b, o) for b in boxes for o in occ)

    dias = []
    chipd = {}
    fallback = []
    for r in sorted(RELS, key=lambda r: -span(r)):
        w, h = dia_w(r.name), R_H
        x1, y1 = E[r.a]
        x2, y2 = E[r.b]
        placed = None
        if r.self_ref:
            for dx, dy in ((0,-180), (150,-150), (-150,-150), (0,-120), (0,180),
                           (170,-60), (-170,-60), (170,60), (-170,60)):
                cx, cy = x1 + dx, y1 + dy
                box = aabb(cx, cy, w, h)
                # las dos cardinalidades van apiladas sobre el arco
                c1 = (cx, cy - h/2 - 16, tw(r.ma)+6, 15)
                c2 = (cx, cy - h/2 - 34, tw(r.mb)+6, 15)
                if free([box, c1, c2]) and not overlap(c1, c2):
                    placed = (cx, cy); break
        else:
            dxv, dyv = x2 - x1, y2 - y1
            L  = (dxv*dxv + dyv*dyv) ** .5 or 1.0
            px, py = -dyv/L, dxv/L
            for t in (.5, .42, .58, .34, .66, .26, .74, .2, .8, .14, .86):
                for perp in (0, -50, 50, -75, 75, -100, 100, -125, 125, -155, 155,
                                 -185, 185, -220, 220, -260, 260, -300, 300):
                    cx = x1 + dxv*t + px*perp
                    cy = y1 + dyv*t + py*perp
                    if not free([aabb(cx, cy, w, h)]):
                        continue
                    # probar combinaciones de distancia para las 2 etiquetas
                    for da in CHIP_D:
                        for db in CHIP_D:
                            ca = chip_at(r.a, cx, cy, r.ma, da)
                            cb = chip_at(r.b, cx, cy, r.mb, db)
                            if free([ca, cb]) and not overlap(ca, cb):
                                placed = (cx, cy)
                                chipd[r.name] = (da, db)
                                break
                        if placed: break
                    if placed: break
                if placed: break
        if not placed:
            fallback.append(r.name)
            dxv, dyv = x2 - x1, y2 - y1
            placed = (x1 + dxv*.5, y1 + dyv*.5)
            chipd[r.name] = (CHIP_D[0], CHIP_D[0])
        cx, cy = placed
        occ.append(aabb(cx, cy, w, h))
        if r.self_ref:
            occ.append(aabb(cx, cy - h/2 - 25, max(tw(r.ma), tw(r.mb)) + 6, 48))
        else:
            da, db = chipd[r.name]
            for ent, txt, d in ((r.a, r.ma, da), (r.b, r.mb, db)):
                occ.append(chip_at(ent, cx, cy, txt, d))
        dias.append((r, cx, cy, w, h))

    return E, attr_pos, dias, chipd, fallback

# -------------------------------------------------------------------- svg

def render(E, attr_pos, dias, chipd):
    shapes = ([aabb(e.x, e.y, EW, EH) for e in ENTITIES]
              + [aabb(e.x + side*attr_pos[e.name][0], e.y + off, ell_w(a.name), A_H)
                 for e in ENTITIES for side, off, a in attr_pos[e.name][1]]
              + [aabb(cx, cy, w, h) for _, cx, cy, w, h in dias])
    xs = [b[0] for b in shapes] + [b[2] for b in shapes]
    ys = [b[1] for b in shapes] + [b[3] for b in shapes]
    minx, maxx, miny, maxy = min(xs), max(xs), min(ys), max(ys)
    cw, ch = maxx - minx + 2*PAD, maxy - miny + 2*PAD
    W = max(cw, ch/RATIO)
    H = max(ch, cw*RATIO)
    ox = (W - cw)/2 - minx
    oy = (H - ch)/2 - miny
    def T(x, y): return (x + ox, y + oy)

    # width/height en mm = A4 real. El viewBox en px conserva la escala, asi
    # que el SVG imprime de borde a borde y rsvg-convert -f pdf ya no recorta.
    o = [f'<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" '
         f'viewBox="0 0 {W:.1f} {H:.1f}">']
    o.append("""<style>
 .bg{fill:#fff}
 .ent{fill:#fff;stroke:#14213d;stroke-width:2.2}
 .entt{fill:#14213d;font:700 14px 'DejaVu Sans',Verdana,sans-serif;text-anchor:middle}
 .esub{fill:#7a8194;font:400 9px 'DejaVu Sans',Verdana,sans-serif;text-anchor:middle}
 .att{fill:#eaf1ff;stroke:#3b5f9e;stroke-width:1.2}
 .attt{fill:#16305c;font:400 11px 'DejaVu Sans',Verdana,sans-serif;text-anchor:middle}
 .keyt{font-weight:700}
 .rel{fill:#fff4e2;stroke:#a8620a;stroke-width:1.5}
 .relt{fill:#7a4a06;font:500 10.5px 'DejaVu Sans',Verdana,sans-serif;text-anchor:middle}
 .edge{stroke:#45454d;stroke-width:1.15;fill:none}
 .card{fill:#111827;font:700 10.5px 'DejaVu Sans',Verdana,sans-serif;text-anchor:middle}
</style>""")
    o.append(f'<rect class="bg" x="0" y="0" width="{W:.1f}" height="{H:.1f}"/>')

    _o = o.append
    def chip(lx, ly, txt):
        _o(f'<rect x="{lx-tw(txt)/2-3:.1f}" y="{ly-10:.1f}" width="{tw(txt)+6:.1f}" height="15" fill="#fff"/>')
        _o(f'<text class="card" x="{lx:.1f}" y="{ly+1.5:.1f}">{esc(txt)}</text>')

    # aristas atributo <-> entidad
    for e in ENTITIES:
        adx, slots = attr_pos[e.name]
        for side, off, at in slots:
            ax, ay = T(e.x + side*adx, e.y + off)
            sx, sy = T(e.x + side*(EW/2), e.y)
            ex = ax - side*ell_w(at.name)/2 + side*3
            o.append(f'<line class="edge" x1="{sx:.1f}" y1="{sy:.1f}" x2="{ex:.1f}" y2="{ay:.1f}"/>')

    # aristas relacion <-> entidad
    for r, cx, cy, w, h in dias:
        dx, dy = T(cx, cy)
        if r.self_ref:
            # el bucle se ancla al borde superior o inferior segun donde
            # caiga el rombo
            ex, ey = T(E[r.a][0], E[r.a][1])
            sx = ex
            sy = ey - EH/2 if dy < ey else ey + EH/2
            lx = dx - w/2
            o.append(f'<path class="edge" d="M {sx:.1f} {sy:.1f} L {lx:.1f} {dy:.1f} '
                     f'A {w/2:.1f} {h/2:.1f} 0 0 1 {dx+w/2:.1f} {dy:.1f} '
                     f'A {w/2:.1f} {h/2:.1f} 0 0 1 {lx:.1f} {dy:.1f} '
                     f'L {sx:.1f} {sy:.1f}"/>')
            chip(dx, dy - h/2 - 16, r.ma)
            chip(dx, dy - h/2 - 34, r.mb)
            continue
        for ent, sign in ((r.a, +1), (r.b, -1)):
            x1, y1 = T(cx, cy)
            x2, y2 = T(*E[ent])
            dxv, dyv = x2 - x1, y2 - y1
            L = (dxv*dxv + dyv*dyv) ** .5 or 1.0
            ux, uy = dxv/L*sign, dyv/L*sign
            o.append(f'<line class="edge" x1="{x1+ux*(w/2):.1f}" y1="{y1+uy*(h/2):.1f}" '
                     f'x2="{x2-ux*(EW/2):.1f}" y2="{y2-uy*(EH/2):.1f}"/>')

    # cardinalidades
    for r, cx, cy, w, h in dias:
        dx, dy = T(cx, cy)
        if r.self_ref:
            continue          # ya dibujadas junto al arco
        da, db = chipd[r.name]
        for ent, txt, sign, d in ((r.a, r.ma, +1, da), (r.b, r.mb, -1, db)):
            x1, y1 = T(cx, cy)
            x2, y2 = T(*E[ent])
            dxv, dyv = x2 - x1, y2 - y1
            L = (dxv*dxv + dyv*dyv) ** .5 or 1.0
            ux, uy = dxv/L*sign, dyv/L*sign
            chip(x2 - ux*(EW/2 + d), y2 - uy*(EH/2 + d*0.72), txt)

    for r, cx, cy, w, h in dias:
        dx, dy = T(cx, cy)
        o.append(f'<polygon class="rel" points="{dx:.1f},{dy-h/2:.1f} {dx+w/2:.1f},{dy:.1f} {dx:.1f},{dy+h/2:.1f} {dx-w/2:.1f},{dy:.1f}"/>')
        o.append(f'<text class="relt" x="{dx:.1f}" y="{dy+3.5:.1f}">{esc(r.name)}</text>')

    for e in ENTITIES:
        x, y = T(e.x, e.y)
        o.append(f'<rect class="ent" x="{x-EW/2:.1f}" y="{y-EH/2:.1f}" width="{EW}" height="{EH}" rx="2"/>')
        o.append(f'<text class="entt" x="{x:.1f}" y="{y-4:.1f}">{esc(e.name)}</text>')
        o.append(f'<text class="esub" x="{x:.1f}" y="{y+13:.1f}">{esc(e.table)}</text>')

    for e in ENTITIES:
        adx, slots = attr_pos[e.name]
        for side, off, at in slots:
            x, y = T(e.x + side*adx, e.y + off)
            w = ell_w(at.name)
            o.append(f'<ellipse class="att" cx="{x:.1f}" cy="{y:.1f}" rx="{w/2:.1f}" ry="{A_H/2:.1f}"/>')
            if at.key:
                o.append(f'<text class="attt keyt" x="{x:.1f}" y="{y+4:.1f}">{esc(at.name)}</text>')
                o.append(f'<line x1="{x-tw(at.name)/2:.1f}" y1="{y+7:.1f}" x2="{x+tw(at.name)/2:.1f}" y2="{y+7:.1f}" stroke="#16305c" stroke-width="1.1"/>')
            else:
                o.append(f'<text class="attt" x="{x:.1f}" y="{y+4:.1f}">{esc(at.name)}</text>')

    o.append("</svg>")
    return "\n".join(o), W, H, shapes

if __name__ == "__main__":
    E, ap, dias, chipd, fallback = build()
    svg, W, H, shapes = render(E, ap, dias, chipd)
    out = sys.argv[1] if len(sys.argv) > 1 else "/tmp/opencode/chen/er.svg"
    open(out, "w").write(svg)
    print(f"entidades={len(ENTITIES)} relaciones={len(RELS)} formas={len(shapes)}")
    print(f"canvas={W:.0f}x{H:.0f} ratio={H/W:.3f} -> {out}")
    print(f"sin posicion libre (fallback): {len(fallback)} {fallback if fallback else ''}")