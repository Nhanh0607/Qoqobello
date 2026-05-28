<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email:rfc,dns|exists:users,email|max:255',
        ];
    }

    public function messages(): array
    {
            return [
            'email.required' => 'Email không được để trống',
            'email.email'    => 'Email không đúng định dạng',
            'email.dns'      => 'Email không tồn tại',
            'email.exists'   => 'Email không tồn tại trong hệ thống',
            'email.max'      => 'Email tối đa 255 ký tự',
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