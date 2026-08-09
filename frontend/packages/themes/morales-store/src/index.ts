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
    loginTheme: {
      bg: '#020617',
      surface: 'rgba(30, 41, 59, 0.8)',
      surfaceHover: 'rgba(51, 65, 85, 0.8)',
      border: 'rgba(255, 255, 255, 0.15)',
      borderStrong: 'rgba(255, 255, 255, 0.4)',
      text: '#FFFFFF',
      textMuted: '#CBD5E1',
      textSubtle: '#64748B',
      inputBg: 'rgba(255, 255, 255, 0.05)',
      inputBgFocus: 'rgba(255, 255, 255, 0.10)',
      danger: '#F87171',
      dangerText: '#FCA5A5',
      dangerBg: 'rgba(127, 29, 29, 0.4)',
      divider: 'rgba(255, 255, 255, 0.15)',
    },
  },
  tokens: {
    fontFamily: 'Inter',
    radius: '0.5rem',
  },
} as const;

export default moralesStoreTheme;
