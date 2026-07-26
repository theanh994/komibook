<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
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
            'phone' => ['nullable', 'string', 'regex:/^(0[3|5|7|8|9])+([0-9]{8})$/', 'max:20'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'favorite_category_ids' => ['nullable', 'array'],
            'favorite_category_ids.*' => ['integer', 'exists:categories,id'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'name.max' => 'Họ và tên không được vượt quá 255 ký tự.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'birthday.date' => 'Ngày sinh không đúng định dạng.',
            'birthday.before_or_equal' => 'Ngày sinh không được vượt quá ngày hiện tại.',
            'address.max' => 'Địa chỉ không được vượt quá 1000 ký tự.',
        ];
    }
}
