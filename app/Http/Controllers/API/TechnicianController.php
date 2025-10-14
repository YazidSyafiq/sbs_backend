<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Technician;
use App\Http\Resources\TechnicianResource;

class TechnicianController extends Controller
{
    // Technician
    public function getTechnician()
    {
        $technician = Technician::all();

        return TechnicianResource::collection($technician);
    }
}
