<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class DummyProductRequest extends FormRequest
{
    use RespondsWithJsonValidationErrors;

    public const MAX_IMAGE_SIZE_KB = 5 * 1024;

    /**
     * Image types accepted for product pictures (no SVG, it can carry scripts).
     *
     * @var list<string>
     */
    public const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'price_discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'lte:price'],
            'rating' => ['required', 'numeric', 'min:0', 'max:5', 'decimal:0,2'],
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('dummy_products', 'sku')->ignore($this->route('dummy_product')),
            ],
            'image_file' => ['nullable', File::image()->types(self::IMAGE_TYPES)->max(self::MAX_IMAGE_SIZE_KB)],
            'remove_image' => ['sometimes', 'boolean'],
            'dummy_product_brand_id' => ['nullable', 'integer', Rule::exists('dummy_product_brands', 'id')],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('dummy_product_categories', 'id')],
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
            'title.required' => 'Judul wajib diisi.',
            'price.required' => 'Harga wajib diisi.',
            'price_discount.lte' => 'Harga diskon tidak boleh lebih besar dari harga.',
            'rating.max' => 'Rating maksimal 5.',
            'stock.required' => 'Stok wajib diisi.',
            'sku.required' => 'SKU wajib diisi.',
            'sku.unique' => 'SKU sudah dipakai produk lain.',
            'dummy_product_brand_id.exists' => 'Brand tidak ditemukan.',
            'category_ids.*.exists' => 'Kategori tidak ditemukan.',
            'image_file.image' => 'File harus berupa gambar.',
            'image_file.mimes' => 'Gambar harus berformat JPG, PNG, WEBP, GIF, atau AVIF.',
            'image_file.max' => 'Ukuran gambar maksimal 5 MB.',
        ];
    }
}
