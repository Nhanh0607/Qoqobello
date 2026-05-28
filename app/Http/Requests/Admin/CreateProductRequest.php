<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'store_price' => 'required|numeric|min:1|max:999999',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'Tiêu đề không được để trống',
            'title.max'            => 'Tiêu đề tối đa 255 ký tự',
            'description.required' => 'Mô tả không được để trống',
            'description.max'      => 'Mô tả tối đa 5000 ký tự',
            'image.image'          => 'File phải là hình ảnh',
            'image.mimes'          => 'Hình ảnh phải là jpg, jpeg, png',
            'image.max'            => 'Hình ảnh không được vượt quá 2MB',
            'store_price.required' => 'Giá không được để trống',
            'store_price.numeric'  => 'Giá phải là số',
            'store_price.min'      => 'Giá tối thiểu là 1',
            'store_price.max'      => 'Giá tối đa là 999,999',
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