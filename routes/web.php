<?php
use Inertia\Inertia;

Route::get('/test', function () {
    return Inertia::render('Test');
});

