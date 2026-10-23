<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesVariantOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Valida el multipart de POST /admin/products/{product}/images/upload.
 *
 * Este request es MUTUAMENTE EXCLUYENTE con
 * {@see ProductImageStoreRequest}: el endpoint `images` acepta `image_url`
 * (referencia externa, p.ej. un CDN de terceros) y este acepta `image`
 * (binario). Son dos formas de crear la misma fila y mezclarlas en un
 * solo endpoint haría ambiguo cuál de las dos gana; separarlas deja el
 * contrato de cada una sin ambigüedad y no rompe a los clientes que hoy
 * usan `image_url`.
 *
 * Reglas:
 *  - image: requerido. El MIME se valida contra el contenido real del
 *    archivo contra la allow-list de `config/assets.php` (`assets.upload.mimes`).
 *    El peso se valida contra `assets.upload.max_kilobytes`.
 *  - alt_text: opcional, max 160 (texto alternativo para a11y).
 *  - is_primary: bool opcional.
 *  - sort_order: int opcional >= 0. Si no se pasa, se asigna automáticamente
 *    al final (max + 1) del producto.
 *  - variant_id: int opcional, y tiene que ser una variante DEL producto
 *    de la ruta — ver {@see ValidatesVariantOwnership}. Las filas que crea
 *    este endpoint son las 4 resoluciones de UNA foto, así que la variante
 *    se aplica a todas por igual (no hay a cuál elegir).
 */
class ProductImageUploadRequest extends FormRequest
{
    use ValidatesVariantOwnership;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Exists>>
     */
    public function rules(): array
    {
        $mimes = implode(separator: ',', array: (array) config(key: 'assets.upload.mimes', default: ['image/jpeg', 'image/png', 'image/webp']));
        $maxKilobytes = (int) config(key: 'assets.upload.max_kilobytes', default: 8192);

        return [
            // Orden pensado para que cada error sea el más útil posible:
            //
            //  - `required` NO alcanza para el archivo vacío: Laravel evalúa
            //    `getSize() >= 0`, que un archivo de 0 bytes cumple igual.
            //    Por eso el piso explícito va con `min`, que Laravel mide en
            //    KILOBYTES para archivos (`getSize() / 1024`) — un piso de 1 KB
            //    descarta el vacío y el texto pegado con extensión .jpg,
            //    y no toca una foto real (una de catálogo pesa cientos de KB).
            //  - `file` descarta un string en un campo llamado `image`.
            //  - `mimetypes` va DESPUÉS de `min` a propósito: para un archivo
            //    vacío el MIME real es `application/x-empty`, y si el filtro
            //    corriera primero el admin recibiría "formato no permitido"
            //    cuando el problema real es que no subió nada.
            //
            //  - No se usa la regla `image`: con el MIME ya acotado por
            //    allow-list es redundante, y como corre antes que
            //    `mimetypes` termina ROBANDO el mensaje — un PDF renombrado
            //    a .jpg falla ambas y el admin lee "está dañada" en vez de
            //    "el formato no está permitido", que es el dato accionable.
            'image' => ['required', 'file', 'min:1', 'mimetypes:'.$mimes, 'max:'.$maxKilobytes],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'variant_id' => $this->variantIdRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxKilobytes = (int) config(key: 'assets.upload.max_kilobytes', default: 8192);
        $mimes = implode(separator: ', ', array: (array) config(key: 'assets.upload.mimes', default: ['image/jpeg', 'image/png', 'image/webp']));

        return [
            'image.required' => 'Tenés que adjuntar una imagen.',
            'image.file' => 'El campo imagen tiene que ser un archivo.',
            'image.min' => 'La imagen está vacía.',
            'image.mimetypes' => 'El formato de la imagen no está permitido. Aceptamos: '.$mimes.'.',
            'image.max' => 'La imagen no puede superar los '.$maxKilobytes.' KB.',
            ...$this->variantIdMessages(),
        ];
    }

    /**
     * El binario ya validado, con el tipo del FormRequest garantizado.
     *
     * Existe para no repetir el `instanceof` en el controller y para que
     * el tipo estricto sobreviva a `validated()`, que devuelve `mixed`.
     *
     * El nombre NO puede ser `image()`: `Illuminate\Http\Request` ya
     * declara `image(string $key): ?Illuminate\Image\Image` y el
     * FormRequest hereda de ahí. Una firma incompatible en la subclase es
     * un error fatal de PHP al cargar la clase — la ruta entera deja de
     * existir, no sólo este método.
     */
    public function uploadedImage(): UploadedFile
    {
        $file = $this->file(key: 'image');

        assert($file instanceof UploadedFile);

        return $file;
    }
}
