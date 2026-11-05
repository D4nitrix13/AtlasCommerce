<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público de red social para el footer del storefront.
 *
 * Expone solo los campos que el visitante necesita para renderizar el
 * enlace. NO expone campos admin (`store_id`, `is_active`) ni timestamps.
 *
 * @mixin \App\Models\Stores\StoreSocialLink
 */
class StorefrontSocialLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->resource->platform,
            'url' => $this->resource->url,
            'display_name' => $this->resource->display_name,
            'icon_url' => $this->resource->icon_url,
            'sort_order' => $this->resource->sort_order,
        ];
    }
}
