<?php

namespace App\Services\Cart;

class CartLineKeyService
{
    /**
     * Generate a deterministic unique key for a cart line.
     *
     * The key is a SHA-256 hash of the normalized combination of
     * product, variant, design, and any additional options.
     */
    public function generate(
        int $productId,
        ?int $productVariantId = null,
        ?int $customDesignId = null,
        array $options = [],
    ): string {
        ksort($options);

        $parts = [
            'product' => $productId,
            'variant' => $productVariantId ?? 'none',
            'design' => $customDesignId ?? 'standard',
        ];

        foreach ($options as $key => $value) {
            $parts[$key] = $value;
        }

        return hash('sha256', implode('|', $parts));
    }
}
