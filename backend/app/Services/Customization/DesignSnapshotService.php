<?php

namespace App\Services\Customization;

use App\Models\Customization\CustomDesign;

class DesignSnapshotService
{
    /**
     * Build a JSON-serializable snapshot of a custom design for order archival.
     *
     * The snapshot captures the complete design state at the moment of purchase,
     * including all layers grouped by print area, referenced assets, fonts,
     * graphics, and canvas configuration.
     */
    public function build(CustomDesign $customDesign): array
    {
        $customDesign->loadMissing([
            'product',
            'productVariant',
            'productCustomization',
            'designLayers.printArea',
            'designLayers.designAsset',
            'designLayers.designFont',
            'designLayers.designGraphic',
        ]);

        $layersByArea = $this->groupLayersByPrintArea($customDesign->designLayers);

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
            'assets_used' => $this->extractAssets($customDesign->designLayers),
            'fonts_used' => $this->extractFonts($customDesign->designLayers),
            'graphics_used' => $this->extractGraphics($customDesign->designLayers),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function groupLayersByPrintArea($layers): array
    {
        $grouped = [];

        foreach ($layers as $layer) {
            $areaKey = $layer->printArea ? $layer->printArea->code : 'default';

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

    private function extractAssets($layers): array
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

    private function extractFonts($layers): array
    {
        return $layers
            ->filter(fn ($layer) => $layer->design_font_id !== null)
            ->map(fn ($layer) => [
                'id' => $layer->designFont->id,
                'family' => $layer->designFont->family,
                'style' => $layer->designFont->style,
                'weight' => $layer->designFont->weight,
            ])
            ->unique('id')
            ->values()
            ->all();
    }

    private function extractGraphics($layers): array
    {
        return $layers
            ->filter(fn ($layer) => $layer->design_graphic_id !== null)
            ->map(fn ($layer) => [
                'id' => $layer->designGraphic->id,
                'name' => $layer->designGraphic->name,
                'type' => $layer->designGraphic->type,
                'file_url' => $layer->designGraphic->file_url,
            ])
            ->unique('id')
            ->values()
            ->all();
    }
}
