<?php

// app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stocks = auth()->user()
            ->stocks()
            ->orderBy('symbol')
            ->get();

        return Inertia::render('Dashboard', [
            'stocks' => $stocks
        ]);
    }
}