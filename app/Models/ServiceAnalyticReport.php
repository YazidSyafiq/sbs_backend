<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ServiceAnalyticReport extends Model
{
    // Gunakan table yang sama dengan Service
    protected $table = 'services';

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
     * Relasi ke Code Template
     */
    public function codeTemplate(): BelongsTo
    {
        return $this->belongsTo(Code::class, 'code_id');
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

        // Price range filters
        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
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

        $allServices = $query->get();

        $totalServices = $allServices->count();
        $highestPrice = $allServices->max('price') ?? 0;
        $lowestPrice = $allServices->min('price') ?? 0;
        $totalServiceValue = $allServices->sum('price');

        return (object)[
            'total_services' => $totalServices,
            'highest_price' => $highestPrice,
            'lowest_price' => $lowestPrice,
            'total_service_value' => $totalServiceValue,
        ];
    }

    /**
     * Get filtered summary by category
     */
    public static function getFilteredSummaryByCategory($filters = [])
    {
        $query = static::with(['category'])
            ->select('category_id')
            ->selectRaw('COUNT(*) as service_count')
            ->selectRaw('SUM(price) as total_value')
            ->selectRaw('MAX(price) as max_price')
            ->selectRaw('MIN(price) as min_price');

        $query = static::applyFiltersToQuery($query, $filters);

        return $query->groupBy('category_id')
                    ->get()
                    ->map(function($item) {
                        $category = ProductCategory::find($item->category_id);

                        return (object)[
                            'category_id' => $item->category_id,
                            'category_name' => $category->name ?? 'Unknown',
                            'service_count' => $item->service_count,
                            'total_value' => $item->total_value,
                            'max_price' => $item->max_price,
                            'min_price' => $item->min_price,
                        ];
                    })
                    ->sortByDesc('total_value');
    }

    /**
     * Get service PO status distribution
     */
    public static function getServicePOStatusDistribution($filters = [], $limit = 10)
    {
        $query = static::with(['category'])
            ->select('services.*')
            ->selectRaw('
                (SELECT COALESCE(COUNT(spi.id), 0)
                FROM service_purchase_items spi
                JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                WHERE spi.service_id = services.id
                AND sp.status = "Requested"
                ) as requested_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(COUNT(spi.id), 0)
                FROM service_purchase_items spi
                JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                WHERE spi.service_id = services.id
                AND sp.status = "Approved"
                ) as approved_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(COUNT(spi.id), 0)
                FROM service_purchase_items spi
                JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                WHERE spi.service_id = services.id
                AND sp.status = "In Progress"
                ) as progress_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(COUNT(spi.id), 0)
                FROM service_purchase_items spi
                JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                WHERE spi.service_id = services.id
                AND sp.status = "Done"
                ) as done_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(COUNT(spi.id), 0)
                FROM service_purchase_items spi
                JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                WHERE spi.service_id = services.id
                AND sp.status = "Cancelled"
                ) as cancelled_qty
            ')
            ->selectRaw('
                (SELECT COALESCE(COUNT(spi.id), 0)
                FROM service_purchase_items spi
                JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                WHERE spi.service_id = services.id
                ) as total_po_qty
            ');

        $query = static::applyFiltersToQuery($query, $filters);

        return $query->havingRaw('total_po_qty > 0')
                    ->orderBy('total_po_qty', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function($service) {
                        return (object)[
                            'id' => $service->id,
                            'name' => $service->name,
                            'code' => $service->code,
                            'category_name' => $service->category->name ?? 'No Category',
                            'price' => $service->price,
                            'requested_qty' => $service->requested_qty,
                            'approved_qty' => $service->approved_qty,
                            'progress_qty' => $service->progress_qty,
                            'done_qty' => $service->done_qty,
                            'cancelled_qty' => $service->cancelled_qty,
                            'total_po_qty' => $service->total_po_qty,
                            'display_name' => strlen($service->name) > 20 ?
                                            substr($service->name, 0, 20) . '...' :
                                            $service->name
                        ];
                    });
    }
}
