<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Services\DiscordNotifier;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function index()
    {
        $suggestions = Suggestion::where('user_id', auth()->id())->latest()->limit(50)->get();

        return view('suggestions.index', compact('suggestions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'content.required' => 'اكتب اقتراحك أولاً',
            'content.min' => 'يجب وصف الاقتراح بوصف جيد — 20 حرفاً على الأقل',
            'content.max' => 'الاقتراح أطول من المسموح (2000 حرف كحد أقصى)',
        ]);

        $suggestion = Suggestion::create([
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $sent = DiscordNotifier::sendSuggestion(auth()->user(), $suggestion->content);

        return back()->with('success', $sent
            ? 'تم إرسال اقتراحك بنجاح — شكراً لمساهمتك'
            : 'تم حفظ اقتراحك، لكن تعذر إرساله إلى ديسكورد حالياً — سنراجعه لاحقاً');
    }
}
