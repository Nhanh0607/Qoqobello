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
            'description' => 'required|string',
            'image'       => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'store_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'Tiêu đề không được để trống',
            'description.required' => 'Mô tả không được để trống',
            'image.required'       => 'Hình ảnh không được để trống',
            'image.image'          => 'File phải là hình ảnh',
            'image.mimes'          => 'Hình ảnh phải là jpg, jpeg, png',
            'image.max'            => 'Hình ảnh không được vượt quá 2MB',
            'store_price.required' => 'Giá không được để trống',
            'store_price.numeric'  => 'Giá phải là số',
            'store_price.min'      => 'Giá phải lớn hơn 0',
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