export const mmBoutiqueTheme = {
  brand: {
    slug: 'mm-boutique',
    name: 'MM Boutique',
    shortName: 'MM',
    domains: ['mm-boutique.com', 'www.mm-boutique.com'],
    adminDomains: ['admin.mm-boutique.com', 'admin.mm-boutique.test'],
    logoPath: '/api/v1/storefront/assets/tenants/mm-boutique/branding/logo.webp',
    loginLogoPath: '/api/v1/storefront/assets/tenants/mm-boutique/branding/logo.webp',
    accentColor: '#DB2777',
    accentHoverColor: '#BE185D',
    supportEmail: 'support@mm-boutique.test',
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
    radius: '1rem',
  },
} as const;

export default mmBoutiqueTheme;
