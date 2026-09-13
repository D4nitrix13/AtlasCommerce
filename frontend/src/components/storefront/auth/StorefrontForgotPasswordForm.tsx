/**
 * Wrapper que reutiliza el admin ForgotPasswordForm con labels en
 * castellano para el cliente.
 */
import type { ReactElement } from 'react';
import ForgotPasswordForm from '../../admin/auth/ForgotPasswordForm';

export default function StorefrontForgotPasswordForm(): ReactElement {
  return <ForgotPasswordForm callbackPath="/account" locale="es" />;
}
