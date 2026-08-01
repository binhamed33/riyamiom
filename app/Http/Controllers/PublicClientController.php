<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicClientController extends Controller
{
    public function showLookupForm(): View
    {
        return view('client-portal.public-lookup');
    }

    public function lookup(Request $request): View|RedirectResponse
    {
        $request->validate([
            'credential' => 'required|string|max:255',
        ]);

        $value = trim($request->credential);
        $phoneValue = $this->normalizePhone($value);
        $emailValue = $this->normalizeEmail($value);

        $match = Client::with('cases.lawyer')->get()->first(function ($client) use ($phoneValue, $emailValue) {
            if ($client->email && $this->normalizeEmail($client->email) === $emailValue) {
                return true;
            }

            if (!$client->phone) {
                return false;
            }

            foreach (array_map('trim', explode(',', $client->phone)) as $phone) {
                if ($this->normalizePhone($phone) === $phoneValue) {
                    return true;
                }
            }

            return false;
        });

        if (!$match) {
            return back()->with('error', 'لم يتم العثور على بيانات مطابقة، يرجى التأكد من المعلومات المدخلة');
        }

        session(['client_access_id' => $match->id, 'client_access_name' => $match->name]);

        $cases = LegalCase::where('client_id', $match->id)
            ->with('lawyer')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('client-portal.public-cases', compact('cases', 'match'));
    }

    public function showCase(LegalCase $case): View|RedirectResponse
    {
        $clientId = session('client_access_id');
        if (!$clientId || $case->client_id !== $clientId) {
            return redirect()->route('client.access')->with('error', 'الرجاء تسجيل الدخول أولاً');
        }

        $case->load(['lawyer', 'sessions', 'documents.uploader']);

        $nextSession = $case->sessions->where('status', 'scheduled')->sortBy('date')->first();
        $pastSessions = $case->sessions->whereIn('status', ['held', 'postponed', 'cancelled'])->sortByDesc('date');

        return view('client-portal.public-case-show', compact('case', 'nextSession', 'pastSessions'));
    }

    public function logout(): RedirectResponse
    {
        session()->forget(['client_access_id', 'client_access_name']);
        return redirect()->route('client.access')->with('success', 'تم تسجيل الخروج بنجاح');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '968')) {
            $digits = substr($digits, 3);
        }

        return ltrim($digits, '0');
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
