<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\POReportProductOverviewResource;
use App\Http\Resources\POReportProductTrendResource;
use App\Models\POReportProduct;
use Illuminate\Http\Request;

class POReportProductController extends Controller
{
    /**
     * Get dashboard overview with role-based filtering
     */
    public function getOverview(Request $request)
    {
        $user = auth()->user();

        // Build filters - handle formData format
        $filters = [];

        // Handle type_po - bisa array atau string
        if ($request->has('type_po')) {
            $typePo = $request->type_po;
            // Jika string, convert ke array
            if (is_string($typePo) && !empty($typePo)) {
                $filters['type_po'] = [$typePo];
            } elseif (is_array($typePo) && !empty($typePo)) {
                $filters['type_po'] = $typePo;
            }
        }

        // Handle date filters
        if ($request->has('date_from') && !empty($request->date_from)) {
            $filters['date_from'] = $request->date_from;
        }

        if ($request->has('date_until') && !empty($request->date_until)) {
            $filters['date_until'] = $request->date_until;
        }

        // Role-based filtering: User hanya bisa lihat data branch sendiri
        if ($user->hasRole('User')) {
            $filters['branch_id'] = $user->branch_id;
        } else {
            // Admin/SuperAdmin bisa filter by branch atau lihat semua
            if ($request->has('branch_id') && !empty($request->branch_id)) {
                $filters['branch_id'] = (int)$request->branch_id;
            }
        }

        $overview = POReportProduct::getFilteredOverviewStats($filters);

        return response()->json([
            'success' => true,
            'data' => new POReportProductOverviewResource($overview),
            'message' => 'PO Product Overview Retrieved Successfully'
        ]);
    }

    /**
     * Get trends chart data with role-based filtering
     */
    public function getTrends(Request $request)
    {
        $user = auth()->user();

        // Build filters - handle formData format
        $filters = [];

        // Handle type_po - bisa array atau string
        if ($request->has('type_po')) {
            $typePo = $request->type_po;
            // Jika string, convert ke array
            if (is_string($typePo) && !empty($typePo)) {
                $filters['type_po'] = [$typePo];
            } elseif (is_array($typePo) && !empty($typePo)) {
                $filters['type_po'] = $typePo;
            }
        }

        // Handle date filters
        if ($request->has('date_from') && !empty($request->date_from)) {
            $filters['date_from'] = $request->date_from;
        }

        if ($request->has('date_until') && !empty($request->date_until)) {
            $filters['date_until'] = $request->date_until;
        }

        // Role-based filtering: User hanya bisa lihat data branch sendiri
        if ($user->hasRole('User')) {
            $filters['branch_id'] = $user->branch_id;
        } else {
            // Admin/SuperAdmin bisa filter by branch atau lihat semua
            if ($request->has('branch_id') && !empty($request->branch_id)) {
                $filters['branch_id'] = (int)$request->branch_id;
            }
        }

        $trends = POReportProduct::getFilteredMonthlyTrends($filters);
        $periodLabel = POReportProduct::getPeriodLabel($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'period_label' => $periodLabel,
                'chart_data' => POReportProductTrendResource::collection($trends),
            ],
            'message' => 'PO Product Trends Retrieved Successfully'
        ]);
    }
}
