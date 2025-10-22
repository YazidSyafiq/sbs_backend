<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Branch;
use App\Http\Resources\BranchResource;

class BranchController extends Controller
{
    // Branch List
    public function getBranch()
    {
        $branch = Branch::all();

        return BranchResource::collection($branch);
    }
}
