<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Http\Resources\ServiceResource;

class ServiceController extends Controller
{
    // Service
    public function getService()
    {
        $services = Service::all();

        return ServiceResource::collection($services);
    }
}
