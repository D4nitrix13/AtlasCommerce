{{--
  Vista del correo de código de recuperación de contraseña.

  Se renderiza dentro del layout `x-email.layout` y muestra:
  - Título "Restablecé tu contraseña".
  - Saludo personalizado con el nombre del usuario.
  - El código OTP en tamaño grande y color distintivo para fácil lectura.
  - Tiempo de expiración del código.
  - Instrucciones de soporte si el usuario no solicitó el código.

  Variables esperadas:
  - $appName: nombre de la aplicación.
  - $code: código OTP de 6 dígitos en texto plano (solo existe en el correo).
  - $expiresMinutes: tiempo de expiración en minutos.
  - $userName: nombre del usuario (puede ser null).
  - $supportEmail: correo de soporte para contacto.

  IMPORTANTE: Este correo contiene información sensible. El código permite
  restablecer la contraseña del usuario. Si este correo es interceptado,
  la cuenta queda comprometida hasta que expire el código.
--}}

<x-email.layout
    :app-name="$appName"
    :logo-url="$logoUrl ?? null"
    :address="$address ?? null"
    :facebook-url="$facebookUrl ?? null"
    :instagram-url="$instagramUrl ?? null"
    :facebook-icon-url="$facebookIconUrl ?? null"
    :instagram-icon-url="$instagramIconUrl ?? null"
>
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #ffffff; border-radius: 6px;">
        <tr>
            <td style="padding: 32px 38px;">
                <h1 style="margin: 0 0 28px; text-align: center; font-size: 24px; line-height: 1.3; font-weight: 800; color: #18181b;">
                    Restablecé tu contraseña
                </h1>

                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Hola{{ $userName ? " {$userName}" : '' }},
                </p>

                <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Recibiste este correo porque solicitaste restablecer la contraseña de tu cuenta.
                    Para continuar de forma segura, usa el siguiente código:
                </p>

                <p style="margin: 0 0 28px; text-align: center; font-size: 30px; line-height: 1.2; font-weight: 800; letter-spacing: 4px; color: #3155b7;">
                    {{ $code }}
                </p>

                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Este código vence en <strong>{{ $expiresMinutes }} minutos</strong>.
                </p>

                <p style="margin: 0; font-size: 15px; line-height: 1.7; color: #18181b;">
                    Si no solicitaste este código, podés ignorar este correo o contactar al equipo de soporte en
                    <a href="mailto:{{ $supportEmail }}" style="color: #3155b7; text-decoration: underline;">
                        {{ $supportEmail }}
                    </a>.
                </p>
            </td>
        </tr>
    </table>
</x-email.layout>