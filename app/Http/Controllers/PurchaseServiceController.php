<?php

namespace App\Http\Controllers;

use App\Models\ServicePurchase;
use App\Models\Service;
use App\Models\Technician;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

    /**
     * Detect if request is from mobile/Flutter app
     */
    private function isMobileAccess(Request $request)
    {
        // Method 1: Check for query parameter
        if ($request->has('mobile') || $request->has('flutter')) {
            return true;
        }

        return false;
    }

    public function invoice(Request $request, ServicePurchase $purchaseService)
    {
        $purchaseService->load(['user.branch', 'items.service']);
        $companyInfo = $this->getCompanyInfo();
        $isFromMobile = $this->isMobileAccess($request);

        return view('purchase-service.invoice', compact('purchaseService', 'companyInfo', 'isFromMobile'));
    }

    public function faktur(ServicePurchase $purchaseService)
    {
        $purchaseService->load(['user.branch', 'items.service']);
        $companyInfo = $this->getCompanyInfo();
        $isFromMobile = $this->isMobileAccess($request);

        return view('purchase-service.faktur', compact('purchaseService', 'companyInfo', 'isFromMobile'));
    }

    public function report(Request $request)
    {
        // Get filter parameters
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->startOfMonth();
        $untilDate = $request->until_date ? Carbon::parse($request->until_date) : Carbon::now();
        $technicianId = $request->technician_id;
        $serviceId = $request->service_id;
        $typePo = $request->type_po;
        $statusPaid = $request->status_paid;
        $status = $request->status;

        // Build query for ServicePurchases
        $query = ServicePurchase::with(['user.branch', 'items.service', 'items.technician'])
            ->whereBetween('order_date', [$fromDate, $untilDate]);

        // Apply PO level filters
        if ($typePo) {
            $query->where('type_po', $typePo);
        }

        if ($statusPaid) {
            $query->where('status_paid', $statusPaid);
        }

        if ($status) {
            $query->where('status', $status);
        }

        // For item-level filters, we need to check if PO has relevant items
        if ($technicianId || $serviceId) {
            $query->whereHas('items', function ($q) use ($technicianId, $serviceId) {
                if ($technicianId) {
                    $q->where('technician_id', $technicianId);
                }
                if ($serviceId) {
                    $q->where('service_id', $serviceId);
                }
            });
        }

        $servicePurchases = $query->orderBy('order_date', 'desc')->get();

        // Process each PO and filter items
        $reportData = [];
        $totalRevenue = 0;
        $totalCost = 0;
        $totalOrders = 0;
        $totalItems = 0;

        foreach ($servicePurchases as $purchase) {
            // Filter items based on the item-level filters
            $filteredItems = $purchase->items->filter(function ($item) use ($serviceId, $technicianId) {
                $matchService = !$serviceId || $item->service_id == $serviceId;
                $matchTechnician = !$technicianId || $item->technician_id == $technicianId;
                return $matchService && $matchTechnician;
            });

            // Only include PO if it has matching items
            if ($filteredItems->count() > 0) {
                // Convert to array untuk memastikan indexing benar dan reload relationships
                $filteredItemsArray = [];
                foreach ($filteredItems->values() as $item) {
                    // Reload relationships to ensure they're accessible
                    $item->load(['service', 'technician']);
                    $filteredItemsArray[] = $item;
                }

                $reportData[] = [
                    'purchase' => $purchase,
                    'filtered_items' => $filteredItemsArray,
                    'items_count' => count($filteredItemsArray)
                ];

                // Calculate totals
                foreach ($filteredItems as $item) {
                    $totalRevenue += $item->selling_price;
                    $totalCost += $item->cost_price ?? 0;
                    $totalItems++;
                }
                $totalOrders++;
            }
        }

        $totalProfit = $totalRevenue - $totalCost;

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-service.report', compact(
            'reportData',
            'fromDate',
            'untilDate',
            'totalRevenue',
            'totalCost',
            'totalProfit',
            'totalOrders',
            'totalItems',
            'filterLabels',
            'companyInfo'
        ));
    }

    private function getFilterLabels(Request $request)
    {
        $labels = [];

        if ($request->technician_id) {
            $technician = Technician::find($request->technician_id);
            $labels['technician'] = $technician ? $technician->name . ' (' . $technician->code . ')' : 'Unknown';
        }

        if ($request->service_id) {
            $service = Service::find($request->service_id);
            $labels['service'] = $service ? $service->name . ' (' . $service->code . ')' : 'Unknown';
        }

        if ($request->type_po) {
            $labels['type_po'] = ucfirst($request->type_po) . ' Purchase';
        }

        if ($request->status_paid) {
            $labels['status_paid'] = ucfirst($request->status_paid);
        }

        if ($request->status) {
            $labels['status'] = $request->status;
        }

        return $labels;
    }
}
