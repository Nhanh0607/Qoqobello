<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class BuyCoinsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_eur' => 'required|numeric|min:1|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount_eur.required' => 'Số tiền không được để trống',
            'amount_eur.numeric'  => 'Số tiền phải là số',
            'amount_eur.min'      => 'Số tiền tối thiểu là 1€',
            'amount_eur.max'      => 'Số tiền tối đa là 1000€',
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