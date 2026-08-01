{{--
  Vista del correo de código OTP para autenticación de dos factores.

  Se renderiza dentro del layout `x-email.layout` y muestra:
  - Título "Código de verificación".
  - Saludo personalizado con el nombre del usuario.
  - El código OTP en tamaño grande y color distintivo para fácil lectura.
  - Tiempo de expiración del código.
  - Instrucciones de soporte si el usuario no solicitó el código.

  Variables esperadas:
  - $appName: nombre de la aplicación.
  - $code: código OTP en texto plano (solo existe en el correo, nunca en la BD).
  - $expiresMinutes: tiempo de expiración en minutos.
  - $userName: nombre del usuario (puede ser null).
  - $supportEmail: correo de soporte para contacto.
  - $logoUrl, $address, $facebookUrl, $instagramUrl, etc.: branding.

  IMPORTANTE: Este correo contiene información sensible. El código OTP
  permite activar 2FA en la cuenta del usuario. Si este correo es
  interceptado, la seguridad de 2FA queda comprometida.
--}}

<x-email.layout
    :app-name="$appName"
    :logo-url="$logoUrl"
    :address="$address"
    :facebook-url="$facebookUrl"
    :instagram-url="$instagramUrl"
    :facebook-icon-url="$facebookIconUrl"
    :instagram-icon-url="$instagramIconUrl"
>
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #ffffff; border-radius: 6px;">
        <tr>
            <td style="padding: 32px 38px;">
                <h1 style="margin: 0 0 28px; text-align: center; font-size: 24px; line-height: 1.3; font-weight: 800; color: #18181b;">
                    Código de verificación
                </h1>

                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Hola{{ $userName ? " {$userName}" : '' }},
                </p>

                <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Recibiste este correo porque estás configurando la verificación en dos pasos de tu cuenta.
                    Para continuar de forma segura, usa el siguiente código:
                </p>

                <p style="margin: 0 0 28px; text-align: center; font-size: 30px; line-height: 1.2; font-weight: 800; letter-spacing: 4px; color: #3155b7;">
                    {{ $code }}
                </p>

                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Este código vence en <strong>{{ $expiresMinutes }} minutos</strong>.
                </p>

                <p style="margin: 0; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Si no solicitaste este código, puedes ignorar este correo o contactar al equipo de soporte en
                    <a href="mailto:{{ $supportEmail }}" style="color: #3155b7; text-decoration: underline;">
                        {{ $supportEmail }}
                    </a>.
                </p>
            </td>
        </tr>
    </table>
</x-email.layout>
