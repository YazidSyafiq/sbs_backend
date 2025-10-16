<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Http\Resources\SupplierResource;

class SupplierController extends Controller
{
    // Supplier
    public function getSupplier()
    {
        $supplier = Supplier::all();

        return SupplierResource::collection($supplier);
    }
}
