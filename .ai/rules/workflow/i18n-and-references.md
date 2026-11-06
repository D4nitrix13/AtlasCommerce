# i18n y referencias — ciclo de vida de claves y componentes

> Regla load-bearing para tocar `frontend/src/i18n/**`, `backend/lang/**`
> o para borrar/renombrar cualquier clave, componente o export. Motivada
> por selectores e2e muertos tras renombrar claves, labels hardcodeados
> en un solo idioma y pisotones entre sesiones sobre los diccionarios.

## Claves i18n: ciclo de vida completo

- **Alta**: crear la clave en los TRES lugares del frontend **en el mismo
  cambio**: `frontend/src/i18n/types.ts` (unión `TranslationKey`),
  `locales/es.ts`, `locales/en.ts`. `bun run typecheck` exige paridad —
  si typecheck pasa, los tres están.
- **Baja/rename**: antes de borrar o renombrar una clave, grepear
  referencias en TODO el repo: `frontend/src`, `frontend/tests` (unit +
  **e2e**), `backend/` (si la usa vía trans), `odd/`, `docs/`. Caso real:
  borrar `auth.signup.shell.termsAccept` dejó un
  `getByLabel('auth.signup.shell.termsAccept')` muerto en
  `tests/e2e/customer-auth.spec.ts`.
- **Interpolación**: `{varName}` (ver `i18n/index.ts` `t()`). Nunca
  placeholders sin interpolar en mensajes visibles (caso real: «Acepto
  los {terms} y la {privacy}» se mostraba crudo como error de validación).
- **Registro es**: voseo del producto («Ingresá…», «Escribí…»). `en` en
  registro neutro.
- **Backend**: `backend/lang/{es,en}/*.php` mantienen paridad es/en (los
  sets `validation`, `auth` los consume `SetRequestLocale`). Un mensaje
  nuevo del backend nace en AMBOS idiomas; nunca hardcodear strings de
  usuario en controllers/requests (van por `trans()`).

## Archivos compartidos entre sesiones

- `i18n/locales/*`, `types.ts`, `stores/cart.ts`, layouts, `global.css`
  son **zonas de colisión**: solo ediciones **aditivas** (un bloque por
  feature, namespaced), jamás reescribir el archivo completo ni
  reordenar/agregar claves ajenas. Edición quirúrgica (old_string →
  new_string) releyendo antes.
- El label de un campo obligatorio lleva `*` + `aria-required`; los
  mensajes de error van inline por campo, no solo en un banner (contrato
  UX de los forms — ver `RegisterForm`/`StorefrontLoginForm`).

## Componentes y exports

- Borrar/renombrar un componente o export: grep de referencias primero
  (incluye stories, tests, e2e y docblocks que lo citan — los docblocks
  envejecen y mienten: «el admin usa LoginForm» siguió vivo meses después
  de borrar `LoginForm`).
- Si un test e2e selecciona por texto i18n, preferir selectores estables
  (`#id`, `data-*`) — el texto cambia con cada copywriting.
