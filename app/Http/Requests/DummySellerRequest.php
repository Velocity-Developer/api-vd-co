<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class DummySellerRequest extends FormRequest
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
                Rule::unique('dummy_sellers', 'slug')->ignore($this->route('dummy_seller')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'rating' => ['required', 'numeric', 'min:0', 'max:5', 'decimal:0,2'],
            'is_verified' => ['required', 'boolean'],
            'image_file' => [
                'nullable',
                File::image()->types(DummyProductRequest::IMAGE_TYPES)->max(DummyProductRequest::MAX_IMAGE_SIZE_KB),
            ],
            'remove_image' => ['sometimes', 'boolean'],
            'banner_file' => [
                'nullable',
                File::image()->types(DummyProductRequest::IMAGE_TYPES)->max(DummyProductRequest::MAX_IMAGE_SIZE_KB),
            ],
            'remove_banner' => ['sometimes', 'boolean'],
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
            'slug.unique' => 'Slug sudah dipakai seller lain.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'email.email' => 'Format email tidak valid.',
            'phone.regex' => 'Nomor telepon hanya boleh angka, spasi, +, -, dan tanda kurung.',
            'rating.max' => 'Rating maksimal 5.',
            'image_file.image' => 'File harus berupa gambar.',
            'image_file.mimes' => 'Gambar harus berformat JPG, PNG, WEBP, GIF, atau AVIF.',
            'image_file.max' => 'Ukuran gambar maksimal 5 MB.',
            'banner_file.image' => 'Banner harus berupa gambar.',
            'banner_file.mimes' => 'Banner harus berformat JPG, PNG, WEBP, GIF, atau AVIF.',
            'banner_file.max' => 'Ukuran banner maksimal 5 MB.',
        ];
    }
}
