<?php

namespace App\Http\Controllers;

use App\Models\RegistrationRequest;
use Illuminate\Http\Request;

class MarketingPageController extends Controller
{
    public function register()
    {
        return view('marketing.register');
    }

    public function storeRegister(Request $request)
    {
        $validated = $request->validate([
            'office_name' => ['required', 'string', 'max:190'],
            'contact_name' => ['required', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-()\s.]+$/'],
            'email' => ['required', 'email', 'max:190'],
            'lawyers_count' => ['nullable', 'integer', 'in:1,2,3,4'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        # فخ للروبوتات
        if (filled($request->input('website'))) {
            return back()->with('success', ' تم استلام طلبك بنجاح، سنتواصل معك قريباً.');
        }

        # حفظ البيانات في قاعدة البيانات
        RegistrationRequest::create($validated + ['status' => RegistrationRequest::STATUS_NEW]);

        # إرسال الإيميل مباشرة عبر دالة mail() في السيرفر (بما أنه يعمل بالفعل)
        $headers = "From: binhamed333@gmail.com\r\n" .
                   "Content-type: text/html; charset=utf-8\r\n";
        $message = "اسم المكتب: " . $validated['office_name'] . "\n" .
                   "اسم المسؤول: " . $validated['contact_name'] . "\n" .
                   "البريد الإلكتروني: " . $validated['email'] . "\n" .
                   "الرسالة: " . $validated['notes'] . "\n";
        mail('binhamed333@gmail.com', 'طلب تسجيل من مُداوَلة', $message, $headers);

        return back()->with('success', ' تم استلام طلبك بنجاح! سنتواصل معك خلال ٢٤–٤٨ ساعة.');
    }

    public function requests()
    {
        $requests = RegistrationRequest::latest()->paginate(25);

        return view('register-requests.index', compact('requests'));
    }

    public function updateStatus(Request $request, RegistrationRequest $registrationRequest)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(RegistrationRequest::STATUSES))],
        ]);

        $registrationRequest->update($validated);

        return back()->with('success', 'تم تحديث حالة الطلب إلى «' . $registrationRequest->status_label . '».');
    }

    public function guide()
    {
        return view('marketing.guide');
    }

    public function features()
    {
        return view('marketing.features');
    }

    public function pricing()
    {
        return view('marketing.pricing');
    }

    public function faq()
    {
        return view('marketing.faq');
    }

    public function blog()
    {
        return view('marketing.blog');
    }

    public function contact()
    {
        return view('marketing.contact');
    }

    public function privacy()
    {
        return view('marketing.privacy');
    }

    public function terms()
    {
        return view('marketing.terms');
    }
}
