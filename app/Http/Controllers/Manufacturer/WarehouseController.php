<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->getOrCreateManufacturerProfile();
        $profile->load(['warehouses.region']);

        $regions = Region::active()->orderBy('name')->get();

        return view('manufacturer.warehouses.index', compact('profile', 'regions'));
    }
}
