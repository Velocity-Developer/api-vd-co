<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondsWithJsonValidationErrors;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreMediaRequest extends FormRequest
{
    use RespondsWithJsonValidationErrors;

    public const MAX_FILES = 10;

    public const MAX_FILE_SIZE_KB = 20 * 1024;

    /**
     * File extensions that may be uploaded to the media library.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
        'mp4', 'webm', 'mov',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip',
    ];

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
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => [
                'required',
                File::types(self::ALLOWED_EXTENSIONS)->max(self::MAX_FILE_SIZE_KB),
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
            'files.required' => 'Pilih minimal satu file.',
            'files.max' => 'Maksimal '.self::MAX_FILES.' file sekali upload.',
            'files.*.mimes' => 'Tipe file tidak diizinkan.',
            'files.*.max' => 'Ukuran file maksimal '.(self::MAX_FILE_SIZE_KB / 1024).' MB.',
            'files.*.uploaded' => 'File gagal diterima server, kemungkinan terlalu besar.',
        ];
    }
}
