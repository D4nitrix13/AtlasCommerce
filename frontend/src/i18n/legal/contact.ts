/**
 * Contacto — canales de soporte del tenant. El email de soporte sale
 * de `brand.supportEmail`; el WhatsApp (si el tenant lo tiene
 * configurado en BD / theme) se renderiza condicionalmente desde la
 * página. El horario de atención queda con marcador `[completar: …]`
 * mientras el negocio no cargue el dato real.
 */
import type { LegalContext, LegalLocale, LegalPageContent } from './types';

function contactEs(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      `Estamos para ayudarte. El canal principal de soporte de ${ctx.brandName} es el correo electrónico: leemos cada consulta y respondemos a la brevedad.`,
    ],
    sections: [
      {
        heading: 'Horarios de atención',
        paragraphs: [
          'Nuestro horario de atención es [completar: días y franja horaria de atención, p. ej. lunes a viernes de 9 a 18 h]. Fuera de ese horario podés escribirnos igual: te respondemos el próximo día hábil.',
        ],
      },
      {
        heading: 'Tiempos de respuesta',
        paragraphs: [
          'Apuntamos a responder toda consulta dentro de las 24 a 48 horas hábiles. Si tu mensaje es sobre un pedido en curso, incluí el número de pedido para que podamos ayudarte más rápido.',
        ],
      },
      {
        heading: 'Formas de contacto',
        paragraphs: [
          `Podés escribirnos a ${ctx.supportEmail}. Es el canal principal y el más rápido para pedidos, cambios, devoluciones y garantías.`,
          'No contamos con formulario de contacto en el sitio: el correo electrónico es el canal oficial de soporte. Si la tienda tiene WhatsApp habilitado, lo verás indicado arriba.',
          'Para cuestiones sobre tus datos personales (derechos ARCO), consultá la página de Privacidad y datos personales.',
        ],
      },
    ],
  };
}

function contactEn(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      `We are here to help. The main support channel for ${ctx.brandName} is email: we read every message and reply as soon as possible.`,
    ],
    sections: [
      {
        heading: 'Business hours',
        paragraphs: [
          'Our business hours are [completar: días y franja horaria de atención, p. ej. lunes a viernes de 9 a 18 h]. Outside those hours you can still write to us: we reply on the next business day.',
        ],
      },
      {
        heading: 'Response times',
        paragraphs: [
          'We aim to answer every inquiry within 24 to 48 business hours. If your message is about an ongoing order, include the order number so we can help you faster.',
        ],
      },
      {
        heading: 'Ways to contact us',
        paragraphs: [
          `Write to ${ctx.supportEmail}. It is the main and fastest channel for orders, changes, returns, and warranties.`,
          'We do not have a contact form on the website: email is the official support channel. If the store has WhatsApp enabled, you will see it listed above.',
          'For questions about your personal data (ARCO rights), see the Privacy and personal data page.',
        ],
      },
    ],
  };
}

export function getContactContent(locale: LegalLocale, ctx: LegalContext): LegalPageContent {
  return locale === 'en' ? contactEn(ctx) : contactEs(ctx);
}
