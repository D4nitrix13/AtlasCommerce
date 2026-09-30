/**
 * Paleta de acento universal para todas las pantallas de autenticación
 * (login, forgot-password, reset-password, reset-success, signup).
 *
 * Estas cinco constantes son la ÚNICA fuente de verdad del color de
 * acento en auth flows. Cualquier hex distinto que aparezca en un
 * componente de auth debe tratarse como bug.
 *
 *   - `primary`  → CTAs principales (submit buttons), bordes en focus,
 *                  links primarios.
 *   - `hover`    → `:hover` de los CTAs.
 *   - `dark`     → variantes oscuras (iconos sobre fondos suaves,
 *                  focus de iconos).
 *   - `softBg`   → fondos de banners / info-boxes exitosos.
 *   - `softBorder` → bordes asociados a `softBg`.
 *
 * Los componentes auth consumen estos valores vía:
 *   - clases Tailwind arbitrarias (`bg-[var(--brand-accent,#2D9D8E)]`,
 *     `focus:border-[var(--brand-accent,#2D9D8E)]`)
 *   - estilos inline (`style={{ backgroundColor: AUTH_ACCENT.softBg }}`)
 *
 * Los valores se exponen como `var(--brand-accent-*, fallback)` para
 * que un tenant pueda override la paleta desde su theme (ver
 * `AuthShell.astro`). El fallback hex mantiene el render correcto si
 * el shell no inyecta las vars (modo standalone).
 *
 * Cualquier cambio futuro de paleta (rebrand) se hace desde acá y se
 * propaga a todos los flujos de auth.
 */
export const AUTH_ACCENT = {
  primary: 'var(--brand-accent, #2D9D8E)',
  hover: 'var(--brand-accent-hover, #258777)',
  dark: 'var(--brand-accent-dark, #1E6F62)',
  softBg: 'var(--brand-accent-bg, #E6F7F4)',
  softBorder: 'var(--brand-accent-border, #C2EBE4)',
} as const;

export type AuthAccent = (typeof AUTH_ACCENT)[keyof typeof AUTH_ACCENT];
