<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|min:2|max:255',
            'avatar'           => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'street'           => 'sometimes|nullable|string|max:255',
            'street_number'    => 'sometimes|nullable|string|max:50',
            'city'             => 'sometimes|nullable|string|max:255',
            'postal_code'      => 'sometimes|nullable|string|max:20',
            'country'          => 'sometimes|nullable|string|max:255',
            'card_holder_name' => 'sometimes|nullable|string|max:255',
            'card_number'      => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[0-9]{16}$/', // Đúng 16 số
            ],
            'card_expiry'      => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^(0[1-9]|1[0-2])\/([0-9]{2})$/', // MM/YY
            ],
            'card_ccv'         => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[0-9]{3,4}$/', // 3 hoặc 4 số
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'                => 'Tên tối thiểu 2 ký tự',
            'name.max'                => 'Tên tối đa 255 ký tự',
            'avatar.image'            => 'File phải là hình ảnh',
            'avatar.mimes'            => 'Hình ảnh phải là jpg, jpeg, png',
            'avatar.max'              => 'Hình ảnh không được vượt quá 2MB',
            'street.max'              => 'Tên đường tối đa 255 ký tự',
            'street_number.max'       => 'Số nhà tối đa 50 ký tự',
            'city.max'                => 'Tên thành phố tối đa 255 ký tự',
            'postal_code.max'         => 'Mã bưu điện tối đa 20 ký tự',
            'country.max'             => 'Tên quốc gia tối đa 255 ký tự',
            'card_holder_name.max'    => 'Tên chủ thẻ tối đa 255 ký tự',
            'card_number.regex'       => 'Số thẻ phải đúng 16 chữ số',
            'card_expiry.regex'       => 'Ngày hết hạn phải đúng định dạng MM/YY',
            'card_ccv.regex'          => 'CCV phải là 3 hoặc 4 chữ số',
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