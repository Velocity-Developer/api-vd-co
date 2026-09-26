<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use App\Models\MediaCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MediaCategoryRequest extends FormRequest
{
    use RespondsWithJsonValidationErrors;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:191',
                'alpha_dash:ascii',
                Rule::unique('media_categories', 'slug')->ignore($this->route('media_category')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('media_categories', 'id')],
        ];
    }

    /**
     * Prevent a category from becoming its own ancestor.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('media_category');
                $parentId = $this->integer('parent_id') ?: null;

                if (! $category instanceof MediaCategory || $parentId === null) {
                    return;
                }

                if (in_array($parentId, [$category->id, ...$category->descendantIds()], true)) {
                    $validator->errors()->add('parent_id', 'Induk tidak boleh kategori ini sendiri atau turunannya.');
                }
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai kategori lain.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'parent_id.exists' => 'Kategori induk tidak ditemukan.',
        ];
    }
}
