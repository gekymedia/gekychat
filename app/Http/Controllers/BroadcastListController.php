<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BroadcastListController extends Controller
{
    /**
     * Display the broadcast lists page
     * GET /broadcast-lists
     */
    public function index()
    {
        return view('broadcast.index');
    }
}
