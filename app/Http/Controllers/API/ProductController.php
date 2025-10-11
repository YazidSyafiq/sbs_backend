<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    // Product
    public function getProduct()
    {
        $products = Product::all();

        return ProductResource::collection($products);
    }
}
