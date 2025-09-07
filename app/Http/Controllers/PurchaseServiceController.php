<?php

namespace App\Http\Controllers;

use App\Models\ServicePurchase;
use Illuminate\Http\Request;

class PurchaseServiceController extends Controller
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

    public function invoice(ServicePurchase $purchaseService)
    {
        $purchaseService->load(['user.branch', 'items.service']);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-service.invoice', compact('purchaseService', 'companyInfo'));
    }

    public function faktur(ServicePurchase $purchaseService)
    {
        $purchaseService->load(['user.branch', 'items.service']);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-service.faktur', compact('purchaseService', 'companyInfo'));
    }
}
