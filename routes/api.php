<?php

use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('assets', [V1Controller::class, 'assets']);
    Route::get('assets/{code}', [V1Controller::class, 'asset']);
    Route::post('assets/{code}/checkout', [V1Controller::class, 'checkout']);
    Route::post('checkouts/{checkout}/checkin', [V1Controller::class, 'checkin']);
    Route::get('projects', [V1Controller::class, 'projects']);
    Route::get('projects/{project}/items', [V1Controller::class, 'projectItems']);
});
