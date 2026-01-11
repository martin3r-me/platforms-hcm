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

/**
 * Bewegungsdaten-Endpunkt für Nostradamus
 * 
 * Empfängt Bewegungsdaten (Stempelzeiten, Urlaubstage, Abwesenheitstage)
 * und speichert sie in den HCM-Tabellen.
 */
Route::post('/movements', [\Platform\Hcm\Http\Controllers\Api\MovementDataController::class, 'store']);
