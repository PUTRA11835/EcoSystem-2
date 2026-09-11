<?php

namespace App\Http\Controllers;

class HiddenTicketController extends Controller
{
    public function page()
    {
        return view('management.hidden-tickets.index');
    }
}
