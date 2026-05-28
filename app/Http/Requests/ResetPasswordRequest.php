<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'    => 'required|string',
            'email'    => 'required|email|exists:users,email|max:255',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'regex:/^\S*$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'    => 'Token không được để trống',
            'token.string'      => 'Token không hợp lệ',
            'email.required'    => 'Email không được để trống',
            'email.email'       => 'Email không đúng định dạng',
            'email.exists'      => 'Email không tồn tại trong hệ thống',
            'email.max'         => 'Email tối đa 255 ký tự',
            'password.required' => 'Mật khẩu không được để trống',
            'password.min'      => 'Mật khẩu tối thiểu 8 ký tự',
            'password.max'      => 'Mật khẩu tối đa 255 ký tự',
            'password.confirmed'=> 'Xác nhận mật khẩu không khớp',
            'password.regex'    => 'Mật khẩu không được chứa khoảng trắng',
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