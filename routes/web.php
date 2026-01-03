<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route di test
Route::get('/test', function () {
    return Inertia::render('TestTailwind');
});

// Home - redirect in base all'autenticazione
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome');
});

// Routes pubbliche (guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Login');
    })->name('login');
    
    Route::post('/login', [LoginController::class, 'store']);
    
    Route::get('/register', function () {
        return Inertia::render('Register');
    })->name('register');
    
    Route::post('/register', [RegisterController::class, 'store']);
});

// Routes protette (auth)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::resource('stocks', StockController::class)->except(['show', 'edit', 'update']);
    Route::post('stocks/{stock}/refresh', [StockController::class, 'refresh'])->name('stocks.refresh');
    
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/login');
    })->name('logout');
});

