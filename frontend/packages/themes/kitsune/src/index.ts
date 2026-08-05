export const kitsuneTheme = {
  brand: {
    slug: 'kitsune',
    name: 'Kitsune Store',
    shortName: 'Kitsune',
    domains: ['kitsune.com', 'www.kitsune.com'],
    adminDomains: ['admin.kitsune.com', 'admin.kitsune.test'],
    logoPath: '/images/stores/kitsune/brand/logo.webp',
    loginLogoPath: '/images/stores/kitsune/brand/logo.webp',
    accentColor: '#7C3AED',
    accentHoverColor: '#6D28D9',
    supportEmail: 'support@kitsune.test',
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.75rem',
  },
} as const;

export default kitsuneTheme;
