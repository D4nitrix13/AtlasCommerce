/**
 * Mock data para el módulo de Support admin.
 *
 * Dos dominios:
 *  - tickets[]   : tickets de soporte tipo Zendesk (12 items)
 *  - reviews[]   : reseñas de productos pendientes de moderación (20 items)
 *
 * Mismo patrón que `inventory-mock.ts` / `marketing-mock.ts`: tipos
 * exportados + arrays congelados con `as const`. Cuando el backend
 * exponga los endpoints `/api/v1/admin/support/*`, los componentes
 * hacen `Astro.fetch()` con fallback a este mock.
 *
 * Ticket status sigue la convención de Zendesk Admin:
 *   open     → recién creado, sin primera respuesta
 *   pending  → esperando respuesta del cliente
 *   hold     → en pausa (admin side, ej. esperando info interna)
 *   solved   → cerrado con resolución
 *   closed   → cerrado sin resolución
 *
 * Ticket messages modelan el thread de conversación: alterna entre
 * `authorRole: 'customer'` y `authorRole: 'admin'`. El componente
 * `TicketThread.astro` los renderiza con alineación y color distintos
 * según el rol.
 *
 * Review status sigue el flujo de moderación estándar:
 *   pending   → recién creada, sin revisar
 *   approved  → aprobada y visible al público
 *   rejected  → rechazada (oculta del storefront)
 *   flagged   → marcada como sospechosa (palabras clave, spam)
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading.
 */

export type TicketStatus = 'open' | 'pending' | 'hold' | 'solved' | 'closed';
export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface TicketMessage {
  readonly id: string;
  readonly authorRole: 'customer' | 'admin';
  readonly authorName: string;
  readonly body: string;
  readonly createdAt: string;
}

export interface SupportTicket {
  readonly id: string;
  readonly ticketNumber: string;
  readonly subject: string;
  readonly customerName: string;
  readonly customerEmail: string;
  readonly status: TicketStatus;
  readonly priority: TicketPriority;
  readonly assignedTo: string | null;
  readonly channel: 'email' | 'web' | 'whatsapp';
  readonly createdAt: string;
  readonly updatedAt: string;
  readonly messages: readonly TicketMessage[];
}

export type ReviewStatus = 'pending' | 'approved' | 'rejected' | 'flagged';

export interface ProductReview {
  readonly id: string;
  readonly productName: string;
  readonly productImage: string;
  readonly customerName: string;
  readonly rating: 1 | 2 | 3 | 4 | 5;
  readonly title: string;
  readonly body: string;
  readonly createdAt: string;
  readonly status: ReviewStatus;
}

export const supportTickets: readonly SupportTicket[] = [
  {
    id: 'tkt-001',
    ticketNumber: '#SUP-1042',
    subject: 'Mi pedido llegó con una camiseta rota',
    customerName: 'Carlos López',
    customerEmail: 'carlos.lopez@example.com',
    status: 'open',
    priority: 'high',
    assignedTo: null,
    channel: 'web',
    createdAt: '2026-09-26T08:14:00Z',
    updatedAt: '2026-09-26T08:14:00Z',
    messages: [
      { id: 'msg-001-1', authorRole: 'customer', authorName: 'Carlos López', body: 'Hola, recibí mi pedido #ORD-1040 y la camiseta viene con un agujero en la manga derecha. Adjunto fotos.', createdAt: '2026-09-26T08:14:00Z' },
    ],
  },
  {
    id: 'tkt-002',
    ticketNumber: '#SUP-1041',
    subject: '¿Hacen envíos a Costa Rica?',
    customerName: 'María Fernández',
    customerEmail: 'm.fernandez@example.com',
    status: 'pending',
    priority: 'low',
    assignedTo: 'Roberto Sánchez',
    channel: 'email',
    createdAt: '2026-09-25T14:22:00Z',
    updatedAt: '2026-09-25T17:45:00Z',
    messages: [
      { id: 'msg-002-1', authorRole: 'customer', authorName: 'María Fernández', body: 'Buenas tardes, ¿tienen envíos hacia San José, Costa Rica? Quiero pedir 5 tazas personalizadas.', createdAt: '2026-09-25T14:22:00Z' },
      { id: 'msg-002-2', authorRole: 'admin', authorName: 'Roberto Sánchez', body: 'Hola María, por el momento solo hacemos envíos dentro de Nicaragua. Pero podemos coordinar un envío por paquetería privada si te interesa. ¿Querés que te cotice?', createdAt: '2026-09-25T17:45:00Z' },
    ],
  },
  {
    id: 'tkt-003',
    ticketNumber: '#SUP-1040',
    subject: 'Cambio de talla sudadera',
    customerName: 'Ana Martínez',
    customerEmail: 'ana.m@example.com',
    status: 'open',
    priority: 'normal',
    assignedTo: null,
    channel: 'whatsapp',
    createdAt: '2026-09-26T07:30:00Z',
    updatedAt: '2026-09-26T07:30:00Z',
    messages: [
      { id: 'msg-003-1', authorRole: 'customer', authorName: 'Ana Martínez', body: 'Compré la sudadera talla L pero me quedó chica. ¿Puedo cambiarla por XL?', createdAt: '2026-09-26T07:30:00Z' },
    ],
  },
  {
    id: 'tkt-004',
    ticketNumber: '#SUP-1039',
    subject: 'Factura duplicada #ORD-1032',
    customerName: 'Roberto Sánchez',
    customerEmail: 'r.sanchez@example.com',
    status: 'hold',
    priority: 'normal',
    assignedTo: 'Laura Díaz',
    channel: 'email',
    createdAt: '2026-09-24T11:00:00Z',
    updatedAt: '2026-09-25T09:30:00Z',
    messages: [
      { id: 'msg-004-1', authorRole: 'customer', authorName: 'Roberto Sánchez', body: 'Me llegaron dos facturas con el mismo número de orden. ¿Es correcto?', createdAt: '2026-09-24T11:00:00Z' },
      { id: 'msg-004-2', authorRole: 'admin', authorName: 'Laura Díaz', body: 'Hola Roberto, tenés razón. Estamos revisando con contabilidad. Te aviso en cuanto tengamos la corrección.', createdAt: '2026-09-24T14:15:00Z' },
      { id: 'msg-004-3', authorRole: 'customer', authorName: 'Roberto Sánchez', body: 'Gracias, quedo atento.', createdAt: '2026-09-25T09:30:00Z' },
    ],
  },
  {
    id: 'tkt-005',
    ticketNumber: '#SUP-1038',
    subject: 'Cupón BLACKFRIDAY no aplica',
    customerName: 'Patricia Ruiz',
    customerEmail: 'p.ruiz@example.com',
    status: 'solved',
    priority: 'high',
    assignedTo: 'Carlos López',
    channel: 'web',
    createdAt: '2026-09-23T16:42:00Z',
    updatedAt: '2026-09-24T10:00:00Z',
    messages: [
      { id: 'msg-005-1', authorRole: 'customer', authorName: 'Patricia Ruiz', body: 'Traté de aplicar BLACKFRIDAY25 y el sistema dice que no es válido.', createdAt: '2026-09-23T16:42:00Z' },
      { id: 'msg-005-2', authorRole: 'admin', authorName: 'Carlos López', body: 'Hola Patricia, ese cupón arranca el 27/nov. Disculpá la confusión. Ya te activé un 15% de cortesía.', createdAt: '2026-09-23T17:10:00Z' },
      { id: 'msg-005-3', authorRole: 'customer', authorName: 'Patricia Ruiz', body: '¡Excelente, muchas gracias!', createdAt: '2026-09-24T10:00:00Z' },
    ],
  },
  {
    id: 'tkt-006',
    ticketNumber: '#SUP-1037',
    subject: '¿Puedo hacer pedido al mayor?',
    customerName: 'Jorge Vega',
    customerEmail: 'j.vega@boutique.com',
    status: 'pending',
    priority: 'normal',
    assignedTo: 'Roberto Sánchez',
    channel: 'email',
    createdAt: '2026-09-25T08:00:00Z',
    updatedAt: '2026-09-25T15:30:00Z',
    messages: [
      { id: 'msg-006-1', authorRole: 'customer', authorName: 'Jorge Vega', body: 'Tengo una boutique y necesito 50 playeras. ¿Manejan precios por volumen?', createdAt: '2026-09-25T08:00:00Z' },
      { id: 'msg-006-2', authorRole: 'admin', authorName: 'Roberto Sánchez', body: 'Hola Jorge, sí manejamos. Te paso nuestra escala: 25 uds 8%, 50 uds 12%, 100 uds 18%.', createdAt: '2026-09-25T15:30:00Z' },
    ],
  },
  {
    id: 'tkt-007',
    ticketNumber: '#SUP-1036',
    subject: 'Personalización mal escrita',
    customerName: 'Lucía Mendoza',
    customerEmail: 'l.mendoza@example.com',
    status: 'open',
    priority: 'high',
    assignedTo: null,
    channel: 'web',
    createdAt: '2026-09-26T06:50:00Z',
    updatedAt: '2026-09-26T06:50:00Z',
    messages: [
      { id: 'msg-007-1', authorRole: 'customer', authorName: 'Lucía Mendoza', body: 'Pedí una taza con mi nombre pero llegó "LUSIA" en lugar de "LUCÍA".', createdAt: '2026-09-26T06:50:00Z' },
    ],
  },
  {
    id: 'tkt-008',
    ticketNumber: '#SUP-1035',
    subject: 'Cancelación de pedido #ORD-1024',
    customerName: 'Diego Castro',
    customerEmail: 'd.castro@example.com',
    status: 'solved',
    priority: 'normal',
    assignedTo: 'María García',
    channel: 'whatsapp',
    createdAt: '2026-09-20T09:15:00Z',
    updatedAt: '2026-09-20T11:30:00Z',
    messages: [
      { id: 'msg-008-1', authorRole: 'customer', authorName: 'Diego Castro', body: 'Quiero cancelar el pedido #ORD-1024, recién lo hice hace 5 minutos.', createdAt: '2026-09-20T09:15:00Z' },
      { id: 'msg-008-2', authorRole: 'admin', authorName: 'María García', body: 'Listo Diego, ya cancelado. El reembolso se procesa en 3-5 días.', createdAt: '2026-09-20T09:45:00Z' },
      { id: 'msg-008-3', authorRole: 'customer', authorName: 'Diego Castro', body: 'Perfecto, gracias.', createdAt: '2026-09-20T11:30:00Z' },
    ],
  },
  {
    id: 'tkt-009',
    ticketNumber: '#SUP-1034',
    subject: 'Pregunta sobre materiales',
    customerName: 'Sofía Aguilar',
    customerEmail: 's.aguilar@example.com',
    status: 'pending',
    priority: 'low',
    assignedTo: 'Ana Martínez',
    channel: 'email',
    createdAt: '2026-09-25T13:00:00Z',
    updatedAt: '2026-09-25T16:20:00Z',
    messages: [
      { id: 'msg-009-1', authorRole: 'customer', authorName: 'Sofía Aguilar', body: '¿La taza personalizada es apta para microondas?', createdAt: '2026-09-25T13:00:00Z' },
      { id: 'msg-009-2', authorRole: 'admin', authorName: 'Ana Martínez', body: 'Sí, apta microondas y lavavajillas. Acabado cerámico horneado a alta temperatura.', createdAt: '2026-09-25T16:20:00Z' },
    ],
  },
  {
    id: 'tkt-010',
    ticketNumber: '#SUP-1033',
    subject: 'Descuento corporativo',
    customerName: 'Empresa ACME S.A.',
    customerEmail: 'compras@acme.com.ni',
    status: 'hold',
    priority: 'urgent',
    assignedTo: 'Roberto Sánchez',
    channel: 'email',
    createdAt: '2026-09-22T08:00:00Z',
    updatedAt: '2026-09-25T17:00:00Z',
    messages: [
      { id: 'msg-010-1', authorRole: 'customer', authorName: 'ACME S.A.', body: 'Necesitamos presupuesto corporativo para 200 uniformes con logo bordado.', createdAt: '2026-09-22T08:00:00Z' },
      { id: 'msg-010-2', authorRole: 'admin', authorName: 'Roberto Sánchez', body: 'Recibido. Estamos preparando propuesta personalizada. Tiempo estimado: 3 días hábiles.', createdAt: '2026-09-23T10:00:00Z' },
      { id: 'msg-010-3', authorRole: 'customer', authorName: 'ACME S.A.', body: 'Ok, quedamos atentos.', createdAt: '2026-09-25T17:00:00Z' },
    ],
  },
  {
    id: 'tkt-011',
    ticketNumber: '#SUP-1032',
    subject: 'Reembolso no recibido',
    customerName: 'Miguel Herrera',
    customerEmail: 'm.herrera@example.com',
    status: 'closed',
    priority: 'high',
    assignedTo: 'Carlos López',
    channel: 'web',
    createdAt: '2026-09-18T10:00:00Z',
    updatedAt: '2026-09-22T12:00:00Z',
    messages: [
      { id: 'msg-011-1', authorRole: 'customer', authorName: 'Miguel Herrera', body: 'Cancelé hace 5 días pero no veo el reembolso en mi tarjeta.', createdAt: '2026-09-18T10:00:00Z' },
      { id: 'msg-011-2', authorRole: 'admin', authorName: 'Carlos López', body: 'Verifico con el banco y te aviso en 24h.', createdAt: '2026-09-18T11:00:00Z' },
      { id: 'msg-011-3', authorRole: 'customer', authorName: 'Miguel Herrera', body: 'Cerrado, ya llegó el reembolso. Gracias.', createdAt: '2026-09-22T12:00:00Z' },
    ],
  },
  {
    id: 'tkt-012',
    ticketNumber: '#SUP-1031',
    subject: 'Stock de gorra bordada',
    customerName: 'Valentina Cruz',
    customerEmail: 'v.cruz@example.com',
    status: 'open',
    priority: 'normal',
    assignedTo: null,
    channel: 'whatsapp',
    createdAt: '2026-09-26T09:20:00Z',
    updatedAt: '2026-09-26T09:20:00Z',
    messages: [
      { id: 'msg-012-1', authorRole: 'customer', authorName: 'Valentina Cruz', body: '¿Cuándo vuelven a tener la gorra bordada verde en stock?', createdAt: '2026-09-26T09:20:00Z' },
    ],
  },
] as const;

export const productReviews: readonly ProductReview[] = [
  { id: 'rev-001', productName: 'Camiseta Kitsune Edición Limitada', productImage: '👕', customerName: 'Carlos López', rating: 5, title: 'Increíble calidad', body: 'La tela es súper suave y el bordado quedó perfecto. Ya quiero pedir otra.', createdAt: '2026-09-25T16:00:00Z', status: 'pending' },
  { id: 'rev-002', productName: 'Sudadera Negra Oversize', productImage: '🧥', customerName: 'María García', rating: 4, title: 'Buena pero queda grande', body: 'La calidad es buena pero tallaje grande. Pedí M y queda como L.', createdAt: '2026-09-25T14:20:00Z', status: 'pending' },
  { id: 'rev-003', productName: 'Taza Personalizada Logo', productImage: '☕', customerName: 'Roberto Sánchez', rating: 5, title: 'Mi oficina ahora tiene estilo', body: 'Pedí 12 tazas con el logo de la empresa. Todas quedaron perfectas y el packaging muy prolijo.', createdAt: '2026-09-25T10:15:00Z', status: 'approved' },
  { id: 'rev-004', productName: 'Hoodie Bordado Premium', productImage: '🧥', customerName: 'Ana Martínez', rating: 2, title: 'Llegó con manchas', body: 'El hoodie vino con manchas blancas en la manga. No entiendo cómo no lo notaron antes de enviar.', createdAt: '2026-09-25T09:00:00Z', status: 'pending' },
  { id: 'rev-005', productName: 'Pack 3 Playeras Básicas', productImage: '👕', customerName: 'Laura Díaz', rating: 5, title: 'Excelente relación precio/calidad', body: 'Por el precio que tienen, son buenísimas. Las uso para entrenar y lavan perfecto.', createdAt: '2026-09-24T20:30:00Z', status: 'approved' },
  { id: 'rev-006', productName: 'Botella Térmica 500ml', productImage: '🍶', customerName: 'Diego Castro', rating: 4, title: 'Mantiene temperatura', body: 'El café sigue caliente 6 horas después. Único pero: la tapa gotea un poco.', createdAt: '2026-09-24T15:45:00Z', status: 'approved' },
  { id: 'rev-007', productName: 'Gorra Bordada Kitsune', productImage: '🧢', customerName: 'Patricia Ruiz', rating: 5, title: 'Diseño precioso', body: 'Quedó tal cual la foto. Bordado fino y regulable.', createdAt: '2026-09-24T12:10:00Z', status: 'approved' },
  { id: 'rev-008', productName: 'Cuaderno Personalizado A5', productImage: '📓', customerName: 'Sofía Aguilar', rating: 3, title: 'Esperaba más', body: 'El cuaderno está bien pero la portada se rayó con el primer uso. Cuesta $15, debería estar mejor protegido.', createdAt: '2026-09-24T10:00:00Z', status: 'pending' },
  { id: 'rev-009', productName: 'Mochila Urbana 20L', productImage: '🎒', customerName: 'Jorge Vega', rating: 4, title: 'Espaciosa y cómoda', body: 'Llevo dos semanas usándola a diario y aguanta bien. Las costuras parecen sólidas.', createdAt: '2026-09-23T19:20:00Z', status: 'approved' },
  { id: 'rev-010', productName: 'Camisa Polo Bordada', productImage: '👔', customerName: 'Valentina Cruz', rating: 5, title: 'Perfecta para uniforme', body: 'Compramos 8 para el equipo de trabajo. Todas bordadas con el logo y nombres. Salieron impecables.', createdAt: '2026-09-23T16:00:00Z', status: 'approved' },
  { id: 'rev-011', productName: 'Lanyard Sublimado', productImage: '🎫', customerName: 'Miguel Herrera', rating: 1, title: 'No lo recomiendo', body: 'Llegó con la imagen pixelada y los colores desvaídos. Muy mala calidad.', createdAt: '2026-09-23T11:30:00Z', status: 'flagged' },
  { id: 'rev-012', productName: 'Stickers Pack x10', productImage: '🏷️', customerName: 'Lucía Mendoza', rating: 5, title: 'Mis favoritos', body: 'Ya compré 3 packs. Vinilo grueso y adhesivo que no se despega con lluvia.', createdAt: '2026-09-23T09:15:00Z', status: 'approved' },
  { id: 'rev-013', productName: 'Calendario 2026 Personalizado', productImage: '📅', customerName: 'Diego Castro', rating: 4, title: 'Buen regalo corporativo', body: 'Regalamos 30 calendarios a clientes y todos quedaron contentos. Buena calidad de papel.', createdAt: '2026-09-22T18:00:00Z', status: 'approved' },
  { id: 'rev-014', productName: 'Mousepad XL Personalizado', productImage: '🖱️', customerName: 'Carlos López', rating: 5, title: 'Tamaño perfecto', body: 'Para mi setup quedó ideal. La base antideslizante funciona perfecto.', createdAt: '2026-09-22T15:30:00Z', status: 'pending' },
  { id: 'rev-015', productName: 'Parche Bordado 7cm', productImage: '🧵', customerName: 'María García', rating: 5, title: 'Pequeños y detallados', body: 'Los hilos son firmes y el adhesivo funciona en tela. Los puse en mi mochila y siguen ahí.', createdAt: '2026-09-22T13:45:00Z', status: 'approved' },
  { id: 'rev-016', productName: 'Termo Cervecero 350ml', productImage: '🍺', customerName: 'Roberto Sánchez', rating: 2, title: 'Se oxide rápido', body: 'Llevo 3 meses y ya tiene manchas de óxido en el interior. Muy decepcionante.', createdAt: '2026-09-22T10:20:00Z', status: 'pending' },
  { id: 'rev-017', productName: 'Memoria USB 16GB Madera', productImage: '💾', customerName: 'Patricia Ruiz', rating: 5, title: 'Súper original', body: 'La madera le da un toque elegante. La grabaron con nuestro logo y quedó increíble.', createdAt: '2026-09-21T17:00:00Z', status: 'approved' },
  { id: 'rev-018', productName: 'Llavero Acrílico Sublimado', productImage: '🔑', customerName: 'Ana Martínez', rating: 4, title: 'Buena relación precio/calidad', body: 'Pedí 50 llaveros para un evento y todos quedaron bien. Tamaño perfecto.', createdAt: '2026-09-21T14:30:00Z', status: 'approved' },
  { id: 'rev-019', productName: 'Bolsa Tote Estampada', productImage: '👜', customerName: 'Sofía Aguilar', rating: 1, title: 'Pésimo estampado', body: 'El estampado se despintó al segundo lavado. Parecen serigrafía barata.', createdAt: '2026-09-21T11:00:00Z', status: 'flagged' },
  { id: 'rev-020', productName: 'Delantal Cocina Personalizado', productImage: '🍳', customerName: 'Jorge Vega', rating: 5, title: 'Para mi restaurante', body: 'Pedí 4 para mi equipo de cocina. Cómodos, lavables y el bordado del logo aguantó 50+ lavadas.', createdAt: '2026-09-20T16:45:00Z', status: 'pending' },
] as const;
