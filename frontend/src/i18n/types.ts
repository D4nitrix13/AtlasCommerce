/**
 * Sistema de tipos del i18n.
 *
 * Las keys se declaran EXPLICITAMENTE acá como un literal union para
 * romper la dependencia circular con los locales. Los locales usan
 * `satisfies TranslationKeys` para forzar que cada key exista y
 * mantener autocompletado.
 *
 * Convenciones:
 *  - Keys en dot notation: `domain.subdomain.text`.
 *  - Interpolación con `{varName}`.
 *
 * Para agregar una nueva key:
 *  1. Agregarla al union `KEYS` acá.
 *  2. Agregarla en `es.ts` (canónico).
 *  3. TypeScript marcará error en `en.ts` hasta que la traduzca.
 */

export type TranslationKey =
  // ─── common ──────────────────────────────────────────────────────────
  | "common.loading"
  | "common.error"
  | "common.cancel"
  | "common.save"
  | "common.back"
  | "common.confirm"
  | "common.cancelar"

  // ─── auth ────────────────────────────────────────────────────────────
  | "auth.brand.name"
  | "auth.login.title"
  | "auth.login.subtitle"
  | "auth.login.tab.signin"
  | "auth.login.tab.signup"
  | "auth.login.tab.signup.disabledHint"
  | "auth.login.email.placeholder"
  | "auth.login.email.errorEmpty"
  | "auth.login.email.errorInvalid"
  | "auth.login.password.placeholder"
  | "auth.login.password.errorEmpty"
  | "auth.login.password.errorMinLength"
  | "auth.login.password.toggle.show"
  | "auth.login.password.toggle.hide"
  | "auth.login.submit.idle"
  | "auth.login.submit.loading"
  | "auth.login.divider"
  | "auth.login.social.apple"
  | "auth.login.social.facebook"
  | "auth.login.social.google"
  | "auth.login.help"
  | "auth.login.termsPrefix"
  | "auth.login.termsLink"
  | "auth.login.error.invalid"
  | "auth.login.error.disabled"
  | "auth.login.brand.left.heading"
  | "auth.login.brand.left.body"
  | "auth.login.brand.left.cta"
  | "auth.login.brand.left.cta"
  | "auth.login.createAccount.heading"
  | "auth.login.firstName.placeholder"
  | "auth.login.lastName.placeholder"
  | "auth.login.passwordHint"
  | "auth.login.submit.create"
  | "auth.login.marketing.optIn.label"
  | "auth.login.brand.left.footer"
  | "auth.login.alreadySignedIn"
  | "auth.logout.button"
  | "auth.logout.loading"
  | "auth.userMenu.loading"
  | "auth.guard.redirecting"

  // ─── auth: password reset modal (Fender-style 2-step) ───────────────
  | "auth.passwordReset.title"
  | "auth.passwordReset.bodyEmail"
  | "auth.passwordReset.email.label"
  | "auth.passwordReset.email.submit"
  | "auth.passwordReset.bodyCode"
  | "auth.passwordReset.code.label"
  | "auth.passwordReset.code.placeholder"
  | "auth.passwordReset.newPassword.label"
  | "auth.passwordReset.confirmPassword.label"
  | "auth.passwordReset.password.show"
  | "auth.passwordReset.password.hide"
  | "auth.passwordReset.passwordHint"
  | "auth.passwordReset.submit"
  | "auth.passwordReset.close"
  | "auth.passwordReset.error.emailEmpty"
  | "auth.passwordReset.error.emailInvalid"
  | "auth.passwordReset.error.codeEmpty"
  | "auth.passwordReset.error.codeInvalid"
  | "auth.passwordReset.error.passwordEmpty"
  | "auth.passwordReset.error.passwordMismatch"
  | "auth.passwordReset.error.passwordWeak"
  | "auth.passwordReset.error.generic"
  | "auth.passwordReset.success"

  // ─── admin: shipments ───────────────────────────────────────────────
  | "admin.shipments.title"
  | "admin.shipments.subtitle"
  | "admin.shipments.table.tracking"
  | "admin.shipments.table.order"
  | "admin.shipments.table.status"
  | "admin.shipments.table.cost"
  | "admin.shipments.table.zone"
  | "admin.shipments.table.shippedAt"
  | "admin.shipments.table.deliveredAt"
  | "admin.shipments.table.actions"
  | "admin.shipments.table.empty"
  | "admin.shipments.loading"
  | "admin.shipments.error"
  | "admin.shipments.status.pending"
  | "admin.shipments.status.shipped"
  | "admin.shipments.status.in_transit"
  | "admin.shipments.status.delivered"
  | "admin.shipments.status.cancelled"

  // ─── admin: returns ──────────────────────────────────────────────────
  | "admin.returns.title"
  | "admin.returns.subtitle"
  | "admin.returns.table.id"
  | "admin.returns.table.order"
  | "admin.returns.table.reason"
  | "admin.returns.table.status"
  | "admin.returns.table.requestedAt"
  | "admin.returns.table.resolvedAt"
  | "admin.returns.table.actions"
  | "admin.returns.table.empty"
  | "admin.returns.loading"
  | "admin.returns.error"
  | "admin.returns.status.pending"
  | "admin.returns.status.approved"
  | "admin.returns.status.rejected"
  | "admin.returns.status.completed"
  | "admin.returns.status.cancelled"

  // ─── admin: refunds ──────────────────────────────────────────────────
  | "admin.refunds.title"
  | "admin.refunds.subtitle"
  | "admin.refunds.table.id"
  | "admin.refunds.table.returnRequest"
  | "admin.refunds.table.amount"
  | "admin.refunds.table.status"
  | "admin.refunds.table.processedAt"
  | "admin.refunds.table.actions"
  | "admin.refunds.table.empty"
  | "admin.refunds.loading"
  | "admin.refunds.error"
  | "admin.refunds.status.pending"
  | "admin.refunds.status.completed"
  | "admin.refunds.status.failed"

  // ─── formatters ──────────────────────────────────────────────────────
  | "format.empty"
  | "format.date.locale";

/**
 * Mapa de traducciones: cada key mapea al string traducido.
 * El tipo `Translations` se construye a partir del `TranslationKey`
 * union para que cada locale cubra todas las keys.
 */
export type Translations = Record<TranslationKey, string>;