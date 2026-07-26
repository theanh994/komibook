<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'email' => ['required_without_all:phone,challenge_token', 'nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required_without_all:email,challenge_token', 'nullable', 'string', 'max:20', 'unique:users,phone'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'birthday' => ['nullable', 'date'],
            'desired_role' => ['nullable', 'string', 'in:customer,author,vendor'],
            'challenge_token' => ['nullable', 'string', 'uuid'],
            'email_verification_token' => ['nullable', 'string', 'uuid'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'email.required_without' => 'Vui lòng nhập email hoặc số điện thoại.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'phone.required_without' => 'Vui lòng nhập số điện thoại hoặc email.',
            'phone.unique' => 'Số điện thoại này đã được sử dụng.',
        ];
    }
}
