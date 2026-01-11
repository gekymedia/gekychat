<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasSidebarData;
use Illuminate\Http\Request;

class BroadcastListController extends Controller
{
    use HasSidebarData;

    /**
     * Display the broadcast lists page
     * GET /broadcast-lists
     */
    public function index()
    {
        $sidebarData = $this->getSidebarData();
        return view('broadcast.index', $sidebarData);
    }
}
