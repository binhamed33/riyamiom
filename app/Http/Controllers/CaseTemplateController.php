<?php

namespace App\Http\Controllers;

use App\Models\CaseTemplate;
use Illuminate\Http\Request;

class CaseTemplateController extends Controller
{
    public function index()
    {
        return view('case-templates.index', [
            'templates' => CaseTemplate::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:190',
            'items_text' => 'required|string|max:5000',
        ], [], [
            'name' => 'اسم القالب',
            'items_text' => 'مهام القالب',
        ]);

        // كل سطر مهمة: «العنوان | بعد كم يوم | الأولوية» — الجزءان الأخيران اختياريان
        $items = [];
        foreach (preg_split('/\r?\n/', $validated['items_text']) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $items[] = [
                'title' => $parts[0],
                'days_offset' => isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 0,
                'priority' => $parts[2] ?? 'medium',
            ];
        }

        if (!$items) {
            return back()->withErrors(['items_text' => 'أضف مهمة واحدة على الأقل.'])->withInput();
        }

        CaseTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'items' => $items,
        ]);

        return back()->with('success', 'أُنشئ القالب «' . $validated['name'] . '» بـ' . count($items) . ' مهام.');
    }

    public function destroy(CaseTemplate $caseTemplate)
    {
        $caseTemplate->delete();

        return back()->with('success', 'حُذف القالب.');
    }
}
