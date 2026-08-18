<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MarketingPageController extends Controller
{
    public function register()
    {
        return view('marketing.register');
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
