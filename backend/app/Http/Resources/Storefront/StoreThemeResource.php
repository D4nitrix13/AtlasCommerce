<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Models\Stores\StoreTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource JSON para el theme de una tienda en el storefront.
 *
 * Solo expone los campos publicos (branding visible). NO expone el
 * `store_id` ni timestamps internos.
 *
 * Si el theme no existe (null), el resource devuelve un objeto vacio
 * con defaults sensatos para que el frontend no se rompa.
 *
 * @property StoreTheme|null $resource
 */
class StoreThemeResource extends JsonResource
{
    /**
     * Transforma el resource en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return $this->defaults();
        }

        return [
            'short_name' => $this->resource->short_name,
            'logo_path' => $this->resource->logo_path,
            'login_logo_path' => $this->resource->login_logo_path,
            'favicon_path' => $this->resource->favicon_path,
            'accent_color' => $this->resource->accent_color,
            'accent_hover_color' => $this->resource->accent_hover_color,
            'primary_color' => $this->resource->primary_color,
            'support_email' => $this->resource->support_email,
            'phone' => $this->resource->phone,
            'whatsapp' => $this->resource->whatsapp,
            'tokens' => $this->resource->tokens ?? [],
        ];
    }

    /**
     * Defaults cuando el store no tiene theme configurado.
     *
     * El frontend puede usar estos valores como fallback.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'short_name' => null,
            'logo_path' => null,
            'login_logo_path' => null,
            'favicon_path' => null,
            'accent_color' => null,
            'accent_hover_color' => null,
            'primary_color' => null,
            'support_email' => null,
            'phone' => null,
            'whatsapp' => null,
            'tokens' => [],
        ];
    }
}
