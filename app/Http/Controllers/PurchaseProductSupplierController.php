<?php

namespace App\Http\Controllers;

use App\Models\PurchaseProductSupplier;
use Illuminate\Http\Request;

class PurchaseProductSupplierController extends Controller
{
    private function getCompanyInfo()
    {
        return [
            'name' => env('COMPANY_NAME', 'Your Company Name'),
            'address' => env('COMPANY_ADDRESS', 'Jl. Example Street No. 123'),
            'city' => env('COMPANY_CITY', 'Jakarta, Indonesia'),
            'phone' => env('COMPANY_PHONE', '+62 21 1234 5678'),
            'email' => env('COMPANY_EMAIL', 'info@yourcompany.com'),
            'website' => env('COMPANY_WEBSITE', 'www.yourcompany.com'),
        ];
    }

    public function faktur(PurchaseProductSupplier $purchaseProduct)
    {
        $purchaseProduct->load([]);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-product-supplier.faktur', compact('purchaseProduct', 'companyInfo'));
    }
}
