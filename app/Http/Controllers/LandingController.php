<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * Show the landing page
     */
    public function index()
    {
        return view('landing.index');
    }

    /**
     * Show features page
     */
    public function features()
    {
        return view('landing.features');
    }

    /**
     * Show pricing page
     */
    public function pricing()
    {
        return view('landing.pricing');
    }

    /**
     * Show documentation page
     */
    public function docs()
    {
        return view('landing.docs');
    }

    /**
     * Help center (FAQs and guides)
     */
    public function help()
    {
        return view('pages.help');
    }

    /**
     * Contact / support page
     */
    public function contact()
    {
        return view('pages.contact');
    }

    /**
     * Redirect to chat login
     */
    public function login()
    {
        return redirect('https://chat.gekychat.com/login');
    }
}

