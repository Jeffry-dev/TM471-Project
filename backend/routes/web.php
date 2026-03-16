<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Simple API playground / docs page (dev-only)
Route::get('/docs', function () {
    abort_unless(app()->environment('local'), 404);

    return view('api-playground');
});
