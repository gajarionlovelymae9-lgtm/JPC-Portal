<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Redirect login requests to the frontend dev server
Route::get('/login', function () {
    return redirect('http://127.0.0.1:5173/login');
});
