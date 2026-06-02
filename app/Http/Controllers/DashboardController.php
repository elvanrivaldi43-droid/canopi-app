<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function owner()
    {
        return view('dashboard.owner');
    }

    public function admin()
    {
        return view('dashboard.admin');
    }

    public function supervisor()
    {
        return view('dashboard.supervisor');
    }

    public function marketing()
    {
        return view('dashboard.marketing');
    }

    public function teknisi()
    {
        return view('dashboard.teknisi');
    }

    public function driver()
    {
        return view('dashboard.driver');
    }

    public function toko()
    {
        return view('dashboard.toko');
    }
}