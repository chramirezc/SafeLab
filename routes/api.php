<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScanController;

Route::post('/scan/file', [ScanController::class, 'scanFile']);
Route::post('/scan/url', [ScanController::class, 'scanUrl']);
Route::get('/scan/progress/{scanId}', [ScanController::class, 'getProgress']);
Route::get('/scan/result/{scanId}', [ScanController::class, 'getResult']);
