<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pregunta frecuente de un producto.
 *
 * Tabla: `product_faqs` — relación N:1 con `products`. Cada FAQ es
 * un par `question` (heading del summary) + `answer` (cuerpo del
 * detail) que la PDP renderiza en una sección dedicada reusando el
 * componente genérico `FaqAccordion`.
 *
 * El orden de las FAQs dentro de la sección lo define `position`
 * (ascendente).
 *
 * @property int $id
 * @property int $product_id
 * @property string $question
 * @property string $answer
 * @property int $position
 */
class ProductFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'question',
        'answer',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
