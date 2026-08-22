<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    public function switch($locale)
    {
        if (!in_array($locale, ['ar', 'en'], true)) {
            return redirect()->back();
        }

        session(['locale' => $locale]);
        App::setLocale($locale);

        // يُحفظ على المستخدم نفسه فلا يضيع بالخروج ولا على جهاز آخر،
        // وتتبعه إشعاراته
        if ($user = auth()->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return redirect()->back();
    }
}
