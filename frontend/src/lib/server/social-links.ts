import { apiFetch } from '../api';
import { API_ENDPOINTS } from '../endpoints';

/**
 * Server-side helper para las redes sociales del footer.
 *
 * Fail-soft obligatorio: si el backend no responde, devolvemos [] y el
 * footer renderiza el fallback decorativo — nunca tumba la página.
 */
export interface SocialLinkRow {
  platform: string;
  url: string | null;
}

export async function loadSocialLinks(origin: string, host: string): Promise<SocialLinkRow[]> {
  try {
    const response = await apiFetch<{ data: SocialLinkRow[] }>(
      API_ENDPOINTS.storefront.socialLinks,
      {
        headers: { Accept: 'application/json' },
        origin,
        host,
      },
    );
    return response.data;
  } catch (error) {
    console.error('[layout] apiFetch social-links failed:', error);
    return [];
  }
}
