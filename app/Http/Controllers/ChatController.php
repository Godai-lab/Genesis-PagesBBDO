<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Display the chat page.
     */
    public function index()
    {
        return view('chat');
    }
}
