<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/categories/{category}.
 *
 * Reglas:
 *  - Todos los campos opcionales (`sometimes`).
 *  - slug único excepto en el row actual.
 *  - parent_id: NO puede ser self ni descendiente (validado en
 *    withValidator para prevenir ciclos en la jerarquía).
 */
class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $categoryId = (int) $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'slug' => [
                'sometimes',
                'string',
                'min:2',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(table: 'categories', column: 'slug')->ignore($categoryId),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Valida que el parent_id no cree un ciclo en la jerarquía.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $category = $this->route('category');
            if (! $category instanceof \App\Models\Catalog\Category) {
                return;
            }

            $newParentId = $this->input(key: 'parent_id');
            // Si parent_id no viene en el body → no validar (sometimes).
            if ($newParentId === null) {
                return;
            }

            $newParentId = (int) $newParentId;

            // Self-as-parent: trivial.
            if ($newParentId === (int) $category->id) {
                $validator->errors()->add(
                    'parent_id',
                    'Una categoría no puede ser su propia padre.',
                );

                return;
            }

            // Cycle: si el newParent está en la descendencia de $category,
            // se crearía un loop. Caminamos ancestros del newParent y
            // verificamos que ninguno sea $category.
            if ($this->wouldCreateCycle(currentId: (int) $category->id, proposedParentId: $newParentId)) {
                $validator->errors()->add(
                    'parent_id',
                    'El padre indicado crearía un ciclo en la jerarquía.',
                );
            }
        });
    }

    /**
     * Helper: detecta si asignar proposedParentId como padre de currentId
     * crearía un ciclo. Recorre los ancestros del proposedParent hasta
     * la raíz; si encuentra currentId en el camino, hay ciclo.
     */
    private function wouldCreateCycle(int $currentId, int $proposedParentId): bool
    {
        $visited = [];
        $cursor = $proposedParentId;

        // Cap de 100 iteraciones para evitar loops infinitos en datos rotos.
        for ($i = 0; $i < 100; $i++) {
            if ($cursor === $currentId) {
                return true;
            }
            if (in_array($cursor, $visited, strict: true)) {
                return false;
            }
            $visited[] = $cursor;

            $parent = \App\Models\Catalog\Category::query()
                ->where(column: 'id', operator: '=', value: $cursor)
                ->value(column: 'parent_id');

            if ($parent === null) {
                return false;
            }
            $cursor = (int) $parent;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe estar en kebab-case.',
            'slug.unique' => 'Ya existe una categoría con ese slug.',
            'parent_id.exists' => 'La categoría padre indicada no existe.',
        ];
    }
}
