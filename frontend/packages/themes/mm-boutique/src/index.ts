export const mmBoutiqueTheme = {
  brand: {
    slug: 'mm-boutique',
    name: 'MM Boutique',
    shortName: 'MM',
    domains: ['mm-boutique.com', 'www.mm-boutique.com'],
    adminDomains: ['admin.mm-boutique.com', 'admin.mm-boutique.test'],
    logoPath: '/images/stores/mm-boutique/brand/logo.webp',
    loginLogoPath: '/images/stores/mm-boutique/brand/logo.webp',
    accentColor: '#DB2777',
    accentHoverColor: '#BE185D',
    supportEmail: 'support@mm-boutique.test',
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '1rem',
  },
} as const;

export default mmBoutiqueTheme;
