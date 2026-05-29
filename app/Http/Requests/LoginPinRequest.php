<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class LoginPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin'       => 'required|string|digits:4',
            'device_id' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required'       => 'PIN không được để trống',
            'pin.digits'         => 'PIN phải đúng 4 chữ số',
            'device_id.required' => 'Device ID không được để trống',
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