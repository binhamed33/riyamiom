<?php

namespace App\Http\Controllers;

use App\Services\AttentionService;
use Illuminate\Http\Request;

class AttentionController extends Controller
{
    public function index(Request $request, AttentionService $service)
    {
        $items = $service->items();

        if ($request->wantsJson()) {
            return response()->json(['items' => $items]);
        }

        return view('attention.index', ['items' => $items]);
    }
}