<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AudioController extends Controller
{
    /**
     * Show audio browse page
     */
    public function browse()
    {
        return view('audio.browse');
    }
}
