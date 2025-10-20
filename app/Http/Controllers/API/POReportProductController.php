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

        // Build filters
        $filters = [
            'type_po' => $request->type_po,
            'date_from' => $request->date_from,
            'date_until' => $request->date_until,
        ];

        // Role-based filtering: User hanya bisa lihat data branch sendiri
        if ($user->hasRole('User')) {
            $filters['branch_id'] = $user->branch_id;
        } else {
            // Admin/SuperAdmin bisa filter by branch atau lihat semua
            if ($request->has('branch_id') && $request->branch_id) {
                $filters['branch_id'] = $request->branch_id;
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

        // Build filters
        $filters = [
            'type_po' => $request->type_po,
            'date_from' => $request->date_from,
            'date_until' => $request->date_until,
        ];

        // Role-based filtering: User hanya bisa lihat data branch sendiri
        if ($user->hasRole('User')) {
            $filters['branch_id'] = $user->branch_id;
        } else {
            // Admin/SuperAdmin bisa filter by branch atau lihat semua
            if ($request->has('branch_id') && $request->branch_id) {
                $filters['branch_id'] = $request->branch_id;
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
