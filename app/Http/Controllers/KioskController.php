<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function index()
    {
        return inertia('Kiosk/Index');
    }
}
