<?php

use App\Http\Controllers\BioTimeBackfillController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Checadas que envía el plugin local (PC del reloj) cuando el ERP vive en un VPS
// y no tiene ruta de red a la IP privada del reloj. Protegido con BACKFILL_KEY.
Route::post('/biotime-punches', [BioTimeBackfillController::class, 'store']);
