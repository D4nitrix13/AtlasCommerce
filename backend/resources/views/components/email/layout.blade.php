{{--
  Layout base para correos electrónicos de la aplicación.

  Componente Blade reutilizable que define la estructura HTML del correo:
  - Contenedor centralizado con fondo color `#3f5fb8`.
  - Tarjeta principal con fondo `#eef2f7` y sombra sutil.
  - Logo de la aplicación en encabezado circular.
  - Slot principal para el contenido del correo.
  - Pie de página con dirección, redes sociales y copyright.

  Variables recibidas (con defaults de config/brand.php):
  - $appName: nombre de la aplicación (config('app.name')).
  - $logoUrl: URL del logo (asset de brand.logo_path).
  - $address: dirección física de la empresa.
  - $facebookUrl / $instagramUrl: URLs de redes sociales.
  - $facebookIconUrl / $instagramIconUrl: URLs de iconos de redes.

  El diseño usa tablas HTML inline para compatibilidad con clientes
  de correo que no soportan CSS moderno (Outlook, Gmail legacy).
--}}

@props([
    'appName' => config('app.name'),
    'logoUrl' => asset(config('brand.logo_path')),
    'address' => config('brand.address'),
    'facebookUrl' => config('brand.social.facebook_url'),
    'instagramUrl' => config('brand.social.instagram_url'),
    'facebookIconUrl' => asset(config('brand.social.facebook_icon_path')),
    'instagramIconUrl' => asset(config('brand.social.instagram_icon_path')),
])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $appName }}</title>
</head>

<body style="margin: 0; padding: 0; background-color: #3f5fb8; font-family: Arial, Helvetica, sans-serif; color: #18181b;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; background-color: #3f5fb8; padding: 48px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 560px; background-color: #eef2f7; border-radius: 4px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);">
                    <tr>
                        <td align="center" style="padding: 44px 32px 28px;">
                            <div
                                style="
                                    width: 96px;
                                    height: 96px;
                                    border-radius: 50%;
                                    overflow: hidden;
                                    background-color: #ffffff;
                                    display: inline-block;
                                    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
                                "
                            >
                                <img
                                    src="{{ $logoUrl }}"
                                    alt="{{ $appName }}"
                                    width="96"
                                    height="96"
                                    style="
                                        display: block;
                                        width: 96px;
                                        height: 96px;
                                        object-fit: cover;
                                        border: 0;
                                    "
                                >
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 32px 32px;">
                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 48px 28px;">
                            @if ($address)
                                <p style="margin: 0 0 26px; font-size: 13px; line-height: 1.6; color: #27272a;">
                                    {{ $address }}
                                </p>
                            @endif

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="left" style="font-size: 13px; font-weight: 700; color: #3155b7;">
                                        {{ $appName }}
                                    </td>

                                    <td align="right">
                                        @if ($facebookUrl)
                                            <a href="{{ $facebookUrl }}" target="_blank" style="display: inline-block; margin-left: 12px; text-decoration: none;">
                                                <img
                                                    src="{{ $facebookIconUrl }}"
                                                    alt="Facebook"
                                                    width="22"
                                                    style="display: inline-block; width: 22px; height: 22px;"
                                                >
                                            </a>
                                        @endif

                                        @if ($instagramUrl)
                                            <a href="{{ $instagramUrl }}" target="_blank" style="display: inline-block; margin-left: 12px; text-decoration: none;">
                                                <img
                                                    src="{{ $instagramIconUrl }}"
                                                    alt="Instagram"
                                                    width="22"
                                                    style="display: inline-block; width: 22px; height: 22px;"
                                                >
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin: 18px 0 0; font-size: 12px; color: #dbeafe;">
                    © {{ date('Y') }} {{ $appName }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
