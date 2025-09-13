<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProductController extends Controller
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

    public function report(Request $request)
    {
        // Get filter parameters dan konversi ke numeric jika diperlukan
        $categoryId = $request->category_id;
        $stockStatus = $request->stock_status;
        $minStock = $request->min_stock ? (int) $request->min_stock : null;
        $maxStock = $request->max_stock ? (int) $request->max_stock : null;
        $minPrice = $request->min_price ? (float) $request->min_price : null;
        $maxPrice = $request->max_price ? (float) $request->max_price : null;
        $sortBy = $request->sort_by ?? 'batch_number';
        $expiryFilter = $request->expiry_filter;

        // Build query for Products with batches
        $query = Product::with(['category', 'productBatches.purchaseProductSupplier.supplier']);

        // Apply product-level filters
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }

        $products = $query->get();

        // Process each product and filter based on product-level stock status
        $reportData = [];
        $totalProducts = 0;
        $totalBatches = 0;
        $totalStockUnits = 0;

        // Batch status breakdown
        $batchStatusBreakdown = [
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'expiring_soon' => 0,
            'expired' => 0,
            'no_expiry' => 0,
        ];

        foreach ($products as $product) {
            // Calculate total product stock
            $totalProductStock = $product->total_stock; // Menggunakan accessor dari model Product

            // Apply product-level stock status filter
            if ($stockStatus) {
                $productStockStatus = $this->getProductStockStatus($totalProductStock);
                if ($stockStatus !== $productStockStatus) {
                    continue; // Skip product jika tidak sesuai filter
                }
            }

            // Apply product-level stock quantity filters
            if ($minStock !== null && $totalProductStock < $minStock) {
                continue;
            }

            if ($maxStock !== null && $totalProductStock > $maxStock) {
                continue;
            }

            // Get all batches for this product
            $batches = $product->productBatches;

            // Filter batches hanya berdasarkan expiry filter (stock filter sudah di product level)
            $filteredBatches = $batches->filter(function ($batch) use ($expiryFilter) {
                // Expiry filters (menggunakan accessor expiry_status dari model)
                if ($expiryFilter) {
                    $expiryStatus = $batch->expiry_status; // Menggunakan accessor
                    switch ($expiryFilter) {
                        case 'expiring_soon':
                            if ($expiryStatus !== 'Expiring Soon') return false;
                            break;
                        case 'expired':
                            if ($expiryStatus !== 'Expired') return false;
                            break;
                        case 'fresh':
                            if ($expiryStatus !== 'Fresh') return false;
                            break;
                        case 'no_expiry':
                            if ($expiryStatus !== 'No Expiry Date') return false;
                            break;
                    }
                }

                return true;
            });

            // Only include product if it has batches (after expiry filtering)
            if ($filteredBatches->count() > 0) {
                // Convert to array untuk memastikan indexing benar
                $filteredBatchesArray = [];
                foreach ($filteredBatches->values() as $batch) {
                    $filteredBatchesArray[] = $batch;
                }

                // Sort batches
                usort($filteredBatchesArray, function ($a, $b) use ($sortBy) {
                    switch ($sortBy) {
                        case 'entry_date':
                            return $b->entry_date <=> $a->entry_date; // Newest first
                        case 'expiry_date':
                            if (!$a->expiry_date && !$b->expiry_date) return 0;
                            if (!$a->expiry_date) return 1;
                            if (!$b->expiry_date) return -1;
                            return $a->expiry_date <=> $b->expiry_date;
                        case 'stock':
                            return $b->quantity <=> $a->quantity; // Highest stock first
                        case 'cost_price':
                            return $b->cost_price <=> $a->cost_price; // Highest cost first
                        default: // batch_number
                            return $a->batch_number <=> $b->batch_number;
                    }
                });

                $reportData[] = [
                    'product' => $product,
                    'total_product_stock' => $totalProductStock,
                    'product_stock_status' => $this->getProductStockStatus($totalProductStock),
                    'filtered_batches' => $filteredBatchesArray,
                    'batches_count' => count($filteredBatchesArray)
                ];

                // Calculate totals
                $totalProducts++;
                $totalBatches += count($filteredBatchesArray);
                $totalStockUnits += $totalProductStock; // Gunakan total product stock

                foreach ($filteredBatchesArray as $batch) {
                    // Update batch status breakdown (untuk batch-level statistics)
                    $batchStockStatus = $batch->stock_status;
                    $expiryStatus = $batch->expiry_status;

                    // Batch stock status (untuk statistik)
                    if ($batchStockStatus === 'Out of Stock') {
                        $batchStatusBreakdown['out_of_stock']++;
                    } elseif ($batchStockStatus === 'Low Stock') {
                        $batchStatusBreakdown['low_stock']++;
                    } else {
                        $batchStatusBreakdown['in_stock']++;
                    }

                    // Expiry status
                    if ($expiryStatus === 'Expired') {
                        $batchStatusBreakdown['expired']++;
                    } elseif ($expiryStatus === 'Expiring Soon') {
                        $batchStatusBreakdown['expiring_soon']++;
                    } elseif ($expiryStatus === 'No Expiry Date') {
                        $batchStatusBreakdown['no_expiry']++;
                    }
                }
            }
        }

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('product.report', compact(
            'reportData',
            'totalProducts',
            'totalBatches',
            'totalStockUnits',
            'batchStatusBreakdown',
            'filterLabels',
            'companyInfo'
        ));
    }

    /**
     * Get product stock status based on total stock
     */
    private function getProductStockStatus($totalStock)
    {
        if ($totalStock <= 0) {
            return 'out_of_stock';
        } elseif ($totalStock <= 10) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    private function getFilterLabels(Request $request)
    {
        $labels = [];

        if ($request->category_id) {
            $category = ProductCategory::find($request->category_id);
            $labels['category'] = $category ? $category->name : 'Unknown';
        }

        if ($request->stock_status) {
            $statusLabels = [
                'in_stock' => 'Product In Stock',
                'low_stock' => 'Product Low Stock',
                'out_of_stock' => 'Product Out of Stock',
            ];
            $labels['stock_status'] = $statusLabels[$request->stock_status] ?? ucfirst($request->stock_status);
        }

        if ($request->expiry_filter) {
            $expiryLabels = [
                'expiring_soon' => 'Expiring Soon',
                'expired' => 'Expired',
                'fresh' => 'Fresh',
                'no_expiry' => 'No Expiry Date',
            ];
            $labels['expiry'] = $expiryLabels[$request->expiry_filter] ?? ucfirst($request->expiry_filter);
        }

        if ($request->min_stock) {
            $labels['min_stock'] = 'Min Product Stock: ' . number_format($request->min_stock);
        }

        if ($request->max_stock) {
            $labels['max_stock'] = 'Max Product Stock: ' . number_format($request->max_stock);
        }

        if ($request->min_price) {
            $labels['min_price'] = 'Min Price: Rp ' . number_format($request->min_price);
        }

        if ($request->max_price) {
            $labels['max_price'] = 'Max Price: Rp ' . number_format($request->max_price);
        }

        if ($request->sort_by && $request->sort_by != 'batch_number') {
            $sortLabels = [
                'entry_date' => 'Sorted by Entry Date',
                'expiry_date' => 'Sorted by Expiry Date',
                'stock' => 'Sorted by Batch Stock',
                'cost_price' => 'Sorted by Cost Price',
            ];
            $labels['sort'] = $sortLabels[$request->sort_by] ?? 'Sorted by ' . ucfirst($request->sort_by);
        }

        return $labels;
    }
}
