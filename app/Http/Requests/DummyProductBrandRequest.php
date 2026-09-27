<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class DummyProductBrandRequest extends FormRequest
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
                Rule::unique('dummy_product_brands', 'slug')->ignore($this->route('dummy_product_brand')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_file' => [
                'nullable',
                File::image()->types(DummyProductRequest::IMAGE_TYPES)->max(DummyProductRequest::MAX_IMAGE_SIZE_KB),
            ],
            'remove_image' => ['sometimes', 'boolean'],
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
            'slug.unique' => 'Slug sudah dipakai brand lain.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'image_file.image' => 'File harus berupa gambar.',
            'image_file.mimes' => 'Gambar harus berformat JPG, PNG, WEBP, GIF, atau AVIF.',
            'image_file.max' => 'Ukuran gambar maksimal 5 MB.',
        ];
    }
}
