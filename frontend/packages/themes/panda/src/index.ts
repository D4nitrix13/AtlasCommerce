export const pandaTheme = {
  brand: {
    slug: 'panda',
    name: 'Panda Estampados',
    shortName: 'Panda',
    domains: ['pandas-estampados.com', 'www.pandas-estampados.com'],
    adminDomains: ['admin.pandas-estampados.com', 'admin.localhost', 'admin.127.0.0.1'],
    logoPath: '/images/stores/panda-estampados/brand/logo.webp',
    loginLogoPath: '/images/stores/panda-estampados/brand/logo.webp',
    // Paleta derivada del logo de Pandas Estampados (monocromático
    // + acentos del "universo panda + estampados"). El logo es
    // estrictamente negro/blanco, así que los acentos de la web
    // expanden la paleta sin romper la identidad.
    //   60% blanco · 30% negro · 10% color de acento
    accentColor: '#7CB342',        // verde bambú (acento principal, CTAs)
    accentHoverColor: '#5a9a30',   // verde bambú oscuro (hover)
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
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.75rem',
  },
} as const;

export default pandaTheme;
