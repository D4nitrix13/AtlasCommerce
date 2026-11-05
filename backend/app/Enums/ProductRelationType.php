<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Riel de recomendación al que pertenece una `product_relations`.
 *
 * `bought_together` alimenta "Frecuentemente Comprados Juntos";
 * `you_may_also_like` alimenta "También Te Puede Gustar".
 */
enum ProductRelationType: string
{
    case BoughtTogether = 'bought_together';

    case YouMayAlsoLike = 'you_may_also_like';
}
