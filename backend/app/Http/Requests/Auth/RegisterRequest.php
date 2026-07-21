<?php

namespace App\Http\Requests\Auth;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required_without:phone', 'nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'phone'        => ['required_without:email', 'nullable', 'string', 'max:20', 'unique:users,phone'],
            'gender'       => ['nullable', 'string', 'in:male,female,other'],
            'birthday'     => ['nullable', 'date'],
            'desired_role' => ['nullable', 'string', 'in:customer,author,vendor'],
            'google_id'    => ['nullable', 'string'],
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
            'name.required'             => 'Vui lòng nhập họ và tên.',
            'email.required_without'    => 'Vui lòng nhập email hoặc số điện thoại.',
            'email.email'               => 'Địa chỉ email không hợp lệ.',
            'email.unique'              => 'Email này đã được sử dụng.',
            'password.required'         => 'Vui lòng nhập mật khẩu.',
            'password.min'              => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.confirmed'        => 'Xác nhận mật khẩu không khớp.',
            'phone.required_without'    => 'Vui lòng nhập số điện thoại hoặc email.',
            'phone.unique'              => 'Số điện thoại này đã được sử dụng.',
        ];
    }
}
