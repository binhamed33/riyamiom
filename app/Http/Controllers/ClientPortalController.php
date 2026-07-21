<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    private function getClient()
    {
        return Client::where('user_id', auth()->id())->first();
    }

    public function cases(): View
    {
        $client = $this->getClient();
        abort_unless($client, 404);

        $cases = LegalCase::where('client_id', $client->id)
            ->with(['lawyer'])
            ->latest()
            ->paginate(15);

        return view('client-portal.cases', compact('cases', 'client'));
    }

    public function showCase(LegalCase $case): View
    {
        $client = $this->getClient();
        abort_unless($client, 404);
        abort_unless($case->client_id === $client->id, 403);

        $case->load(['lawyer', 'sessions', 'documents.uploader']);

        return view('client-portal.case-show', compact('case', 'client'));
    }

    public function sessions(): View
    {
        $client = $this->getClient();
        abort_unless($client, 404);

        $caseIds = LegalCase::where('client_id', $client->id)->pluck('id');

        $sessions = Session::whereIn('case_id', $caseIds)
            ->with('case')
            ->orderBy('date', 'desc')
            ->paginate(15);

        return view('client-portal.sessions', compact('sessions', 'client'));
    }

    public function documents(): View
    {
        $client = $this->getClient();
        abort_unless($client, 404);

        $caseIds = LegalCase::where('client_id', $client->id)->pluck('id');

        $documents = Document::where(function ($query) use ($caseIds) {
            $query->whereIn('case_id', $caseIds)
                ->where('access_level', 'all');
        })->orWhere(function ($query) {
            $query->where('uploaded_by', auth()->id())
                ->where('access_level', 'private');
        })->with(['case', 'uploader'])
            ->latest()
            ->paginate(15);

        return view('client-portal.documents', compact('documents', 'client'));
    }
}
