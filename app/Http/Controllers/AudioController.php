<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HasSidebarData;
use Illuminate\Http\Request;

class AudioController extends Controller
{
    use HasSidebarData;

    /**
     * Show audio browse page (same shell as World / AI: menu + conversation sidebar + main pane).
     */
    public function browse()
    {
        return view('audio.browse', $this->getSidebarData());
    }
}
