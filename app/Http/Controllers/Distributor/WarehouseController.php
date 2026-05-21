<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->getOrCreateDistributorProfile();
        $profile->load(['warehouses.region']);
        $regions = Region::active()->orderBy('name')->get();

        return view('distributor.warehouses.index', compact('profile', 'regions'));
    }
}
