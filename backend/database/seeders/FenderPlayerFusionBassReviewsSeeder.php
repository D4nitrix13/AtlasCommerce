<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Support\ProductReview;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder — Reseñas del producto Fender Player Fusion Precision Bass HH.
 *
 * Puebla `product_reviews` con 8 reseñas realistas (mezcla de ratings
 * 3-5★, promedio ~4.4) para validar la lógica de promedio, filtros y
 * paginación en la PDP. Las reseñas cubren aspectos variados del
 * instrumento (pastillas Custombucker, acabado Halo Metallic, mástil,
 * electrónica, peso, estuche) y se distribuyen en los últimos 90 días
 * para que los filtros por fecha tengan muestra representativa.
 *
 * Idempotente: borra las reseñas previas del producto antes de
 * re-insertar usando el slug como discriminante — no contamina
 * reseñas de otros productos. Re-ejecutar produce siempre exactamente
 * 8 reseñas (no más, no menos).
 *
 * Depende de:
 *   - `FenderPlayerFusionBassSeeder` (registra el producto por slug).
 *   - `CustomerTestSeeder` o `AuthFlowTestUsersSeeder` (registran al
 *     menos 3 customers en la tabla `customers`). El seeder NO crea
 *     customers — toma los IDs existentes vía `pluck('id')` y rota
 *     entre ellos para no inflar la BD con datos de prueba.
 *
 * Uso:
 *   php artisan db:seed --class=FenderPlayerFusionBassReviewsSeeder
 */
final class FenderPlayerFusionBassReviewsSeeder extends Seeder
{
    private const PRODUCT_SLUG = 'fender-player-fusion-bass-hh';

    /**
     * Catálogo inmutable de las 8 reseñas. `customer` es el índice en
     * el pool de customers existentes (rotación 0/1/2) para que cada
     * customer contribuya y ninguno domine la distribución.
     *
     * @var list<array{customer: int, rating: int, days_ago: int, title: string, body: string}>
     */
    private const REVIEWS = [
        ['customer' => 0, 'rating' => 5, 'days_ago' => 5,  'title' => 'Mástil rápido',        'body' => 'El mástil es rápido y cómodo. Le quito una estrella porque el peso se siente al tocar de pie.'],
        ['customer' => 1, 'rating' => 5, 'days_ago' => 25, 'title' => 'Acabado precioso',     'body' => 'El Halo Metallic en persona es espectacular. Vale cada centavo.'],
        ['customer' => 2, 'rating' => 4, 'days_ago' => 50, 'title' => 'Sonido brutal',        'body' => 'Las pastillas Custombucker suenan increíbles. Mucho más cálidas que las del bajo que tenía antes.'],
        ['customer' => 0, 'rating' => 5, 'days_ago' => 60, 'title' => 'Ultra Heavy Aged',     'body' => 'El relic es perfecto. Se ve tocado pero sin abuso. Los herrajes envejecidos lucen naturales.'],
        ['customer' => 1, 'rating' => 4, 'days_ago' => 70, 'title' => 'Setup impecable',      'body' => 'Llegó con acción baja y cejilla bien cortada. Solo ajusté un poco la curvatura del mástil.'],
        ['customer' => 2, 'rating' => 5, 'days_ago' => 75, 'title' => 'Pastillas hand-wound', 'body' => 'Las Custombucker son exactamente lo que esperaba del custom shop. El pickup del mástil tiene un growl increíble.'],
        ['customer' => 0, 'rating' => 3, 'days_ago' => 85, 'title' => 'Peso considerable',    'body' => 'Es un bajo pesado (4.1 kg se siente). El sonido lo compensa pero requiere correa gruesa.'],
        ['customer' => 1, 'rating' => 5, 'days_ago' => 90, 'title' => 'Estuche de lujo',      'body' => 'El estuche rígido incluido vale la pena. Forro interior aterciopelado, cerraduras de metal.'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $product = Product::query()
                ->where(column: 'slug', operator: '=', value: self::PRODUCT_SLUG)
                ->first();
            if ($product === null) {
                throw new RuntimeException(sprintf(
                    'Product con slug "%s" no existe. Corre FenderPlayerFusionBassSeeder primero.',
                    self::PRODUCT_SLUG,
                ));
            }

            // Necesitamos al menos 3 customers como autores de reseñas.
            // Si no hay suficientes, los creamos al vuelo con el factory
            // (Customer::user_id es nullable, no necesitamos User). Esto
            // hace el seeder autosuficiente para dev/CI sin requerir
            // CustomerTestSeeder previo.
            $customerIds = DB::table('customers')->limit(8)->pluck('id')->all();
            $needed = 3 - count($customerIds);
            if ($needed > 0) {
                for ($i = 0; $i < $needed; $i++) {
                    $customer = Customer::factory()->create();
                    $customerIds[] = $customer->id;
                }
                // Releer IDs por si los nuevos están fuera del limit(8).
                $customerIds = DB::table('customers')->limit(8)->pluck('id')->all();
            }

            // Nullable: si el producto no está publicado en ninguna tienda
            // (caso teórico), la reseña queda como reseña de producto
            // global sin tenant específico.
            $storeId = DB::table('product_stores')
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->limit(1)
                ->value('store_id');

            // Limpiar reseñas previas solo de este producto (idempotencia).
            // El borrado es scoped a product_id para nunca tocar reseñas
            // de otros productos en el mismo run.
            ProductReview::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->delete();

            foreach (self::REVIEWS as $review) {
                $customerId = $customerIds[$review['customer']] ?? $customerIds[0];

                ProductReview::query()->create(attributes: [
                    'product_id' => $product->id,
                    'customer_id' => $customerId,
                    'store_id' => $storeId,
                    'rating' => $review['rating'],
                    // Concatenamos título + cuerpo con punto para que el
                    // frontend pueda parsear visualmente; nunca dejamos
                    // el comment vacío (la PDP lo requiere poblada).
                    'comment' => $review['title'].'. '.$review['body'],
                    'is_approved' => true,
                    'reviewed_at' => now()->subDays(value: $review['days_ago']),
                ]);
            }
        });
    }
}
