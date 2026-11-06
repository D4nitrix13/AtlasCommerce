/**
 * Términos y condiciones — legislación Argentina.
 * Fuentes: Ley 24.240 de Defensa del Consumidor y modificatorias,
 * Decreto 424/98 (garantía legal), Código Civil y Comercial de la Nación.
 */
import type { LegalContext, LegalLocale, LegalPageContent } from './types';

function termsEs(ctx: LegalContext): LegalPageContent {
  return {
    sections: [
      {
        heading: 'Objeto y aceptación',
        paragraphs: [
          `Los presentes Términos y Condiciones (en adelante, los «Términos») regulan el acceso y el uso del sitio web de ${ctx.brandName} (en adelante, la «Tienda»), así como la adquisición de los productos y servicios ofrecidos a través del mismo.`,
          'La navegación por el sitio y/o la realización de una compra implican la aceptación plena e irrestricta de estos Términos. Quien no los acepte deberá abstenerse de utilizar el sitio.',
          'Ámbito: estos Términos se rigen por la legislación de la República Argentina —en particular por la Ley 24.240 de Defensa del Consumidor y sus modificatorias— y resultan aplicables a las compras realizadas por consumidores o usuarios en territorio argentino.',
        ],
      },
      {
        heading: 'Precios, moneda, medios de pago e impuestos',
        paragraphs: [
          'Los precios publicados en el sitio se expresan en pesos argentinos (ARS) e incluyen el Impuesto al Valor Agregado (IVA) cuando corresponda, salvo indicación expresa en contrario.',
          'Los medios de pago aceptados se informan en el proceso de checkout. La Tienda podrá incorporar, modificar o retirar medios de pago sin previo aviso, sin afectar las compras ya confirmadas.',
          'La Tienda se reserva el derecho de modificar los precios publicados en cualquier momento. Las compras ya confirmadas respetarán el precio vigente al momento de la confirmación del pedido.',
          'De cada compra se emitirá el comprobante o factura correspondiente, conforme a la normativa fiscal vigente.',
        ],
      },
      {
        heading: 'Envíos y plazos de entrega',
        paragraphs: [
          'Los envíos se realizan a la dirección informada por el Usuario durante el proceso de compra, dentro del territorio argentino.',
          'Los plazos de entrega son estimados y se informan al momento de la compra. Pueden variar según el destino, el medio de envío elegido y la disponibilidad del producto.',
          'El pedido debe ser recibido por el Usuario o por una persona mayor de edad autorizada en el domicilio de entrega; el transportista podrá requerir identificación.',
          'Si el pedido no pudiera entregarse por causas atribuibles al Usuario (dirección incorrecta, ausencia reiterada, etc.), la Tienda coordinará un nuevo intento de entrega; los costos adicionales que correspondan serán informados previamente.',
        ],
      },
      {
        heading: 'Derecho de desistimiento (derecho de arrepentimiento)',
        paragraphs: [
          'El Usuario que actúe como consumidor conforme a la Ley 24.240 cuenta con el derecho de desistimiento previsto en el artículo 34 de dicha ley: puede revocar la compra sin expresión de causa dentro de los DIEZ (10) DÍAS CORRIDOS computables desde la recepción del producto, o desde la celebración del contrato si se tratara de servicios.',
          'Para ejercer el desistimiento, el Usuario debe comunicar su decisión de manera fehaciente (por escrito o por correo electrónico) al canal de soporte indicado en la página de Contacto, identificando el pedido y el producto sobre el que recae.',
          'Recibida la comunicación, la Tienda instruirá al Usuario sobre el procedimiento de devolución. El producto debe ser devuelto sin uso, en las condiciones en que fue recibido, con su empaque original y todos sus accesorios.',
          'Verificada la recepción del producto en condiciones, la Tienda reintegrará el importe abonado por el mismo conforme a los plazos previstos por la normativa vigente.',
        ],
        steps: [
          'Comunicar el desistimiento dentro de los 10 días corridos desde la recepción, al canal de soporte de la página de Contacto.',
          'Indicar número de pedido y producto a devolver.',
          'Seguir las instrucciones de devolución que informe la Tienda.',
          'Recibir el reintegro del importe abonado una vez verificada la devolución.',
        ],
      },
      {
        heading: 'Garantía legal',
        paragraphs: [
          'Todos los productos cuentan con garantía legal de conformidad, conforme a la Ley 24.240 de Defensa del Consumidor y el Decreto 424/98.',
          'Regla general: la garantía legal es de SEIS (6) MESES para bienes nuevos y de TRES (3) MESES para bienes usados, computables desde la entrega del producto.',
          'Esta garantía legal es independiente de la garantía comercial que pudiera ofrecer el fabricante o importador, y no limita en modo alguno los derechos irrenunciables del consumidor. Ante un defecto de conformidad, el Usuario podrá optar, según lo previsto por la ley, entre la reparación, el reemplazo o la restitución del precio pagado.',
          'Para hacer valer la garantía, el Usuario deberá conservar el comprobante de compra y contactar al canal de soporte de la página de Contacto.',
        ],
      },
      {
        heading: 'Cambios y devoluciones',
        paragraphs: [
          'Además de la garantía legal, la Tienda admite cambios y devoluciones por defecto de fabricación, producto distinto al solicitado o faltantes en el envío, dentro de los plazos de garantía aplicables.',
          'Fuera del plazo de desistimiento, no se admiten devoluciones por arrepentimiento, ni de productos usados, deteriorados, sin su empaque original o fuera de las condiciones de higiene y conservación, salvo que medie defecto de conformidad.',
          'Para iniciar un cambio o devolución, el Usuario debe contactarse por el canal de soporte de la página de Contacto e indicar número de pedido, producto y motivo. La Tienda responderá con el procedimiento y los plazos aplicables al caso.',
        ],
      },
      {
        heading: 'Productos personalizados',
        paragraphs: [
          'El derecho de desistimiento NO resulta aplicable a productos confeccionados conforme a las especificaciones del consumidor o claramente personalizados (por ejemplo, prendas estampadas con diseños provistos o elegidos por el Usuario), en los términos de la excepción prevista en el artículo 34 de la Ley 24.240 y su reglamentación.',
          'En estos casos se aplican los plazos de entrega informados al confirmar el pedido, dado que la confección comienza con la aprobación del diseño.',
          'La garantía legal por defectos de fabricación continúa vigente también para los productos personalizados.',
          'Los errores derivados de un diseño, talle o insumo expresamente aprobado por el Usuario previo a la producción no son imputables a la Tienda, sin perjuicio de los derechos irrenunciables del consumidor.',
        ],
      },
      {
        heading: 'Propiedad intelectual',
        paragraphs: [
          `Todo el contenido del sitio —marcas, logotipos, textos, fotografías, diseños, gráficos y código— es propiedad de ${ctx.brandName} o de sus licenciantes y está protegido por la Ley 11.723 de Propiedad Intelectual, la Ley 22.426 de Marcas y Designaciones y la normativa concordante.`,
          'Queda prohibida la reproducción, distribución, comunicación pública o modificación de dicho contenido, total o parcialmente, sin autorización previa y por escrito de la Tienda.',
          'Los diseños, archivos y contenidos que el Usuario aporte para productos personalizados siguen siendo de propiedad del Usuario; el Usuario declara contar con los derechos necesarios sobre los mismos y autoriza su uso exclusivamente para confeccionar el pedido.',
        ],
      },
      {
        heading: 'Limitación de responsabilidad',
        paragraphs: [
          'La Tienda no se responsabiliza por daños derivados del uso indebido de los productos, del incumplimiento de las instrucciones de uso o de interrupciones del servicio ajenos a su control razonable (fallas de conectividad, hechos de fuerza mayor o de terceros).',
          'La responsabilidad de la Tienda por defectos de conformidad de los productos se rige exclusivamente por la Ley 24.240 de Defensa del Consumidor. Nada de lo previsto en estos Términos limita, excluye o reduce los derechos irrenunciables reconocidos al consumidor por la legislación vigente.',
        ],
      },
      {
        heading: 'Ley aplicable y jurisdicción',
        paragraphs: [
          'Estos Términos se interpretan y rigen por las leyes de la República Argentina.',
          'Para toda controversia derivada de su interpretación, ejecución o incumplimiento, el Usuario consumidor puede reclamar ante la justicia de su domicilio, conforme a lo dispuesto por los artículos 26 y concordantes de la Ley 24.240 de Defensa del Consumidor y el Código Civil y Comercial de la Nación.',
          'Antes de iniciar una acción judicial, el Usuario puede canalizar su reclamo ante las autoridades locales de Defensa del Consumidor y/o instancias de mediación o conciliación previas aplicables.',
        ],
      },
    ],
  };
}

function termsEn(ctx: LegalContext): LegalPageContent {
  return {
    sections: [
      {
        heading: 'Purpose and acceptance',
        paragraphs: [
          `These Terms and Conditions (the «Terms») govern access to and use of the ${ctx.brandName} website (the «Store»), as well as the purchase of the products and services offered through it.`,
          'Browsing the website and/or making a purchase implies full and unconditional acceptance of these Terms. If you do not accept them, you must not use the website.',
          'Scope: these Terms are governed by the laws of the Argentine Republic —in particular Consumer Defense Law No. 24.240 and its amendments— and apply to purchases made by consumers or users within Argentine territory.',
        ],
      },
      {
        heading: 'Prices, currency, payment methods, and taxes',
        paragraphs: [
          'Prices published on the website are expressed in Argentine pesos (ARS) and include Value Added Tax (VAT) where applicable, unless expressly stated otherwise.',
          'Accepted payment methods are shown during the checkout process. The Store may add, modify, or remove payment methods without prior notice, without affecting purchases already confirmed.',
          'The Store reserves the right to change published prices at any time. Confirmed orders keep the price in effect at the time of order confirmation.',
          'A receipt or invoice will be issued for each purchase in accordance with current tax regulations.',
        ],
      },
      {
        heading: 'Shipping and delivery times',
        paragraphs: [
          'Orders are shipped to the address you provide during checkout, within Argentine territory.',
          'Delivery times are estimates provided at the time of purchase. They may vary depending on destination, the shipping method chosen, and product availability.',
          'Orders must be received by you or by an authorized adult at the delivery address; the carrier may request identification.',
          'If an order cannot be delivered for reasons attributable to you (wrong address, repeated absence, etc.), the Store will arrange a new delivery attempt; any additional costs will be informed in advance.',
        ],
      },
      {
        heading: 'Right of withdrawal (cooling-off)',
        paragraphs: [
          'If you act as a consumer under Law No. 24.240, you are entitled to the right of withdrawal provided by section 34 of that law: you may cancel the purchase without giving any reason within TEN (10) CONSECUTIVE DAYS from receipt of the product, or from the conclusion of the contract in the case of services.',
          'To exercise this right, you must notify your decision in writing or by email through the support channel listed on the Contact page, identifying the order and the product involved.',
          'Once notified, the Store will provide return instructions. The product must be returned unused, in the condition it was received, in its original packaging with all accessories.',
          'After the product is received in due condition, the Store will refund the amount paid within the timeframes provided by current regulations.',
        ],
        steps: [
          'Notify your withdrawal within 10 consecutive days of receipt through the support channel on the Contact page.',
          'Provide the order number and the product to return.',
          'Follow the return instructions provided by the Store.',
          'Receive the refund once the return has been verified.',
        ],
      },
      {
        heading: 'Legal warranty',
        paragraphs: [
          'All products come with a legal conformity warranty under Consumer Defense Law No. 24.240 and Decree No. 424/98.',
          'General rule: the legal warranty is SIX (6) MONTHS for new goods and THREE (3) MONTHS for used goods, counted from delivery of the product.',
          'This legal warranty is independent of any commercial warranty offered by the manufacturer or importer, and does not limit the consumer’s non-waivable rights in any way. In the event of a lack of conformity, you may choose, as provided by law, between repair, replacement, or refund of the price paid.',
          'To claim under the warranty, keep your proof of purchase and contact the support channel on the Contact page.',
        ],
      },
      {
        heading: 'Changes and returns',
        paragraphs: [
          'In addition to the legal warranty, the Store accepts changes and returns for manufacturing defects, wrong items shipped, or missing items, within the applicable warranty periods.',
          'After the withdrawal period, returns based on regret are not accepted, nor are returns of used, damaged items, items without original packaging, or items outside hygiene and preservation conditions, unless there is a lack of conformity.',
          'To start a change or return, contact the support channel on the Contact page with your order number, the product, and the reason. The Store will respond with the applicable procedure and timeframes.',
        ],
      },
      {
        heading: 'Custom-made products',
        paragraphs: [
          'The right of withdrawal does NOT apply to goods made to the consumer’s specifications or clearly personalized (for example, garments printed with designs provided or chosen by you), under the exception provided by section 34 of Law No. 24.240 and its regulations.',
          'In such cases the delivery times informed at order confirmation apply, since production starts once the design is approved.',
          'The legal warranty for manufacturing defects remains in force for custom-made products as well.',
          'Mistakes arising from a design, size, or material expressly approved by you before production are not attributable to the Store, without prejudice to the consumer’s non-waivable rights.',
        ],
      },
      {
        heading: 'Intellectual property',
        paragraphs: [
          `All website content —trademarks, logos, texts, photographs, designs, graphics, and code— is owned by ${ctx.brandName} or its licensors and is protected by Intellectual Property Law No. 11.723, Trademarks Law No. 22.426, and related regulations.`,
          'Reproduction, distribution, communication to the public, or modification of such content, in whole or in part, is prohibited without the Store’s prior written authorization.',
          'Designs, files, and content you contribute for custom products remain your property; you declare that you hold the necessary rights over them and authorize their use solely to produce your order.',
        ],
      },
      {
        heading: 'Limitation of liability',
        paragraphs: [
          'The Store is not liable for damages arising from misuse of products, failure to follow instructions, or service interruptions beyond its reasonable control (connectivity failures, force majeure, or third-party acts).',
          'The Store’s liability for product conformity defects is governed exclusively by Consumer Defense Law No. 24.240. Nothing in these Terms limits, excludes, or reduces the non-waivable rights granted to consumers by current legislation.',
        ],
      },
      {
        heading: 'Governing law and jurisdiction',
        paragraphs: [
          'These Terms are governed by and construed in accordance with the laws of the Argentine Republic.',
          'For any dispute arising from their interpretation, performance, or breach, consumer users may bring claims before the courts of their own domicile, as provided by sections 26 et seq. of Consumer Defense Law No. 24.240 and the National Civil and Commercial Code.',
          'Before initiating legal action, you may raise your claim with local consumer protection authorities and/or applicable mediation or conciliation bodies.',
        ],
      },
    ],
  };
}

export function getTermsContent(locale: LegalLocale, ctx: LegalContext): LegalPageContent {
  return locale === 'en' ? termsEn(ctx) : termsEs(ctx);
}
