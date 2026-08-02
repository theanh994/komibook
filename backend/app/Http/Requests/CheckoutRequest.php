<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Được bảo vệ bởi auth:sanctum
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', 'exists:books,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['nullable', 'string', 'in:COD,VNPAY,DEMO_WALLET,cod,online,vnpay,demo_wallet'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
            'ebook_terms_accepted' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bookIds = collect($this->input('items', []))
                ->pluck('book_id')
                ->filter()
                ->unique()
                ->values();

            if ($bookIds->isEmpty()) {
                return;
            }

            $hasPhysicalBooks = Book::withoutGlobalScopes()
                ->whereIn('id', $bookIds)
                ->where('type', '!=', 'ebook')
                ->exists();

            if (! $hasPhysicalBooks) {
                return;
            }

            if (blank($this->input('shipping_address'))) {
                $validator->errors()->add('shipping_address', 'Địa chỉ nhận hàng là bắt buộc khi đơn có sách vật lý.');
            }

            if (blank($this->input('phone'))) {
                $validator->errors()->add('phone', 'Số điện thoại là bắt buộc khi đơn có sách vật lý.');
            }
        });
    }
}
