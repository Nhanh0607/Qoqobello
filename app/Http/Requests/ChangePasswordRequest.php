<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string|min:8',
            'new_password'     => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'different:current_password',
                'regex:/^\S*$/', // Không được chứa khoảng trắng
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Mật khẩu hiện tại không được để trống',
            'current_password.min'      => 'Mật khẩu hiện tại tối thiểu 8 ký tự',
            'new_password.required'     => 'Mật khẩu mới không được để trống',
            'new_password.min'          => 'Mật khẩu mới tối thiểu 8 ký tự',
            'new_password.max'          => 'Mật khẩu mới tối đa 255 ký tự',
            'new_password.confirmed'    => 'Xác nhận mật khẩu không khớp',
            'new_password.different'    => 'Mật khẩu mới không được giống mật khẩu cũ',
            'new_password.regex'        => 'Mật khẩu không được chứa khoảng trắng',
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