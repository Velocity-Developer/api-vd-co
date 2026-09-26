<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMediaRequest extends FormRequest
{
    use RespondsWithJsonValidationErrors;

    public const MAX_TAGS = 30;

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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_TAGS],
            'tags.*' => ['required', 'string', 'max:50'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('media_categories', 'id')],
        ];
    }

    /**
     * Require at least one editable field in the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['title', 'caption', 'tags', 'category_ids'])) {
                    $validator->errors()->add('media', 'Tidak ada data yang diubah.');
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
            'title.max' => 'Judul maksimal 255 karakter.',
            'caption.max' => 'Caption maksimal 1000 karakter.',
            'tags.max' => 'Maksimal '.self::MAX_TAGS.' tag.',
            'tags.*.max' => 'Setiap tag maksimal 50 karakter.',
            'category_ids.*.exists' => 'Kategori media tidak ditemukan.',
        ];
    }
}
