<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/', function () {
    return response()->json([
        'app' => 'Bangladesh Railway Employee Management System',
        'version' => '1.0.0',
        'status' => 'running',
        'api_docs' => url('/api'),
    ]);
});

// Fallback for SPA (if using React Router)
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');