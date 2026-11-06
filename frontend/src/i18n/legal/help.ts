/**
 * Ayuda / FAQ — preguntas y respuestas prácticas de compra, envíos,
 * pagos, devoluciones y soporte. Cada pregunta es un `heading` (h2)
 * con su respuesta debajo.
 */
import type { LegalContext, LegalLocale, LegalPageContent } from './types';

function helpEs(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      'Respuestas a las consultas más frecuentes. Si no encontrás lo que buscás, escribinos: estamos para ayudarte.',
    ],
    sections: [
      {
        heading: '¿Cómo puedo comprar en la tienda?',
        paragraphs: ['Comprar es simple y no necesitás crear una cuenta para hacerlo:'],
        steps: [
          'Navegá por el catálogo y elegí los productos que te interesan.',
          'Agregá los productos al carrito e indicá las cantidades.',
          'Iniciá el checkout: completá tus datos de contacto y la dirección de envío.',
          'Elegí el medio de pago y confirmá el pedido.',
          'Vas a recibir un correo electrónico con el detalle y el número de tu pedido.',
        ],
      },
      {
        heading: '¿Cuáles son los estados del pedido?',
        paragraphs: ['Tu pedido puede atravesar los siguientes estados:'],
        items: [
          'Pendiente de pago: recibimos tu pedido y estamos aguardando la confirmación del pago.',
          'Pago confirmado / En preparación: el pago se acreditó y estamos preparando tu envío.',
          'Enviado: el pedido salió a reparto; te informamos el seguimiento por correo.',
          'Entregado: el pedido llegó a la dirección informada.',
          'Cancelado: el pedido fue cancelado (por vos o por falta de pago) y no llegó a prepararse.',
        ],
      },
      {
        heading: '¿Realizan envíos? ¿Cuánto demoran?',
        paragraphs: [
          'Sí. Enviamos a todo el territorio argentino, a la dirección que indiques al comprar.',
          'El plazo de entrega depende del destino, del medio de envío elegido y de la disponibilidad del producto. El plazo estimado se informa al momento de la compra. Si tu pedido es personalizado, el plazo se cuenta desde la aprobación del diseño.',
        ],
      },
      {
        heading: '¿Qué medios de pago aceptan?',
        paragraphs: [
          'Los medios de pago habilitados se muestran durante el proceso de checkout e incluyen, según disponibilidad, tarjetas de crédito y débito y transferencia bancaria.',
          'Los precios están expresados en pesos argentinos (ARS) e incluyen IVA cuando corresponde. De cada compra emitimos el comprobante o factura correspondiente.',
        ],
      },
      {
        heading: '¿Cómo hago un cambio o devolución?',
        paragraphs: [
          'Si sos consumidor, tenés derecho de desistimiento: podés revocar la compra sin causa dentro de los 10 días corridos desde que recibiste el producto (Ley 24.240, art. 34). Los productos también cuentan con garantía legal por defectos de fabricación.',
          `Para iniciar un cambio o devolución, escribinos a ${ctx.supportEmail} (o por el canal de la página de Contacto) con tu número de pedido, el producto y el motivo. Te vamos a indicar el procedimiento. Los productos personalizados son excepción al derecho de desistimiento — la garantía legal por defectos sí se mantiene.`,
          'Encontrá el detalle completo en los Términos y condiciones.',
        ],
      },
      {
        heading: '¿Cómo sigo mi pedido?',
        paragraphs: [
          'Te enviamos un correo electrónico cada vez que el pedido cambia de estado. Cuando sale a reparto, incluimos el número de seguimiento del transportista para que puedas rastrearlo.',
          'Si tenés dudas sobre dónde está tu pedido, contactanos con tu número de pedido y lo verificamos.',
        ],
      },
      {
        heading: '¿Cómo contacto al soporte?',
        paragraphs: [
          `Escribinos a ${ctx.supportEmail}: es nuestro canal principal de soporte y te respondemos a la brevedad. También podés encontrar los horarios de atención y demás canales en la página de Contacto.`,
        ],
      },
    ],
  };
}

function helpEn(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      'Answers to the most common questions. If you cannot find what you are looking for, write to us — we are here to help.',
    ],
    sections: [
      {
        heading: 'How do I buy from the store?',
        paragraphs: ['Buying is simple, and you do not need an account to do it:'],
        steps: [
          'Browse the catalog and pick the products you are interested in.',
          'Add products to the cart and set the quantities.',
          'Start checkout: enter your contact details and shipping address.',
          'Choose a payment method and confirm your order.',
          'You will receive an email with the order details and order number.',
        ],
      },
      {
        heading: 'What are the order statuses?',
        paragraphs: ['Your order may go through the following statuses:'],
        items: [
          'Awaiting payment: we received your order and are waiting for payment confirmation.',
          'Payment confirmed / Preparing: payment was credited and we are getting your shipment ready.',
          'Shipped: the order is on its way; we email you the tracking details.',
          'Delivered: the order arrived at the address you provided.',
          'Cancelled: the order was cancelled (by you or for non-payment) and was not prepared.',
        ],
      },
      {
        heading: 'Do you ship? How long does it take?',
        paragraphs: [
          'Yes. We ship throughout Argentine territory to the address you provide at checkout.',
          'Delivery time depends on destination, the shipping method chosen, and product availability. The estimated timeframe is shown at the time of purchase. For custom-made items, the timeframe starts once the design is approved.',
        ],
      },
      {
        heading: 'Which payment methods do you accept?',
        paragraphs: [
          'Available payment methods are shown during checkout and may include credit and debit cards and bank transfers, subject to availability.',
          'Prices are expressed in Argentine pesos (ARS) and include VAT where applicable. A receipt or invoice is issued for every purchase.',
        ],
      },
      {
        heading: 'How do I make a change or return?',
        paragraphs: [
          'If you are a consumer, you have a right of withdrawal: you may cancel your purchase without cause within 10 consecutive days of receiving the product (Law No. 24.240, section 34). Products also come with a legal warranty for manufacturing defects.',
          `To start a change or return, email ${ctx.supportEmail} (or use the channel on the Contact page) with your order number, the product, and the reason. We will guide you through the procedure. Custom-made products are an exception to the right of withdrawal — the legal warranty for defects still applies.`,
          'Find the full detail in the Terms and conditions.',
        ],
      },
      {
        heading: 'How do I track my order?',
        paragraphs: [
          'We send you an email every time your order changes status. When it ships, we include the carrier tracking number so you can follow it.',
          'If you are unsure where your order is, contact us with your order number and we will check.',
        ],
      },
      {
        heading: 'How do I contact support?',
        paragraphs: [
          `Email ${ctx.supportEmail}: it is our main support channel and we reply as soon as possible. You can also find business hours and other channels on the Contact page.`,
        ],
      },
    ],
  };
}

export function getHelpContent(locale: LegalLocale, ctx: LegalContext): LegalPageContent {
  return locale === 'en' ? helpEn(ctx) : helpEs(ctx);
}
