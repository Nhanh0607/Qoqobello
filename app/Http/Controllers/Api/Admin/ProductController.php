<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with('creator')->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'title'       => $request->title,
            'description' => $request->description,
            'image'       => $imagePath,
            'store_price' => $request->store_price,
            'created_by'  => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thêm sản phẩm thành công',
            'data'    => $product,
        ], 201);
    }
}