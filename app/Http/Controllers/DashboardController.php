<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardStatsService $dashboard): View
    {
        return view('dashboard', [
            'dashboard' => $dashboard->forUser(auth()->user()),
        ]);
    }
}
