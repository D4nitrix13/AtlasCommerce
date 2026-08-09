export const pandaTheme = {
  brand: {
    slug: 'panda-estampados',
    name: 'Panda Estampados',
    shortName: 'Panda',
    domains: ['panda-estampados.com', 'www.panda-estampados.com'],
    adminDomains: ['admin.panda-estampados.com', 'admin.localhost', 'admin.127.0.0.1'],
    logoPath: '/images/stores/panda-estampados/brand/logo.webp',
    loginLogoPath: '/images/stores/panda-estampados/brand/logo.webp',
    // Paleta derivada del logo de Panda Estampados (monocromático
    // + acentos del "universo panda + estampados"). El logo es
    // estrictamente negro/blanco, así que los acentos de la web
    // expanden la paleta sin romper la identidad.
    //   60% blanco · 30% negro · 10% color de acento
    accentColor: '#0A0A0A',        // negro profundo (acento principal, CTAs)
    accentHoverColor: '#262626',   // gris muy oscuro (hover)
    // Acentos secundarios reservados para badges / highlights:
    //   - Rosa chicle:    #FF6B9D  (estampados, badges "nuevo")
    //   - Amarillo sol:   #FFD93D  (sellos, ofertas)
    //   - Naranja coral:  #FF8A5B  (alertas, hover secundario)
    //   - Morado tinta:   #6A4C93  (categorías premium)
    //   - Azul cielo:     #4FC3F7  (suscripciones, info)
    // Neutros (del logo):
    //   - Negro profundo: #0A0A0A  (texto principal)
    //   - Blanco puro:     #FFFFFF  (fondo principal)
    //   - Gris perla:      #F5F5F5  (cards, fondos alternos)
    //   - Gris medio:      #6B6B6B  (texto secundario, subtítulos)
    supportEmail: 'support@panda-estampados.test',
    // Panda usa fondo claro para acompañar el logo monocromo B/N.
    // Tokens sincronizados con `LoginTheme` en `src/config/stores.ts`.
    loginTheme: {
      bg: '#FFFFFF',
      surface: '#F5F5F5',
      surfaceHover: '#E5E5E5',
      border: '#E5E5E5',
      borderStrong: '#0A0A0A',
      text: '#0A0A0A',
      textMuted: '#525252',
      textSubtle: '#A3A3A3',
      inputBg: '#FFFFFF',
      inputBgFocus: '#FAFAFA',
      danger: '#DC2626',
      dangerText: '#B91C1C',
      dangerBg: '#FEF2F2',
      divider: '#E5E5E5',
    },
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.75rem',
  },
} as const;

export default pandaTheme;
