---
name: astro-themes-and-palette
description: Multi-theme workspace packages (Panda Estampados, Kitsune, MM-Boutique, Morales Store), per-tenant branding, universal auth palette (#2D9D8E teal), theme→brand adapter. Triggers: editing frontend/packages/themes/, src/lib/auth-accent.ts, src/lib/theme.ts, src/config/stores.ts, any auth page, adding a new tenant to the frontend.
---

# Astro Themes & Palette

## Stack

- **Themes workspace**: `frontend/packages/themes/<slug>/` - each store has a package with its `brand` config.
- **Slugs**: `panda-estampados`, `kitsune`, `mm-boutique`, `morales-store` (see `frontend/src/config/stores.ts:STORE_BRANDS`).
- **Symlink**: `frontend/node_modules/@theme/<slug>` → `packages/themes/<slug>` (declared in `pnpm-workspace.yaml`).
- **Theme adapter**: `frontend/src/lib/theme.ts::themeToBrand()` merges the `Theme` (from the API `/api/storefront/theme`) with the static `StoreBrand`.

## Brand structure

Each `brand` in `packages/themes/<slug>/src/index.ts` exposes:

```ts
{
 slug: string,
 name: string,
 shortName: string,
 domains: string[], // e.g. ['panda-estampados.com']
 adminDomains: string[], // e.g. ['admin.panda-estampados.com']
 logoPath: string,
 loginLogoPath: string,
 accentColor: string, // tenant-specific - NOT used in auth flows
 accentHoverColor: string,
 supportEmail: string,
 loginTheme: LoginTheme, // dark/light slate scale
}
```

## Universal auth palette (auth-accent.ts)

**RULE**: Every auth flow (login, forgot, reset, signup, reset-success) uses
the universal palette from `frontend/src/lib/auth-accent.ts`. **DO NOT** use
`brand.accentColor` because it differs per tenant and breaks visual
consistency across auth pages.

```ts
export const AUTH_ACCENT = {
 primary: "#2D9D8E", // teal - CTAs, focus rings, links
 hover: "#258777", // teal hover
 dark: "#1E6F62", // icons on soft background
 softBg: "#E6F7F4", // banners / info-bg
 softBorder: "#C2EBE4", // borders associated with softBg
} as const;
```

Application by convention:

- **CTAs and focus** → Tailwind arbitrary classes: `bg-[#2D9D8E] hover:bg-[#258777] focus:border-[#2D9D8E] focus:ring-[#2D9D8E]/25`
- **Banners** → inline styles with `AUTH_ACCENT.softBg` / `AUTH_ACCENT.softBorder`
- **Icons on softBg** → `text-[#1E6F62]`

See `LoginForm`, `ForgotPasswordForm`, `ResetPasswordForm` for the
canonical patterns.

## Trigger patterns

Activate when:

- Creating a new package in `frontend/packages/themes/<slug>/` (new store).
- Touching `frontend/src/lib/theme.ts` (changes to the adapter).
- Touching `frontend/src/config/stores.ts` (adding to `STORE_BRANDS`).
- Touching `frontend/src/lib/auth-accent.ts` (changing the universal palette - breaking change for auth).
- Creating/modifying any page in `frontend/src/pages/[locale]/{admin,account}/auth/`.
- Creating/modifying components in `frontend/src/components/{admin,storefront,auth}/`.

## Conventions

- **Auth flows → auth-accent, NOT `brand.accentColor`**. If you see a hex other than `#2D9D8E` in an auth page/component, it's a bug.
- **Storefront/admin dashboard → `brand.accentColor`**. Brand identity is the point.
- **React components with dynamic style** → prefer Tailwind arbitrary classes over inline `style` (better consistency check + tree-shaking).
- **New theme package** must:
 1. Create `frontend/packages/themes/<slug>/package.json` (look at an existing one).
 2. Create `src/index.ts` exporting `export const <slug>Theme = { brand: {...}, tokens: {...} }`.
 3. Create symlink: `frontend/node_modules/@theme/<slug>` → `../../packages/themes/<slug>` (done by `pnpm install`).
 4. Register in `frontend/src/config/stores.ts:STORE_BRANDS`.

## Anti-patterns

- ❌ Hardcoding `#2D9D8E` in an auth component without importing from `auth-accent.ts`.
- ❌ Using `var(--login-*)` CSS vars in auth (was the old approach, broke consistency).
- ❌ Passing `brand` as a prop to an auth component so it uses `brand.accentColor`.
- ❌ Assuming a theme works without the `@theme/<slug>` symlink.

## File map

```
frontend/packages/themes/panda-estampados/src/index.ts
frontend/packages/themes/kitsune/src/index.ts
frontend/packages/themes/mm-boutique/src/index.ts
frontend/packages/themes/morales-store/src/index.ts
frontend/src/config/stores.ts ← STORE_BRANDS registry
frontend/src/lib/theme.ts ← theme → brand adapter
frontend/src/lib/auth-accent.ts ← universal auth palette
frontend/src/components/auth/AuthShell.astro ← uses #2D9D8E family
frontend/src/components/admin/auth/LoginForm.tsx ← canonical pattern
frontend/src/components/admin/auth/ForgotPasswordForm.tsx
frontend/src/components/admin/auth/ResetPasswordForm.tsx
frontend/src/pages/[locale]/{admin,account}/auth/ ← the 6 pages
```

## Related skills

- `laravel-multi-tenant-patterns` - backend counterpart.
- `caddyfile-multi-tenant-routing` - host → tenant mapping.
- `docker-stack-orchestration` - per-tenant dev environments.
