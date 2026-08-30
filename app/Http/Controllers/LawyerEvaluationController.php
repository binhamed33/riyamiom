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

        // §4: ترتيب صفوف محسوبة — الرتبة (rank) ثابتة بالنقاط، والعرض يُرتب
        $sort = (string) $request->query('sort', 'score');
        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $key = match ($sort) {
            'name' => fn ($r) => $r['name'],
            'cases' => fn ($r) => $r['metrics']['cases_total'] ?? 0,
            'tasks' => fn ($r) => $r['metrics']['tasks_completed'] ?? 0,
            default => fn ($r) => $r['metrics']['score'] ?? 0,
        };
        $rows = collect($rows);
        $rows = ($dir === 'asc' ? $rows->sortBy($key) : $rows->sortByDesc($key))->values()->all();

        return view('evaluations.index', compact('rows', 'period'));
    }
}
