<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreateAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'exists:products,id',
                function ($attribute, $value, $fail) {
                    $exists = \App\Models\Auction::where('product_id', $value)
                        ->whereIn('status', ['pending', 'active'])
                        ->exists();
                    if ($exists) {
                        $fail('Sản phẩm này đã có phiên đấu giá đang diễn ra');
                    }
                }
            ],
            'start_price'      => 'required|numeric|min:0',
            'bid_increment'    => 'required|numeric|min:1',
            'min_participants' => 'required|integer|min:1',
            'max_participants' => 'required|integer|gt:min_participants',
            'started_at'       => 'required|date|after:now',
            'ended_at'         => 'required|date|after:started_at',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required'       => 'Sản phẩm không được để trống',
            'product_id.exists'         => 'Sản phẩm không tồn tại',
            'start_price.required'      => 'Giá bắt đầu không được để trống',
            'start_price.numeric'       => 'Giá bắt đầu phải là số',
            'bid_increment.required'    => 'Bước giá không được để trống',
            'bid_increment.numeric'     => 'Bước giá phải là số',
            'bid_increment.min'         => 'Bước giá tối thiểu là 1',
            'min_participants.required' => 'Số người tối thiểu không được để trống',
            'min_participants.min'      => 'Số người tối thiểu phải lớn hơn 0',
            'max_participants.required' => 'Số người tối đa không được để trống',
            'max_participants.gt'       => 'Số người tối đa phải lớn hơn số người tối thiểu',
            'started_at.required'       => 'Thời gian bắt đầu không được để trống',
            'started_at.after'          => 'Thời gian bắt đầu phải sau thời điểm hiện tại',
            'ended_at.required'         => 'Thời gian kết thúc không được để trống',
            'ended_at.after'            => 'Thời gian kết thúc phải sau thời gian bắt đầu',
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