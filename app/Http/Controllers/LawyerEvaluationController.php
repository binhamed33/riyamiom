<?php

namespace App\Http\Controllers;

use App\Services\LawyerEvaluationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LawyerEvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('period', 'all');

        if (!in_array($period, LawyerEvaluationService::PERIODS, true)) {
            $period = 'all';
        }

        $rows = app(LawyerEvaluationService::class)->evaluate($period);

        return view('evaluations.index', compact('rows', 'period'));
    }
}
