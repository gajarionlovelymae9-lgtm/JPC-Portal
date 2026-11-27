<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Redirect login requests to the frontend SPA
Route::get('/login', function () {
    $frontendUrl = rtrim(env('FRONTEND_URL', app()->environment('production') ? 'https://www.jpc-portal.com' : 'http://127.0.0.1:5173'), '/');

    return redirect($frontendUrl.'/login');
});
