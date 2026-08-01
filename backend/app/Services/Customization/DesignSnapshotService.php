<?php

namespace App\Services\Customization;

use App\Models\Customization\CustomDesign;
use App\Models\Customization\DesignLayer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio para construir snapshots completos de diseños personalizados.
 *
 * Un snapshot es una representación serializada del estado completo de un
 * diseño, incluyendo el producto, variante, capas agrupadas por área de
 * impresión, y todos los assets, fuentes y gráficos utilizados.
 *
 * Los snapshots se usan para:
 * - Persistir el estado del diseño en pedidos y facturas.
 * - Regenerar previews sin recalcular todo el árbol de relaciones.
 * - Auditar qué assets se usaron en cada diseño.
 *
 * Estructura del snapshot:
 * ```
 * [
 *   'custom_design' => [...],
 *   'product' => [...],
 *   'variant' => [...]|null,
 *   'product_customization' => [...],
 *   'layers_by_print_area' => ['code' => [...]],
 *   'assets_used' => [...],
 *   'fonts_used' => [...],
 *   'graphics_used' => [...],
 *   'generated_at' => 'ISO 8601',
 * ]
 * ```
 */
class DesignSnapshotService
{
    /**
     * Construye el snapshot completo de un diseño personalizado.
     *
     * Carga las relaciones necesarias en una sola consulta (eager loading)
     * y agrupa las capas por código de área de impresión.
     *
     * @param  CustomDesign  $customDesign  Diseño del que se genera el snapshot.
     * @return array<string, mixed> Snapshot estructurado del diseño.
     */
    public function build(CustomDesign $customDesign): array
    {
        $customDesign->loadMissing(relations: [
            'product',
            'productVariant',
            'productCustomization',
            'designLayers.printArea',
            'designLayers.designAsset',
            'designLayers.designFont',
            'designLayers.designGraphic',
        ]);

        $layersByArea = $this->groupLayersByPrintArea(layers: $customDesign->designLayers);

        return [
            'custom_design' => [
                'id' => $customDesign->id,
                'name' => $customDesign->name,
                'status' => $customDesign->status,
                'background_color' => $customDesign->background_color,
                'canvas_width_px' => $customDesign->canvas_width_px,
                'canvas_height_px' => $customDesign->canvas_height_px,
                'canvas_settings' => $customDesign->canvas_settings,
            ],
            'product' => [
                'id' => $customDesign->product->id,
                'name' => $customDesign->product->name,
                'slug' => $customDesign->product->slug,
                'sku' => $customDesign->product->sku,
            ],
            'variant' => $customDesign->productVariant ? [
                'id' => $customDesign->productVariant->id,
                'name' => $customDesign->productVariant->variant_name,
                'sku' => $customDesign->productVariant->variant_sku,
            ] : null,
            'product_customization' => [
                'id' => $customDesign->productCustomization->id,
                'type' => $customDesign->productCustomization->customization_type,
                'editor' => $customDesign->productCustomization->editor_type,
            ],
            'layers_by_print_area' => $layersByArea,
            'assets_used' => $this->extractAssets(layers: $customDesign->designLayers),
            'fonts_used' => $this->extractFonts(layers: $customDesign->designLayers),
            'graphics_used' => $this->extractGraphics(layers: $customDesign->designLayers),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Agrupa las capas por código de área de impresión.
     *
     * Si una capa no tiene área de impresión asignada, se agrupa bajo
     * la clave `'default'`.
     *
     * @param  Collection<int, DesignLayer>  $layers  Capas del diseño.
     * @return array<string, array<int, array<string, mixed>>> Capas agrupadas por código de área.
     */
    private function groupLayersByPrintArea(Collection $layers): array
    {
        $grouped = [];

        foreach ($layers as $layer) {
            $areaKey = $layer->printArea?->code ?? 'default';

            $grouped[$areaKey][] = [
                'id' => $layer->id,
                'type' => $layer->layer_type,
                'name' => $layer->name,
                'text_content' => $layer->text_content,
                'color' => $layer->color,
                'background_color' => $layer->background_color,
                'alignment' => $layer->alignment,
                'position_x' => $layer->position_x,
                'position_y' => $layer->position_y,
                'width' => $layer->width,
                'height' => $layer->height,
                'scale_x' => $layer->scale_x,
                'scale_y' => $layer->scale_y,
                'rotation' => $layer->rotation,
                'opacity' => $layer->opacity,
                'z_index' => $layer->z_index,
                'locked' => $layer->locked,
                'visible' => $layer->visible,
                'styles' => $layer->styles,
                'effects' => $layer->effects,
            ];
        }

        return $grouped;
    }

    /**
     * Extrae los assets de diseño utilizados en las capas.
     *
     * Filtra solo las capas que tienen un `design_asset_id` asignado
     * y retorna sus datos relevantes (nombre original, ruta, MIME type).
     *
     * @param  Collection<int, DesignLayer>  $layers  Capas del diseño.
     * @return array<int, array{id: int, original_name: string, file_path: string, mime_type: string}>
     */
    private function extractAssets(Collection $layers): array
    {
        return $layers
            ->filter(fn ($layer) => $layer->design_asset_id !== null)
            ->map(fn ($layer) => [
                'id' => $layer->designAsset->id,
                'original_name' => $layer->designAsset->original_name,
                'file_path' => $layer->designAsset->file_path,
                'mime_type' => $layer->designAsset->mime_type,
            ])
            ->values()
            ->all();
    }

    /**
     * Extrae las fuentes tipográficas utilizadas en las capas.
     *
     * Filtra las capas con `design_font_id` y retorna datos de la fuente
     * (familia, estilo, peso). Elimina duplicados por ID de fuente.
     *
     * @param  Collection<int, DesignLayer>  $layers  Capas del diseño.
     * @return array<int, array{id: int, family: string, style: string, weight: string}>
     */
    private function extractFonts(Collection $layers): array
    {
        return $layers
            ->filter(fn ($layer) => $layer->design_font_id !== null)
            ->map(fn ($layer) => [
                'id' => $layer->designFont->id,
                'family' => $layer->designFont->family,
                'style' => $layer->designFont->style,
                'weight' => $layer->designFont->weight,
            ])
            ->unique(key: 'id')
            ->values()
            ->all();
    }

    /**
     * Extrae los gráficos de biblioteca utilizados en las capas.
     *
     * Filtra las capas con `design_graphic_id` y retorna datos del gráfico
     * (nombre, tipo, URL). Elimina duplicados por ID de gráfico.
     *
     * @param  Collection<int, DesignLayer>  $layers  Capas del diseño.
     * @return array<int, array{id: int, name: string, type: string, file_url: string}>
     */
    private function extractGraphics(Collection $layers): array
    {
        return $layers
            ->filter(fn ($layer) => $layer->design_graphic_id !== null)
            ->map(fn ($layer) => [
                'id' => $layer->designGraphic->id,
                'name' => $layer->designGraphic->name,
                'type' => $layer->designGraphic->type,
                'file_url' => $layer->designGraphic->file_url,
            ])
            ->unique(key: 'id')
            ->values()
            ->all();
    }
}
