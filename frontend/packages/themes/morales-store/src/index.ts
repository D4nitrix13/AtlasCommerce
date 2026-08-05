export const moralesStoreTheme = {
  brand: {
    slug: 'morales-store',
    name: 'Morales Store',
    shortName: 'Morales',
    domains: ['morales-store.com', 'www.morales-store.com'],
    adminDomains: ['admin.morales-store.com', 'admin.morales-store.test'],
    logoPath: '/images/stores/morales-store/brand/logo.webp',
    loginLogoPath: '/images/stores/morales-store/brand/logo.webp',
    accentColor: '#2563EB',
    accentHoverColor: '#1D4ED8',
    supportEmail: 'support@morales-store.test',
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.5rem',
  },
} as const;

export default moralesStoreTheme;
