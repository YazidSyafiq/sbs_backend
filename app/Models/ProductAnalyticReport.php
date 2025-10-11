<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductAnalyticReport extends Model
{
    // Gunakan table yang sama dengan Product
    protected $table = 'products';

    // Cast untuk data types
    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Relasi ke Category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Relasi ke ProductBatches
     */
    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_id');
    }

    /**
     * Get total stock dari semua batch
     */
    public function getTotalStockAttribute(): int
    {
        return $this->productBatches->sum('quantity');
    }

    /**
     * Get available stock (stock > 0)
     */
    public function getAvailableStockAttribute(): int
    {
        return $this->productBatches->where('quantity', '>', 0)->sum('quantity');
    }

    /**
     * Get average cost price dari semua batch yang tersedia
     */
    public function getAverageCostPriceAttribute(): float
    {
        $batches = $this->productBatches->where('quantity', '>', 0);

        if ($batches->isEmpty()) {
            return 0;
        }

        $totalValue = 0;
        $totalQuantity = 0;

        foreach ($batches as $batch) {
            $totalValue += $batch->quantity * $batch->cost_price;
            $totalQuantity += $batch->quantity;
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }

    /**
     * Get pending orders dari PurchaseProduct
     */
    public function getPendingOrdersAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->whereIn('status', ['Requested', 'Processing']);
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    /**
     * Calculate need purchase quantity
     */
    public function getNeedPurchaseAttribute(): int
    {
        $availableStock = $this->available_stock;
        $pendingOrders = $this->pending_orders;

        if ($pendingOrders > $availableStock) {
            return $pendingOrders - $availableStock;
        }

        return 0;
    }

    /**
     * Get stock status
     */
    public function getStockStatusAttribute(): string
    {
        $availableStock = $this->available_stock;

        if ($availableStock <= 0) {
            return 'Out of Stock';
        } elseif ($availableStock < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

    /**
     * Get stock value (stock * selling price)
     */
    public function getStockValueAttribute(): float
    {
        return $this->total_stock * $this->price;
    }

    /**
     * Get PO status quantities
     */
    public function getTotalPurchasedAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->whereIn('status', ['Processing', 'Shipped', 'Received', 'Done']);
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    public function getRequestedAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->where('status', 'Requested');
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    public function getProcessingAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->where('status', 'Processing');
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    public function getShippedAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->where('status', 'Shipped');
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    public function getReceivedAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->where('status', 'Received');
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    public function getDoneAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->where('status', 'Done');
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    /**
     * Helper method to apply filters to any query
     */
    public static function applyFiltersToQuery($query, $filters = [])
    {
        // Product filter
        if (!empty($filters['product_ids'])) {
            $query->whereIn('id', $filters['product_ids']);
        }

        // Category filter
        if (!empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        // Date range filter - filter berdasarkan ProductBatch entry_date
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $query->whereHas('productBatches', function($batchQuery) use ($filters) {
                if (!empty($filters['date_from'])) {
                    $batchQuery->whereDate('entry_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_until'])) {
                    $batchQuery->whereDate('entry_date', '<=', $filters['date_until']);
                }
            });
        }

        return $query;
    }

    /**
     * Get filtered overview statistics
     */
    public static function getFilteredOverviewStats($filters = [])
    {
        $query = static::query()->with(['productBatches']);
        $query = static::applyFiltersToQuery($query, $filters);

        $products = $query->get();

        $totalProducts = $products->count();
        $outOfStock = 0;
        $lowStock = 0;
        $inStock = 0;
        $totalStockValue = 0;
        $totalStockUnits = 0;
        $productsNeedingPurchase = 0;

        foreach ($products as $product) {
            $availableStock = $product->available_stock;
            $totalStock = $product->total_stock;

            // Stock status counts
            if ($availableStock <= 0) {
                $outOfStock++;
            } elseif ($availableStock < 10) {
                $lowStock++;
            } else {
                $inStock++;
            }

            // Calculations
            $totalStockValue += $totalStock * $product->price;
            $totalStockUnits += $totalStock;

            if ($product->need_purchase > 0) {
                $productsNeedingPurchase++;
            }
        }

        return (object)[
            'total_products' => $totalProducts,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'in_stock' => $inStock,
            'total_stock_value' => $totalStockValue,
            'total_stock_units' => $totalStockUnits,
            'products_needing_purchase' => $productsNeedingPurchase,
        ];
    }

    /**
     * Get filtered summary by category
     */
    public static function getFilteredSummaryByCategory($filters = [])
    {
        $query = static::query()->with(['category', 'productBatches']);
        $query = static::applyFiltersToQuery($query, $filters);

        $products = $query->get()->groupBy('category_id');

        $categoryStats = collect();

        foreach ($products as $categoryId => $categoryProducts) {
            $category = $categoryProducts->first()->category;

            $productCount = $categoryProducts->count();
            $totalStock = 0;
            $totalValue = 0;
            $outOfStockCount = 0;
            $lowStockCount = 0;

            foreach ($categoryProducts as $product) {
                $totalStock += $product->total_stock;
                $totalValue += $product->stock_value;

                $availableStock = $product->available_stock;
                if ($availableStock <= 0) {
                    $outOfStockCount++;
                } elseif ($availableStock < 10) {
                    $lowStockCount++;
                }
            }

            $healthyCount = $productCount - $outOfStockCount - $lowStockCount;
            $stockHealthRate = $productCount > 0 ? round(($healthyCount / $productCount) * 100, 1) : 0;

            $categoryStats->push((object)[
                'category_id' => $categoryId,
                'category_name' => $category->name ?? 'Unknown',
                'product_count' => $productCount,
                'total_stock' => $totalStock,
                'total_value' => $totalValue,
                'out_of_stock_count' => $outOfStockCount,
                'low_stock_count' => $lowStockCount,
                'stock_health_rate' => $stockHealthRate,
            ]);
        }

        return $categoryStats->sortByDesc('total_value');
    }

    /**
     * Get products needing attention dengan detailed case handling
     */
    public static function getProductsNeedingAttention($filters = [], $limit = 6)
    {
        $query = static::query()->with(['category', 'productBatches']);
        $query = static::applyFiltersToQuery($query, $filters);

        $products = $query->get();
        $attentionItems = collect();
        $now = Carbon::now();

        foreach ($products as $product) {
            // Check untuk expired/expiring batches dengan stock > 0
            $expiringBatches = $product->productBatches->filter(function($batch) use ($now) {
                return $batch->quantity > 0 &&
                       $batch->expiry_date &&
                       $batch->expiry_date->lte($now->copy()->addDays(30));
            });

            foreach ($expiringBatches as $batch) {
                $isExpired = $batch->expiry_date->lt($now);

                $attentionItems->push((object)[
                    'id' => $product->id . '_batch_' . $batch->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'display_name' => strlen($product->name) > 20 ? substr($product->name, 0, 20) . '...' : $product->name,
                    'status_text' => $isExpired ? 'EXPIRED' : 'EXPIRING SOON',
                    'detail_message' => 'Batch: ' . $batch->batch_number . ' | Exp: ' . $batch->expiry_date->format('d M Y'),
                    'attention_type' => $isExpired ? 'expired' : 'expiring_soon',
                    'category' => $product->category,
                    'priority' => $isExpired ? 1 : 2,
                ]);
            }

            // Check untuk stock issues
            $availableStock = $product->available_stock;
            if ($availableStock <= 0) {
                $attentionItems->push((object)[
                    'id' => $product->id . '_stock',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'display_name' => strlen($product->name) > 20 ? substr($product->name, 0, 20) . '...' : $product->name,
                    'status_text' => 'OUT OF STOCK',
                    'detail_message' => '0 units available',
                    'attention_type' => 'out_of_stock',
                    'category' => $product->category,
                    'priority' => 3,
                ]);
            } elseif ($availableStock < 10) {
                $attentionItems->push((object)[
                    'id' => $product->id . '_stock',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'display_name' => strlen($product->name) > 20 ? substr($product->name, 0, 20) . '...' : $product->name,
                    'status_text' => 'LOW STOCK',
                    'detail_message' => $availableStock . ' units remaining',
                    'attention_type' => 'low_stock',
                    'category' => $product->category,
                    'priority' => 4,
                ]);
            }

            // Check untuk need purchase
            $needPurchase = $product->need_purchase;
            if ($needPurchase > 0) {
                $attentionItems->push((object)[
                    'id' => $product->id . '_purchase',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'display_name' => strlen($product->name) > 20 ? substr($product->name, 0, 20) . '...' : $product->name,
                    'status_text' => 'NEED PURCHASE',
                    'detail_message' => $needPurchase . ' units needed',
                    'attention_type' => 'need_purchase',
                    'category' => $product->category,
                    'priority' => 5,
                ]);
            }
        }

        return $attentionItems->sortBy('priority')->take($limit);
    }

    /**
     * Get entry trend data from PurchaseProductSupplier
     */
    public static function getProductEntryTrend($filters = [], $days = 30)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays($days);

        // Get products that match filters
        $query = static::query();
        $query = static::applyFiltersToQuery($query, $filters);
        $productIds = $query->pluck('id');

        if ($productIds->isEmpty()) {
            return (object)['labels' => [], 'data' => []];
        }

        // Get daily entry data from PurchaseProductSupplier
        $dailyEntries = PurchaseProductSupplier::whereIn('product_id', $productIds)
            ->whereIn('status', ['Received', 'Done'])
            ->whereNotNull('received_date')
            ->whereBetween('received_date', [$startDate, $endDate])
            ->selectRaw('DATE(received_date) as date, SUM(quantity) as total_quantity')
            ->groupByRaw('DATE(received_date)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill missing dates with zero
        $trendData = [];
        $labels = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $displayDate = $date->format('d M');

            $quantity = $dailyEntries->get($dateStr)?->total_quantity ?? 0;

            $labels[] = $displayDate;
            $trendData[] = (int) $quantity;
        }

        return (object)[
            'labels' => $labels,
            'data' => $trendData,
        ];
    }

    /**
     * Get Product PO Status Breakdown
     */
    public static function getProductPOStatusBreakdown($filters = [], $limit = 10)
    {
        $query = static::query()->with(['category']);
        $query = static::applyFiltersToQuery($query, $filters);

        $products = $query->get();

        $poBreakdown = collect();

        foreach ($products as $product) {
            $requested = $product->requested;
            $processing = $product->processing;
            $shipped = $product->shipped;
            $received = $product->received;
            $done = $product->done;
            $totalPO = $requested + $processing + $shipped + $received + $done;

            if ($totalPO > 0) {
                $poBreakdown->push((object)[
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'display_name' => strlen($product->name) > 20 ? substr($product->name, 0, 20) . '...' : $product->name,
                    'category_name' => $product->category->name ?? 'No Category',
                    'current_stock' => $product->total_stock,
                    'requested' => $requested,
                    'processing' => $processing,
                    'shipped' => $shipped,
                    'received' => $received,
                    'done' => $done,
                    'total_po' => $totalPO,
                ]);
            }
        }

        return $poBreakdown->sortByDesc('total_po')->take($limit);
    }
}
