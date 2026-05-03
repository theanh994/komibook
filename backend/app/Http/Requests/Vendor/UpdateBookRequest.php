<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'author'      => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isbn'        => ['nullable', 'string', 'max:20'],
            'price'       => ['sometimes', 'required', 'integer', 'min:0'],
            'sale_price'  => ['nullable', 'integer', 'min:0'],
            'stock'       => ['sometimes', 'required', 'integer', 'min:0'],
            'type'        => ['sometimes', 'required', Rule::in(['physical', 'ebook'])],
            'status'      => ['nullable', Rule::in(['draft', 'published'])],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'ebook_file'  => ['nullable', 'file', 'mimes:pdf,epub', 'max:51200'],
        ];
    }

    /**
     * Custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'title'       => 'Tên sách',
            'author'      => 'Tác giả',
            'category_id' => 'Danh mục',
            'price'       => 'Giá',
            'sale_price'  => 'Giá khuyến mãi',
            'stock'       => 'Tồn kho',
            'type'        => 'Loại sách',
            'cover_image' => 'Ảnh bìa',
            'ebook_file'  => 'File E-book',
        ];
    }
}
