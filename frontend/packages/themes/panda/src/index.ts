export const pandaTheme = {
  brand: {
    slug: 'panda',
    name: 'Panda Estampados',
    shortName: 'Panda',
    domains: ['pandas-estampados.com', 'www.pandas-estampados.com'],
    adminDomains: ['admin.pandas-estampados.com', 'admin.localhost', 'admin.127.0.0.1'],
    logoPath: '/images/stores/panda-estampados/brand/logo.webp',
    loginLogoPath: '/images/stores/panda-estampados/brand/logo.webp',
    accentColor: '#DC2626',
    accentHoverColor: '#B91C1C',
    supportEmail: 'support@panda-estampados.test',
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.75rem',
  },
} as const;

export default pandaTheme;
