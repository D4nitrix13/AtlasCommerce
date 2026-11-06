/**
 * Contenido de la página «Acerca de» — texto de marca, breve.
 * El nombre de la tienda se interpola desde `config/stores.ts`.
 */
import type { LegalContext, LegalLocale, LegalPageContent } from './types';

function aboutEs(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      `${ctx.brandName} es una tienda online argentina. Este sitio es nuestro canal de venta directa: acá vas a encontrar nuestro catálogo, comprar con seguridad y recibir acompañamiento antes y después de cada pedido.`,
    ],
    sections: [
      {
        heading: 'Quiénes somos',
        paragraphs: [
          `Somos un equipo local que trabaja día a día para que comprar por internet sea simple, transparente y confiable. Detrás de ${ctx.brandName} hay personas reales: si necesitás ayuda, del otro lado te atiende alguien del equipo, no un robot.`,
        ],
      },
      {
        heading: 'Nuestro propósito',
        paragraphs: [
          'Existimos para acercar productos de calidad a todo el país, con información clara, precios honestos y un proceso de compra sin sorpresas. Queremos que cada pedido llegue bien y que vuelvas por convicción, no por obligación.',
        ],
      },
      {
        heading: 'Nuestros valores',
        items: [
          'Transparencia: condiciones de compra, precios y plazos siempre a la vista.',
          'Cercanía: soporte humano por los canales que encontrarás en la página de Contacto.',
          'Responsabilidad: cumplimos lo que prometemos y respondemos cuando algo no sale como esperabas.',
          'Calidad: elegimos y tratamos cada producto como si fuera para nosotros.',
        ],
      },
      {
        heading: 'Compromiso con la calidad',
        paragraphs: [
          'Cada producto pasa por nuestro control antes de salir. Si algo no cumple con lo que esperabas, tenés la garantía legal que te asiste como consumidor en Argentina y nuestros canales de cambios y devoluciones, detallados en los Términos y condiciones.',
          `Gracias por confiar en ${ctx.brandName}.`,
        ],
      },
    ],
  };
}

function aboutEn(ctx: LegalContext): LegalPageContent {
  return {
    intro: [
      `${ctx.brandName} is an Argentine online store. This site is our direct sales channel: here you can browse our catalog, shop safely, and get support before and after every order.`,
    ],
    sections: [
      {
        heading: 'Who we are',
        paragraphs: [
          `We are a local team working every day to make online shopping simple, transparent, and reliable. Behind ${ctx.brandName} there are real people: when you need help, a team member answers — not a bot.`,
        ],
      },
      {
        heading: 'Our purpose',
        paragraphs: [
          'We exist to bring quality products to the whole country, with clear information, honest prices, and a purchase process free of surprises. We want every order to arrive right and for you to come back because you choose to.',
        ],
      },
      {
        heading: 'Our values',
        items: [
          'Transparency: purchase terms, prices, and delivery windows are always visible.',
          'Closeness: human support through the channels listed on the Contact page.',
          'Responsibility: we deliver what we promise and own it when something goes wrong.',
          'Quality: we pick and handle every product as if it were for ourselves.',
        ],
      },
      {
        heading: 'Commitment to quality',
        paragraphs: [
          'Every product goes through our own quality check before shipping. If something does not meet your expectations, you are covered by the legal warranty that protects consumers in Argentina, plus our change and return channels described in the Terms and conditions.',
          `Thank you for trusting ${ctx.brandName}.`,
        ],
      },
    ],
  };
}

export function getAboutContent(locale: LegalLocale, ctx: LegalContext): LegalPageContent {
  return locale === 'en' ? aboutEn(ctx) : aboutEs(ctx);
}
