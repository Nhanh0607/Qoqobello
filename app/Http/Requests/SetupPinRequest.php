<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class SetupPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin'         => 'required|string|digits:4',
            'device_name' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required'         => 'PIN không được để trống',
            'pin.digits'           => 'PIN phải đúng 4 chữ số',
            'device_name.required' => 'Tên thiết bị không được để trống',
            'device_name.max'      => 'Tên thiết bị tối đa 255 ký tự',
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