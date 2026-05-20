<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Tên không được để trống',
            'email.required'     => 'Email không được để trống',
            'email.email'        => 'Email không đúng định dạng',
            'email.unique'       => 'Email đã được sử dụng',
            'password.required'  => 'Mật khẩu không được để trống',
            'password.min'       => 'Mật khẩu tối thiểu 8 ký tự',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}