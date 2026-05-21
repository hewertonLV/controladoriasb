<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardIndexRequest;
use App\Services\Dashboard\DashboardFinanceiroService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardIndexRequest $request, DashboardFinanceiroService $financeiro): View
    {
        $user = $request->user();

        return view('dashboard', [
            'financeiro' => $financeiro->forUser($user, $request->unidadeIdsFiltro()),
        ]);
    }
}
