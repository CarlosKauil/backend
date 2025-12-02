<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;



Route::get('/archivo/{path}', function ($path) {
    $fullPath = 'obras/' . $path;

    if (!Storage::disk('public')->exists($fullPath)) {
        abort(404);
    }

    $file = Storage::disk('public')->get($fullPath);
    $mime = Storage::disk('public')->mimeType($fullPath);

    return Response::make($file, 200)->header("Content-Type", $mime);
})->where('path', '.*');


Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});



Route::get('/', function () {
    return view('welcome');
});
