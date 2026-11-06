/**
 * Wrapper storefront del `ForgotPasswordWizard` — el flujo multi-método
 * (email → método de verificación → código → contraseña) para clientes.
 */
import type { ReactElement } from 'react';
import ForgotPasswordWizard from '../../admin/auth/ForgotPasswordWizard';

interface Props {
  locale?: string;
  callbackPath?: string;
}

export default function StorefrontForgotPasswordForm({
  locale = 'es',
  callbackPath = '/account',
}: Props): ReactElement {
  return <ForgotPasswordWizard locale={locale} callbackPath={callbackPath} />;
}
