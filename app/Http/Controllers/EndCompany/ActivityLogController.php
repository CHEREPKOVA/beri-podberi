<?php

namespace App\Http\Controllers\EndCompany;

use App\Http\Controllers\Controller;
use App\Services\EndCompanyActionLogger;
use App\Services\EndCompanyCompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly EndCompanyCompanyContext $context,
        private readonly EndCompanyActionLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        $profile = $this->context->profile($request->user());
        abort_if($profile === null, 403);

        $events = $this->logger->paginateForProfile($profile);

        return view('end_company.activity.index', compact('events', 'profile'));
    }
}
