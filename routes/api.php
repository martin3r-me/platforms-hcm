<?php

use Illuminate\Support\Facades\Route;
use Platform\Hcm\Http\Controllers\Api\EmployeeNostradamusController;

/**
 * HCM API Routes
 * 
 * Nostradamus-Endpunkt für Employee-Daten
 */
Route::get('/employees/nostradamus', [EmployeeNostradamusController::class, 'index']);
Route::get('/employees/nostradamus/health', [EmployeeNostradamusController::class, 'health']);

