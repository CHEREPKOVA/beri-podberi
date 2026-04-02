<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DirectoriesController extends Controller
{
    public function index(): View
    {
        return view('admin.directories.index');
    }
}
