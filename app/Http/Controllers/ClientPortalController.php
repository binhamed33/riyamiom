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

        // القاعدةُ نفسُها التي في documents() أدناه — كانت هذه الصفحةُ
        // تُحمّل المستندات بلا تصفيةٍ أصلاً، فيقرأ الموكّلُ عناوينَ كلّ
        // ورقةٍ داخليّةٍ في ملفّه: مسوّداتٌ، مذكّراتٌ لم تُقدَّم، تقاريرُ
        // خبراء. والرافعُ لا يُحمَّل: اسمُ الموظّف شأنُ المكتب.
        $case->load([
            'lawyer',
            'sessions',
            'documents' => fn ($q) => $q->where('client_visible', true)
                ->where('access_level', '!=', Document::ACCESS_PRIVATE),
        ]);

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

        // ═══ ما يراه الموكّل هو ما وُسم له صراحةً ═══
        //
        // كان الشرطُ access_level='all' وحدَه — و«all» تعني «كلُّ
        // الفريق» لا «الموكّل». فكلُّ مستندٍ عامٍّ في قضاياه يصله بلا
        // قرارٍ من أحد: مسوّداتٌ داخلية، مذكّراتٌ لم تُقدَّم بعد،
        // تقاريرُ خصمٍ. والبوّابةُ الأخرى (ClientCaseGateway) تشترط
        // client_visible صراحةً — فاختلف البابان في الشيء نفسِه.
        //
        // والرافعُ لا يُحمَّل: اسمُ الموظّف الذي رفع الورقة شأنُ
        // المكتب لا شأنُ الموكّل.
        $documents = Document::whereIn('case_id', $caseIds)
            ->where('client_visible', true)
            ->where('access_level', '!=', Document::ACCESS_PRIVATE)
            ->with('case')
            ->latest()
            ->paginate(15);

        return view('client-portal.documents', compact('documents', 'client'));
    }
}
