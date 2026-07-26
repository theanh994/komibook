<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'vendor';
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
            'author' => ['required', 'string', 'max:255'],
            'translator' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'series_id' => ['nullable', 'integer', 'exists:series,id'],
            'series_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'dimensions' => ['nullable', 'string', 'max:50'],
            'cover_format' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:50'],
            'target_age' => ['nullable', 'string', 'max:50'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'release_date' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'type' => ['required', Rule::in(['physical', 'ebook'])],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'ebook_file' => ['nullable', 'file', 'mimes:pdf,epub', 'max:51200', 'required_if:type,ebook'],
        ];
    }

    /**
     * Custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'title' => 'Tên sách',
            'author' => 'Tác giả',
            'category_id' => 'Danh mục',
            'price' => 'Giá',
            'sale_price' => 'Giá khuyến mãi',
            'stock' => 'Tồn kho',
            'type' => 'Loại sách',
            'cover_image' => 'Ảnh bìa',
            'ebook_file' => 'File E-book',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'sale_price.lt' => 'Giá khuyến mãi phải nhỏ hơn giá gốc.',
            'ebook_file.required_if' => 'Vui lòng tải lên file E-book khi loại sách là E-book.',
        ];
    }
}
