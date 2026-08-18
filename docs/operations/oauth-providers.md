# OAuth providers — Google + Facebook

Guía operativa para registrar los providers OAuth de los tenants
(Panda Estampados, Kitsune, MM-Boutique, Morales Store) en Google
Cloud Console y Facebook Developers. Cada combinación tenant × env
× provider requiere una **Authorized Redirect URI** propia en la
consola del provider.

**Estado**: Implementado en el SDD `oauth-google-facebook-login`
(PRs #17–#21 merged). Esta guía documenta el paso a paso para
operadores.

## Variables de entorno

Definidas en `backend/.env.example`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
OAUTH_REDIRECT_BASE_URL=
```

| Variable | Notas |
| -------- | ----- |
| `GOOGLE_CLIENT_ID` | OAuth client Web application (Google Cloud) |
| `GOOGLE_CLIENT_SECRET` | Idem, no commitear valores reales |
| `FACEBOOK_CLIENT_ID` | App ID de Facebook Developers |
| `FACEBOOK_CLIENT_SECRET` | App Secret, no commitear valores reales |
| `OAUTH_REDIRECT_BASE_URL` | Override por env (default: per-host) |

## Google Cloud Console

### Setup inicial (una vez por proyecto)

1. Crear proyecto en [Google Cloud Console][gcp].
2. **APIs & Services → Library**: habilitar `Google+ API` (legacy)
   o `People API` (recomendado para nuevos proyectos).
3. **APIs & Services → OAuth consent screen**:
   - User type: **External** (soporta cualquier Google account).
   - Support email: contacto del equipo (visible al user).
   - Scopes: `openid`, `https://www.googleapis.com/auth/userinfo.email`,
     `https://www.googleapis.com/auth/userinfo.profile`.
   - Test users (solo en modo Testing): agregar los emails de los
     devs antes de pasar a producción.

### Authorized redirect URIs

**APIs & Services → Credentials → Create credentials → OAuth client ID
→ Web application**.

Por cada combinación tenant × env, agregar la callback URI. La
estructura es:

```text
https://{host}/api/auth/social/google/callback
```

Donde `{host}` es:

| Tenant          | dev                                | staging                              | prod                                  |
| --------------- | ---------------------------------- | ------------------------------------ | ------------------------------------- |
| panda-estampados | `panda-estampados.test`            | `staging.panda-estampados.com`       | `panda-estampados.com`                |
|                 | `admin.panda-estampados.test`      | `staging.admin.panda-estampados.com` | `admin.panda-estampados.com`          |
| kitsune         | `kitsune.test`                     | `staging.kitsune.com`                | `kitsune.com`                         |
|                 | `admin.kitsune.test`               | `staging.admin.kitsune.com`          | `admin.kitsune.com`                   |
| mm-boutique     | `mm-boutique.test`                 | `staging.mm-boutique.com`            | `mm-boutique.com`                     |
|                 | `admin.mm-boutique.test`           | `staging.admin.mm-boutique.com`      | `admin.mm-boutique.com`               |
| morales-store   | `morales-store.test`               | `staging.morales-store.com`          | `morales-store.com`                   |
|                 | `admin.morales-store.test`         | `staging.admin.morales-store.com`    | `admin.morales-store.com`             |

URIs completas a registrar:

```text
# Panda Estampados (Google)
https://panda-estampados.test/api/auth/social/google/callback
https://admin.panda-estampados.test/api/auth/social/google/callback
https://staging.panda-estampados.com/api/auth/social/google/callback
https://staging.admin.panda-estampados.com/api/auth/social/google/callback
https://panda-estampados.com/api/auth/social/google/callback
https://admin.panda-estampados.com/api/auth/social/google/callback

# Kitsune (Google)
https://kitsune.test/api/auth/social/google/callback
https://admin.kitsune.test/api/auth/social/google/callback
https://staging.kitsune.com/api/auth/social/google/callback
https://staging.admin.kitsune.com/api/auth/social/google/callback
https://kitsune.com/api/auth/social/google/callback
https://admin.kitsune.com/api/auth/social/google/callback

# MM-Boutique (Google)
https://mm-boutique.test/api/auth/social/google/callback
https://admin.mm-boutique.test/api/auth/social/google/callback
https://staging.mm-boutique.com/api/auth/social/google/callback
https://staging.admin.mm-boutique.com/api/auth/social/google/callback
https://mm-boutique.com/api/auth/social/google/callback
https://admin.mm-boutique.com/api/auth/social/google/callback

# Morales Store (Google)
https://morales-store.test/api/auth/social/google/callback
https://admin.morales-store.test/api/auth/social/google/callback
https://staging.morales-store.com/api/auth/social/google/callback
https://staging.admin.morales-store.com/api/auth/social/google/callback
https://morales-store.com/api/auth/social/google/callback
https://admin.morales-store.com/api/auth/social/google/callback
```

## Facebook Developers

### Setup inicial (una vez por app)

1. Crear app en [Facebook Developers][fb-dev] → **My Apps → Create
   App → Business**.
2. **Settings → Basic**:
   - App ID y App Secret → copiar a `.env` (NO commitear valores).
   - Privacy Policy URL: `https://panda-estampados.com/privacy`.
   - Terms of Service URL: `https://panda-estampados.com/terms`.
3. **Use cases → Authentication and account creation → Customize**:
   - Permissions: `email`, `public_profile`.
4. **Facebook Login for Business → Settings**:
   - Valid OAuth Redirect URIs: ver lista abajo.
   - Enforce HTTPS: **sí** para prod/staging; dev puede usar HTTP
     en `localhost`.
5. **App Review → Permissions and Features**:
   - `email` (default granted).
   - `public_profile` (default granted).
   - Ambos requieren revisión si la app pasa a Live mode con
     business verification.

### Valid OAuth Redirect URIs

Estructura idéntica a Google:

```text
https://{host}/api/auth/social/facebook/callback
```

URIs completas a registrar (24 totales = 4 tenants × 3 envs × 2 hosts
admin/storefront):

```text
# Panda Estampados (Facebook)
https://panda-estampados.test/api/auth/social/facebook/callback
https://admin.panda-estampados.test/api/auth/social/facebook/callback
https://staging.panda-estampados.com/api/auth/social/facebook/callback
https://staging.admin.panda-estampados.com/api/auth/social/facebook/callback
https://panda-estampados.com/api/auth/social/facebook/callback
https://admin.panda-estampados.com/api/auth/social/facebook/callback

# Kitsune (Facebook)
https://kitsune.test/api/auth/social/facebook/callback
https://admin.kitsune.test/api/auth/social/facebook/callback
https://staging.kitsune.com/api/auth/social/facebook/callback
https://staging.admin.kitsune.com/api/auth/social/facebook/callback
https://kitsune.com/api/auth/social/facebook/callback
https://admin.kitsune.com/api/auth/social/facebook/callback

# MM-Boutique (Facebook)
https://mm-boutique.test/api/auth/social/facebook/callback
https://admin.mm-boutique.test/api/auth/social/facebook/callback
https://staging.mm-boutique.com/api/auth/social/facebook/callback
https://staging.admin.mm-boutique.com/api/auth/social/facebook/callback
https://mm-boutique.com/api/auth/social/facebook/callback
https://admin.mm-boutique.com/api/auth/social/facebook/callback

# Morales Store (Facebook)
https://morales-store.test/api/auth/social/facebook/callback
https://admin.morales-store.test/api/auth/social/facebook/callback
https://staging.morales-store.com/api/auth/social/facebook/callback
https://staging.admin.morales-store.com/api/auth/social/facebook/callback
https://morales-store.com/api/auth/social/facebook/callback
https://admin.morales-store.com/api/auth/social/facebook/callback
```

## Verificación end-to-end

Después de configurar las URIs y poblar `.env`:

```bash
cd backend
composer test -- --filter=SocialAuth
# 60 / 60 deben pasar (PR 2b baseline)

# Smoke test manual
docker exec panda-and-kitsune-dev-backend php artisan serve --host=0.0.0.0 --port=8000
curl -i http://localhost:8000/api/auth/social/google/redirect
# → 302 a accounts.google.com (con client_id presente)
# → 503 oauth_provider_not_configured (si GOOGLE_CLIENT_ID vacío)
# → 400 oauth_state_required (si no hay cookie de sesión)
```

## Troubleshooting

| Síntoma | Causa probable | Fix |
| ------- | -------------- | --- |
| `redirect_uri_mismatch` | Callback URL no registrada en provider | Agregar URL exacta en Google Cloud / FB console |
| `invalid_client` | Client ID o Secret mal copiado | Re-verificar `.env` y reiniciar backend |
| `403 email_verified=false` | User no verificó email en Google | Pedagógico — pedir verificación antes del flow |
| `403 oauth_admin_signup_disabled` | User intentó OAuth desde `admin.*` con email nuevo | Esperado por design (P-C policy) |
| Cookie no se persiste cross-subdomain | Falta `Domain=.eTLD+1` | Verificar `SetSessionDomainFromHost` |

## Riesgos conocidos

- **R4 (dos semanas de review)**: Facebook App Review puede tardar
  hasta 14 días hábiles. Iniciar el proceso lo antes posible,
  especialmente antes del go-live de Morales Store.
- **R5 (cuota)**: Single `client_id/secret` por env, multi-callback
  URL registrada por host. Si un tenant excede quota de Google,
  los demás no se ven afectados (mismo client_id pero rate limit
  compartido).
- **R6 (localhost)**: El redirect en `localhost` omite el
  `Domain=...` (browser lo ignora). Funciona en dev con cookie
  efímera.

[gcp]: https://console.cloud.google.com/
[fb-dev]: https://developers.facebook.com/
