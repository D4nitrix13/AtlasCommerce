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
 *   - clases Tailwind arbitrarias (`bg-[#2D9D8E]`, `focus:border-[#2D9D8E]`)
 *   - estilos inline (`style={{ backgroundColor: AUTH_ACCENT.softBg }}`)
 *
 * Cualquier cambio futuro de paleta (rebrand) se hace desde acá y se
 * propaga a todos los flujos de auth.
 */
export const AUTH_ACCENT = {
  primary: "#2D9D8E",
  hover: "#258777",
  dark: "#1E6F62",
  softBg: "#E6F7F4",
  softBorder: "#C2EBE4",
} as const;

export type AuthAccent = (typeof AUTH_ACCENT)[keyof typeof AUTH_ACCENT];