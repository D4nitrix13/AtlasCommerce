/**
 * Privacidad y datos personales — Ley 25.326 de Protección de Datos
 * Personales (Argentina) y su reglamentación. Derechos ARCO, cookies,
 * destinatarios y plazos de conservación.
 *
 * Los datos fiscales y el domicilio legal del responsable NO se
 * inventan: quedan marcadores `[completar: …]` para que el negocio
 * los cargue con datos reales.
 */
import type { LegalContext, LegalLocale, LegalPageContent } from './types';

function privacyEs(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      `La presente Política de Privacidad describe cómo ${ctx.brandName} recolecta, utiliza, almacena y protege los datos personales de los usuarios del sitio, conforme a la Ley 25.326 de Protección de Datos Personales y su reglamentación.`,
    ],
    sections: [
      {
        heading: 'Responsable del tratamiento',
        paragraphs: [
          `Responsable del tratamiento: ${ctx.brandName}, CUIT [completar: CUIT del responsable], con domicilio legal en [completar: domicilio legal], República Argentina (en adelante, el «Responsable»).`,
          `Correo de contacto para cuestiones de privacidad: ${ctx.supportEmail}.`,
        ],
      },
      {
        heading: 'Datos que recolectamos y finalidades',
        paragraphs: [
          'Recolectamos los siguientes tipos de datos personales, para las finalidades indicadas:',
        ],
        items: [
          'Datos de identidad y contacto: nombre y apellido, correo electrónico, teléfono y dirección. Finalidad: gestionar tu cuenta, procesar y entregar pedidos, emitir comprobantes y comunicarnos contigo.',
          'Datos de compra: productos adquiridos, importes, historial de pedidos y preferencias. Finalidad: gestionar la venta, brindar soporte postventa y cumplir obligaciones legales.',
          'Datos de facturación: condición fiscal y datos necesarios para emitir el comprobante correspondiente. Finalidad: cumplir obligaciones fiscales y contables.',
          'Datos de navegación y cookies: dirección IP, tipo de navegador, páginas visitadas, identificadores de sesión y dispositivo. Finalidad: seguridad del sitio, prevención de fraude, métricas de uso y mejora de la experiencia.',
        ],
      },
      {
        heading: 'Base legal y consentimiento',
        paragraphs: [
          'El tratamiento de datos personales se sustenta en: (i) la ejecución del contrato de compraventa y las medidas precontractuales a tu pedido; (ii) el cumplimiento de obligaciones legales (normativa fiscal y de defensa del consumidor); (iii) el interés legítimo en la seguridad y la mejora del sitio; y (iv) tu consentimiento para cookies no esenciales y comunicaciones comerciales, cuando corresponda.',
          'Podés revocar tu consentimiento en cualquier momento, sin efectos retroactivos, escribiendo al correo de contacto indicado más abajo.',
        ],
      },
      {
        heading: 'Encargados de tratamiento y destinatarios',
        paragraphs: [
          'Tus datos pueden ser compartidos con proveedores que actúan como encargados del tratamiento: pasarelas y procesadores de pago, servicios de facturación, transportistas o empresas de logística, proveedores de hosting, y herramientas de analítica y soporte.',
          'Estos terceros reciben únicamente los datos necesarios para cumplir su función y están obligados a su confidencialidad y a tratarlos conforme a esta política y a la normativa vigente.',
          'No vendemos ni cedemos datos personales a terceros con fines de marketing.',
        ],
      },
      {
        heading: 'Derechos ARCO (Ley 25.326)',
        paragraphs: [
          'Conforme a la Ley 25.326 de Protección de Datos Personales y su reglamentación, el titular de los datos personales tiene derecho a acceder, rectificar, actualizar, cancelar u oponerse al tratamiento de sus datos (derechos ARCO). El ejercicio de estos derechos es gratuito.',
          'La Autoridad de Aplicación de la Ley 25.326 es la Agencia de Acceso a la Información Pública (AAIP), organismo ante el cual el titular puede presentar reclamos o denuncias por incumplimientos en materia de protección de datos personales.',
        ],
        steps: [
          `Dirigí tu solicitud por escrito al correo ${ctx.supportEmail}, indicando nombre completo, el derecho que querés ejercer (acceso, rectificación, actualización, cancelación u oposición) y la descripción concreta del pedido.`,
          'Adjuntá copia de un documento que acredite tu identidad (o acreditá la representación, si actuás por un tercero).',
          'La solicitud será atendida dentro de los plazos previstos por la Ley 25.326 y su reglamentación.',
          'Si la respuesta no te satisface, podés reclamar ante la AAIP (Agencia de Acceso a la Información Pública).',
        ],
      },
      {
        heading: 'Cookies',
        paragraphs: [
          'El sitio utiliza cookies propias y de terceros, que pueden clasificarse en:',
        ],
        items: [
          'Técnicas: necesarias para el funcionamiento del sitio (sesión, carrito, seguridad).',
          'De preferencia: recuerdan idioma y configuración para mejorar tu experiencia.',
          'Analíticas: miden el uso del sitio de forma agregada (páginas visitadas, origen del tráfico).',
        ],
        subSections: [
          {
            heading: 'Cómo desactivarlas',
            paragraphs: [
              'Podés bloquear o eliminar cookies desde la configuración de tu navegador (Chrome, Firefox, Safari, Edge), en la sección de privacidad o cookies. Tené en cuenta que desactivar las cookies técnicas puede afectar el funcionamiento del sitio (por ejemplo, el carrito de compras).',
            ],
          },
        ],
      },
      {
        heading: 'Seguridad de la información',
        paragraphs: [
          'Aplicamos medidas técnicas y organizativas razonables para proteger los datos personales contra pérdida, acceso no autorizado, alteración o divulgación, incluyendo cifrado en tránsito, controles de acceso y copias de seguridad.',
          'Ningún sistema es 100% seguro. Ante un incidente de seguridad que afecte datos personales, actuaremos conforme a la normativa vigente y notificaremos a las autoridades y titulares cuando corresponda.',
        ],
      },
      {
        heading: 'Transferencias internacionales',
        paragraphs: [
          'Algunos proveedores de servicios (por ejemplo, hosting, analítica o procesadores de pago) pueden almacenar o tratar datos fuera de la República Argentina. En tales casos, exigimos niveles de protección adecuados y compatibles con la Ley 25.326 y su reglamentación.',
        ],
      },
      {
        heading: 'Plazos de conservación',
        paragraphs: [
          'Conservamos los datos personales durante el tiempo necesario para cumplir las finalidades informadas y las obligaciones legales aplicables (por ejemplo, normativa fiscal y contable). Vencidos esos plazos, los datos se eliminan o se anonimizan de forma segura.',
        ],
      },
      {
        heading: 'Modificaciones de esta política',
        paragraphs: [
          'Podemos modificar esta política para reflejar cambios normativos o de nuestros servicios. Las modificaciones se publicarán en esta página; el uso continuado del sitio tras su publicación implica la aceptación de los cambios.',
          'Última actualización: [completar: fecha de última actualización].',
        ],
      },
    ],
  };
}

function privacyEn(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      `This Privacy Policy describes how ${ctx.brandName} collects, uses, stores, and protects the personal data of website users, in accordance with Argentine Personal Data Protection Law No. 25.326 and its regulations.`,
    ],
    sections: [
      {
        heading: 'Data controller',
        paragraphs: [
          `Data controller: ${ctx.brandName}, Tax ID [completar: CUIT del responsable], with legal domicile at [completar: domicilio legal], Argentine Republic (the «Controller»).`,
          `Privacy contact email: ${ctx.supportEmail}.`,
        ],
      },
      {
        heading: 'Data we collect and purposes',
        paragraphs: ['We collect the following types of personal data for the purposes stated below:'],
        items: [
          'Identity and contact data: full name, email address, phone number, and address. Purpose: manage your account, process and deliver orders, issue receipts, and communicate with you.',
          'Purchase data: products purchased, amounts, order history, and preferences. Purpose: manage the sale, provide after-sales support, and comply with legal obligations.',
          'Billing data: tax status and data required to issue the relevant invoice. Purpose: comply with tax and accounting obligations.',
          'Browsing and cookie data: IP address, browser type, pages visited, session identifiers, and device data. Purpose: website security, fraud prevention, usage metrics, and experience improvement.',
        ],
      },
      {
        heading: 'Legal basis and consent',
        paragraphs: [
          'Personal data is processed on the following bases: (i) performance of the sales contract and pre-contractual measures at your request; (ii) compliance with legal obligations (tax and consumer protection regulations); (iii) legitimate interest in website security and improvement; and (iv) your consent for non-essential cookies and marketing communications, where applicable.',
          'You may withdraw your consent at any time, with no retroactive effect, by writing to the contact email below.',
        ],
      },
      {
        heading: 'Data processors and recipients',
        paragraphs: [
          'Your data may be shared with providers acting as data processors: payment gateways and processors, invoicing services, carriers or logistics companies, hosting providers, and analytics and support tools.',
          'These third parties receive only the data necessary to perform their function and are bound by confidentiality and by this policy and current regulations.',
          'We do not sell or share personal data with third parties for marketing purposes.',
        ],
      },
      {
        heading: 'ARCO rights (Law No. 25.326)',
        paragraphs: [
          'Under Personal Data Protection Law No. 25.326 and its regulations, data subjects have the right to access, rectify, update, delete, or object to the processing of their personal data (ARCO rights). Exercising these rights is free of charge.',
          'The enforcement authority of Law No. 25.326 is the Agency for Access to Public Information (AAIP), before which data subjects may file complaints regarding personal data protection breaches.',
        ],
        steps: [
          `Send your request in writing to ${ctx.supportEmail}, stating your full name, the right you wish to exercise (access, rectification, update, deletion, or objection), and a concrete description of your request.`,
          'Attach a copy of an identity document (or prove representation if acting on behalf of a third party).',
          'Your request will be handled within the timeframes provided by Law No. 25.326 and its regulations.',
          'If the response is unsatisfactory, you may file a complaint with the AAIP.',
        ],
      },
      {
        heading: 'Cookies',
        paragraphs: ['This website uses first- and third-party cookies, which can be classified as:'],
        items: [
          'Technical: required for the website to work (session, cart, security).',
          'Preference: remember language and settings to improve your experience.',
          'Analytics: measure website usage in aggregate (pages visited, traffic sources).',
        ],
        subSections: [
          {
            heading: 'How to disable them',
            paragraphs: [
              'You can block or delete cookies from your browser settings (Chrome, Firefox, Safari, Edge), in the privacy or cookies section. Note that disabling technical cookies may affect website functionality (for example, the shopping cart).',
            ],
          },
        ],
      },
      {
        heading: 'Information security',
        paragraphs: [
          'We apply reasonable technical and organizational measures to protect personal data against loss, unauthorized access, alteration, or disclosure, including encryption in transit, access controls, and backups.',
          'No system is 100% secure. In the event of a security incident affecting personal data, we will act in accordance with current regulations and notify authorities and data subjects where required.',
        ],
      },
      {
        heading: 'International transfers',
        paragraphs: [
          'Some service providers (for example, hosting, analytics, or payment processors) may store or process data outside the Argentine Republic. In such cases, we require adequate protections compatible with Law No. 25.326 and its regulations.',
        ],
      },
      {
        heading: 'Retention periods',
        paragraphs: [
          'We keep personal data for as long as necessary to fulfill the purposes described and applicable legal obligations (for example, tax and accounting rules). Once those periods expire, data is securely deleted or anonymized.',
        ],
      },
      {
        heading: 'Changes to this policy',
        paragraphs: [
          'We may update this policy to reflect regulatory or service changes. Changes will be posted on this page; continued use of the website after publication implies acceptance of the changes.',
          'Last updated: [completar: fecha de última actualización].',
        ],
      },
    ],
  };
}

export function getPrivacyContent(locale: LegalLocale, ctx: LegalContext): LegalPageContent {
  return locale === 'en' ? privacyEn(ctx) : privacyEs(ctx);
}
