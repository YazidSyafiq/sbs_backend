<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductAnalyticReport extends Model
{
    // Gunakan table yang sama dengan Product
    protected $table = 'products';

    // Cast untuk data types
    protected $casts = [
        'entry_date' => 'date',
        'expiry_date' => 'date',
        'price' => 'decimal:2',
        'supplier_price' => 'decimal:2',
    ];

    /**
     * Relasi ke Category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Relasi ke Code Template
     */
    public function codeTemplate(): BelongsTo
    {
        return $this->belongsTo(Code::class, 'code_id');
    }

    /**
     * Get stock status attribute
     */
    public function getStatusAttribute(): string
    {
        $displayStock = max(0, $this->stock);

        if ($displayStock <= 0) {
            return 'Out of Stock';
        } elseif ($displayStock < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

    /**
     * Get expiry status attribute
     */
    public function getExpiryStatusAttribute(): string
    {
        if (!$this->expiry_date) {
            return 'No Expiry Date';
        }

        $daysUntilExpiry = Carbon::now()->diffInDays($this->expiry_date, false);

        if ($daysUntilExpiry < 0) {
            return 'Expired';
        } elseif ($daysUntilExpiry <= 30) {
            return 'Expiring Soon';
        } else {
            return 'Fresh';
        }
    }

    public function getOnRequestAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Requested
        $totalRequested = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Requested']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalRequested > 0 ? $totalRequested : 0;
    }

    public function getOnProcessingAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Processing
        $totalProcessing = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Processing']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalProcessing > 0 ? $totalProcessing : 0;
    }

    public function getOnShippedAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Shipped
        $totalShipped = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Shipped']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalShipped > 0 ? $totalShipped : 0;
    }

    public function getOnReceivedAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Received
        $totalReceived = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Received']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalReceived > 0 ? $totalReceived : 0;
    }

    public function getOnDoneAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Done
        $totalDone = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Done']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalDone > 0 ? $totalDone : 0;
    }

    public function getNeedPurchaseAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Requested atau Processing
        $totalRequested = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Requested', 'Processing']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        $deficit = $totalRequested - $this->attributes['stock']; // Gunakan raw stock value

        return $deficit > 0 ? $deficit : 0;
    }

    // Accessor untuk Purchase Status
    public function getPurchaseStatusAttribute(): string
    {
        if ($this->need_purchase > 0) {
            return 'Need Purchase';
        }
        return 'Stock Available';
    }

    /**
     * Helper method to apply filters to any query
     */
    public static function applyFiltersToQuery($query, $filters = [])
    {
        // Category filter
        if (!empty($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        // Stock status filter
        if (!empty($filters['stock_status'])) {
            $query->where(function($q) use ($filters) {
                foreach ($filters['stock_status'] as $status) {
                    if ($status === 'out_of_stock') {
                        $q->orWhere('stock', '<=', 0);
                    } elseif ($status === 'low_stock') {
                        $q->orWhere(function($subQ) {
                            $subQ->where('stock', '>', 0)->where('stock', '<', 10);
                        });
                    } elseif ($status === 'in_stock') {
                        $q->orWhere('stock', '>=', 10);
                    }
                }
            });
        }

        // Expiry status filter
        if (!empty($filters['expiry_status'])) {
            $now = Carbon::now();
            $query->where(function($q) use ($filters, $now) {
                foreach ($filters['expiry_status'] as $status) {
                    if ($status === 'expired') {
                        $q->orWhere(function($subQ) use ($now) {
                            $subQ->where('expiry_date', '<', $now)->whereNotNull('expiry_date');
                        });
                    } elseif ($status === 'expiring_soon') {
                        $q->orWhere(function($subQ) use ($now) {
                            $subQ->whereBetween('expiry_date', [$now, $now->copy()->addDays(30)]);
                        });
                    } elseif ($status === 'fresh') {
                        $q->orWhere(function($subQ) use ($now) {
                            $subQ->where('expiry_date', '>', $now->copy()->addDays(30));
                        });
                    } elseif ($status === 'no_expiry') {
                        $q->orWhereNull('expiry_date');
                    }
                }
            });
        }

        // Date filters
        if (!empty($filters['entry_date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['entry_date_from']);
        }

        if (!empty($filters['entry_date_until'])) {
            $query->whereDate('entry_date', '<=', $filters['entry_date_until']);
        }

        // Price range filters
        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        // Need purchase filter
        if (!empty($filters['need_purchase_only'])) {
            $query->whereRaw('(
                SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                AND pp.status IN ("Requested", "Processing")
            ) > stock');
        }

        return $query;
    }

    /**
     * Get filtered overview statistics
     */
    public static function getFilteredOverviewStats($filters = [])
    {
        $query = static::query();
        $query = static::applyFiltersToQuery($query, $filters);

        $allProducts = $query->get();

        $totalProducts = $allProducts->count();
        $outOfStock = $allProducts->where('stock', '<=', 0)->count();
        $lowStock = $allProducts->where('stock', '>', 0)->where('stock', '<', 10)->count();
        $inStock = $allProducts->where('stock', '>=', 10)->count();

        $now = Carbon::now();
        $expired = $allProducts->filter(function($product) use ($now) {
            return $product->expiry_date && $product->expiry_date->lt($now);
        })->count();

        $expiringSoon = $allProducts->filter(function($product) use ($now) {
            return $product->expiry_date &&
                   $product->expiry_date->between($now, $now->copy()->addDays(30));
        })->count();

        $totalStockValue = $allProducts->sum(function($product) {
            return $product->stock * $product->price;
        });

        $totalStockUnits = $allProducts->sum('stock');

        // Calculate products needing purchase
        $productsNeedingPurchase = static::getProductsNeedingPurchaseCount($filters);

        return (object)[
            'total_products' => $totalProducts,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'in_stock' => $inStock,
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'total_stock_value' => $totalStockValue,
            'total_stock_units' => $totalStockUnits,
            'products_needing_purchase' => $productsNeedingPurchase,
        ];
    }

    /**
     * Get count of products needing purchase
     */
    public static function getProductsNeedingPurchaseCount($filters = [])
    {
        $query = static::query()
            ->selectRaw('
                products.*,
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                 FROM purchase_product_items ppi
                 JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                 WHERE ppi.product_id = products.id
                 AND pp.status IN ("Requested", "Processing")
                ) as pending_purchase
            ');

        $query = static::applyFiltersToQuery($query, $filters);

        return $query->havingRaw('pending_purchase > stock OR stock <= 10')->count();
    }

    /**
     * Get filtered summary by category
     */
    public static function getFilteredSummaryByCategory($filters = [])
    {
        $query = static::with(['category'])
            ->select('category_id')
            ->selectRaw('COUNT(*) as product_count')
            ->selectRaw('SUM(stock) as total_stock')
            ->selectRaw('SUM(stock * price) as total_value')
            ->selectRaw('AVG(price) as avg_price')
            ->selectRaw('SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ->selectRaw('SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END) as low_stock_count');

        $query = static::applyFiltersToQuery($query, $filters);

        return $query->groupBy('category_id')
                    ->get()
                    ->map(function($item) {
                        $category = ProductCategory::find($item->category_id);

                        return (object)[
                            'category_id' => $item->category_id,
                            'category_name' => $category->name ?? 'Unknown',
                            'product_count' => $item->product_count,
                            'total_stock' => $item->total_stock,
                            'total_value' => $item->total_value,
                            'avg_price' => round($item->avg_price, 2),
                            'out_of_stock_count' => $item->out_of_stock_count,
                            'low_stock_count' => $item->low_stock_count,
                            'stock_health_rate' => $item->product_count > 0 ?
                                round((($item->product_count - $item->out_of_stock_count - $item->low_stock_count) / $item->product_count) * 100, 1) : 0,
                        ];
                    })
                    ->sortByDesc('total_value');
    }

    /**
     * Get specific products requiring attention with detailed information
     */
    public static function getDetailedProductsNeedAttention($filters = [], $limit = 6)
    {
        $now = Carbon::now();

        $query = static::with(['category'])
            ->select('*')
            ->selectRaw('
                CASE
                    WHEN stock <= 0 THEN "out_of_stock"
                    WHEN stock < 10 THEN "low_stock"
                    WHEN expiry_date IS NOT NULL AND expiry_date < ? THEN "expired"
                    WHEN expiry_date IS NOT NULL AND expiry_date <= ? THEN "expiring_soon"
                    ELSE "normal"
                END as attention_type
            ', [$now, $now->copy()->addDays(30)])
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                 FROM purchase_product_items ppi
                 JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                 WHERE ppi.product_id = products.id
                 AND pp.status IN ("Requested", "Processing")
                ) as pending_purchase
            ');

        $query = static::applyFiltersToQuery($query, $filters);

        return $query->havingRaw('attention_type != "normal" OR pending_purchase > stock')
                    ->orderByRaw('
                        CASE attention_type
                            WHEN "expired" THEN 1
                            WHEN "out_of_stock" THEN 2
                            WHEN "expiring_soon" THEN 3
                            WHEN "low_stock" THEN 4
                            ELSE 5
                        END
                    ')
                    ->limit($limit)
                    ->get()
                    ->map(function($product) use ($now) {
                        $product->need_purchase_qty = max(0, $product->pending_purchase - $product->stock);
                        $product->display_name = strlen($product->name) > 25 ?
                                                substr($product->name, 0, 25) . '...' :
                                                $product->name;

                        // Set status text and detail message
                        if ($product->attention_type === 'expired') {
                            $product->status_text = 'EXPIRED';
                            $product->detail_message = $product->expiry_date ? $product->expiry_date->format('d M Y') : 'No date';
                        } elseif ($product->attention_type === 'expiring_soon') {
                            $product->status_text = 'EXPIRING SOON';
                            $product->detail_message = $product->expiry_date ? $product->expiry_date->format('d M Y') : 'No date';
                        } elseif ($product->attention_type === 'out_of_stock') {
                            $product->status_text = 'OUT OF STOCK';
                            $product->detail_message = '0 units available';
                        } elseif ($product->attention_type === 'low_stock') {
                            $product->status_text = 'LOW STOCK';
                            $product->detail_message = $product->stock . ' units remaining';
                        } elseif ($product->need_purchase_qty > 0) {
                            $product->status_text = 'NEED PURCHASE';
                            $product->detail_message = $product->need_purchase_qty . ' units to order';
                        } else {
                            $product->status_text = 'NEEDS ATTENTION';
                            $product->detail_message = 'Review required';
                        }

                        return $product;
                    });
    }

    /**
    * Get product entry trend data from PurchaseProductSupplier (when received or done)
    * Shows all products with 0 values if no stock received
    */
    public static function getProductEntryTrendPerProduct($filters = [], $days = 30, $limit = 10)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays($days);

        // First, get all products that match the filters (not just those with received stock)
        $allProductsQuery = static::query()
            ->select('id', 'name', 'code')
            ->limit($limit);

        $allProductsQuery = static::applyFiltersToQuery($allProductsQuery, $filters);
        $allProducts = $allProductsQuery->get();

        if ($allProducts->isEmpty()) {
            return collect();
        }

        // Then get products that actually have received/done quantities in the period
        $receivedProductsData = DB::table('purchase_product_suppliers as pps')
            ->select('pps.product_id')
            ->selectRaw('SUM(pps.quantity) as total_received')
            ->whereIn('pps.status', ['Received', 'Done'])
            ->whereNotNull('pps.received_date')
            ->whereBetween('pps.received_date', [$startDate, $endDate])
            ->whereIn('pps.product_id', $allProducts->pluck('id'))
            ->groupBy('pps.product_id')
            ->get()
            ->keyBy('product_id');

        $productTrends = [];

        foreach ($allProducts as $product) {
            // Get daily received quantities for this product (both Received and Done status)
            $dailyData = DB::table('purchase_product_suppliers')
                ->selectRaw('DATE(received_date) as date, SUM(quantity) as total_qty')
                ->where('product_id', $product->id)
                ->whereIn('status', ['Received', 'Done'])
                ->whereNotNull('received_date')
                ->whereBetween('received_date', [$startDate, $endDate])
                ->groupByRaw('DATE(received_date)')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // Fill missing dates with zero quantity
            $trendData = [];
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateStr = $date->format('Y-m-d');
                $qty = $dailyData->get($dateStr)?->total_qty ?? 0;

                $trendData[] = (int) $qty; // Ensure it's integer
            }

            $totalReceived = $receivedProductsData->get($product->id)?->total_received ?? 0;

            $productTrends[] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'display_name' => strlen($product->name) > 15 ?
                                substr($product->name, 0, 15) . '...' :
                                $product->name,
                'data' => $trendData,
                'total_received' => (int) $totalReceived,
            ];
        }

        // Sort by total_received descending, but keep all products
        usort($productTrends, function($a, $b) {
            return $b['total_received'] <=> $a['total_received'];
        });

        // Generate date labels
        $dateLabels = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateLabels[] = $date->format('d M');
        }

        return (object)[
            'products' => $productTrends,
            'labels' => $dateLabels,
        ];
    }

    /**
     * Get PO status distribution
     */
    public static function getPOStatusDistribution($filters = [], $limit = 10)
    {
        $query = static::with(['category'])
            ->select('products.*')
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                AND pp.status = "Requested"
                ) as requested_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                AND pp.status = "Processing"
                ) as processing_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                AND pp.status = "Shipped"
                ) as shipped_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                AND pp.status = "Received"
                ) as received_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                AND pp.status = "Done"
                ) as done_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(SUM(ppi.quantity), 0)
                FROM purchase_product_items ppi
                JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                WHERE ppi.product_id = products.id
                ) as total_po_qty
            ');

        $query = static::applyFiltersToQuery($query, $filters);

        return $query->havingRaw('total_po_qty > 0')
                    ->orderBy('total_po_qty', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function($product) {
                        return (object)[
                            'id' => $product->id,
                            'name' => $product->name,
                            'code' => $product->code,
                            'category_name' => $product->category->name ?? 'No Category',
                            'current_stock' => $product->stock,
                            'requested_qty' => $product->requested_qty,
                            'processing_qty' => $product->processing_qty,
                            'shipped_qty' => $product->shipped_qty,
                            'received_qty' => $product->received_qty,
                            'done_qty' => $product->done_qty,
                            'total_po_qty' => $product->total_po_qty,
                            'display_name' => strlen($product->name) > 20 ?
                                            substr($product->name, 0, 20) . '...' :
                                            $product->name
                        ];
                    });
    }
}
