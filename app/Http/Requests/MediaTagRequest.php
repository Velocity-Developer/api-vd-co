<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaTagRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'slug' => [
                'required',
                'string',
                'max:191',
                'alpha_dash:ascii',
                Rule::unique('media_tags', 'slug')->ignore($this->route('media_tag')),
            ],
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
            'name.max' => 'Nama tag maksimal 50 karakter.',
            'slug.required' => 'Slug wajib diisi.',
            'slug.unique' => 'Slug sudah dipakai tag lain.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
        ];
    }
}
