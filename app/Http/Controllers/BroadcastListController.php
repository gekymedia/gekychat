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

    /**
     * Display a specific broadcast list
     * GET /broadcast-lists/{id}
     */
    public function show($id)
    {
        $user = auth()->user();
        $broadcastList = \App\Models\BroadcastList::where('user_id', $user->id)
            ->with('recipients')
            ->findOrFail($id);
        
        $sidebarData = $this->getSidebarData();
        $sidebarData['broadcastList'] = $broadcastList;
        
        return view('broadcast.show', $sidebarData);
    }
}
