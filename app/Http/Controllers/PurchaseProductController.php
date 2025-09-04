<?php

namespace App\Http\Controllers;

use App\Models\PurchaseProduct;
use Illuminate\Http\Request;

class PurchaseProductController extends Controller
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

    public function invoice(PurchaseProduct $purchaseProduct)
    {
        $purchaseProduct->load(['user.branch', 'items.product']);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-product.invoice', compact('purchaseProduct', 'companyInfo'));
    }

    public function faktur(PurchaseProduct $purchaseProduct)
    {
        $purchaseProduct->load(['user.branch', 'items.product']);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-product.faktur', compact('purchaseProduct', 'companyInfo'));
    }
}
