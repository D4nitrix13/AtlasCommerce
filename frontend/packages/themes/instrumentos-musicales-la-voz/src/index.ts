/**
 * Instrumentos Musicales La Voz — Tropical Music.
 *
 * Primer cliente real de AtlasCommerce SaaS multi-tenant. La Voz opera
 * audio profesional e instrumentos musicales en Nicaragua desde hace
 * varias décadas. Su web oficial lavoznicaragua.com funciona como
 * catálogo en línea; la confirmación de compras sigue por WhatsApp
 * y Facebook. Este theme replica esa lógica con CTA "Cotizar por
 * WhatsApp" prominente en home y product detail.
 *
 * Paleta tropical inspirada en la marca Tropical Music:
 *   - 60% crema muy claro   (#F5F0E6) — fondo editorial cálido
 *   - 30% casi negro         (#1B1B1B) — texto principal
 *   - 10% verde tropical     (#0E7C7B) — accent (CTAs, badges, focus)
 *
 * Acentos secundarios para badges / categorías:
 *   - Oro tropical:    #D4A437  (sellos, ofertas destacadas)
 *   - Coral cálido:   #E76F51  (hover secundario, alertas)
 *   - Verde lima:     #B4C44A  (badges "nuevo")
 *   - Beige arena:    #C4A57B  (categorías premium)
 *
 * Neutros:
 *   - Negro profundo:  #1B1B1B  (texto)
 *   - Blanco puro:    #FFFFFF  (cards, fondo de cards)
 *   - Gris perla:     #EDE7DC  (superficies alternas, hover bg)
 *   - Gris medio:     #6B6B6B  (texto secundario)
 *   - Gris borde:     #D6CFC2  (borders sobre crema)
 *
 * Tipografía: Inter (open source). Jerarquía bold sans-serif en H1/H2,
 * regular en body. El script del site de La Voz usa un estilo
 * editorial similar al de la home de bonfire.
 */
export const instrumentosMusicalesLaVozTheme = {
  brand: {
    slug: 'instrumentos-musicales-la-voz',
    name: 'Instrumentos Musicales La Voz',
    shortName: 'La Voz',
    domains: [
      'lavoznicaragua.com',
      'www.lavoznicaragua.com',
      'lavoznicaragua.test',
      'www.lavoznicaragua.test',
    ],
    adminDomains: [
      'admin.lavoznicaragua.com',
      'admin.lavoznicaragua.test',
      'admin.localhost',
      'admin.127.0.0.1',
    ],
    logoPath: '/api/v1/storefront/assets/tenants/instrumentos-musicales-la-voz/branding/logo.svg',
    loginLogoPath: '/api/v1/storefront/assets/tenants/instrumentos-musicales-la-voz/branding/logo.svg',
    // Acentos secundarios: dorado tropical, coral cálido, verde lima,
    // beige arena. Reservados para badges y categorías premium.
    accentColor: '#0E7C7B', // verde tropical (CTA principal)
    accentHoverColor: '#0A6867', // verde tropical oscuro (hover)
    supportEmail: 'contacto@lavoznicaragua.com',
    supportPhone: '+505 2222 0000',
    // La Voz vende via WhatsApp + Facebook. URL configurable para
    // escalar a multiples numeros por sucursal o por categoria.
    supportWhatsapp: '+50588888888',
    // Login theme — fondo claro tropical. Misma paleta editorial
    // que la home. LoginTheme blanco + acentos verdes.
    loginTheme: {
      bg: '#F5F0E6',
      surface: '#FFFFFF',
      surfaceHover: '#EDE7DC',
      border: '#D6CFC2',
      borderStrong: '#0E7C7B',
      text: '#1B1B1B',
      textMuted: '#6B6B6B',
      textSubtle: '#A3A3A3',
      inputBg: '#FFFFFF',
      inputBgFocus: '#FAFAFA',
      danger: '#DC2626',
      dangerText: '#B91C1C',
      dangerBg: '#FEF2F2',
      divider: '#D6CFC2',
    },
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.5rem',
  },
} as const;

export default instrumentosMusicalesLaVozTheme;
