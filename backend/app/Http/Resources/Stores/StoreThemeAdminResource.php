<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use App\Models\Stores\StoreTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource JSON para el theme desde el panel admin.
 *
 * Expone TODOS los campos (incluyendo timestamps internos) para
 * que el admin pueda ver y editar.
 *
 * El `tokens` se devuelve como array PHP nativo. Si el store no
 * tiene theme configurado, devuelve defaults vacíos.
 *
 * @property StoreTheme|null $resource
 */
class StoreThemeAdminResource extends JsonResource
{
    /**
     * Transforma el resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [
                'store_id' => null,
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
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [
            'store_id' => $this->resource->store_id,
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
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
