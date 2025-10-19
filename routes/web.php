<?php

use Illuminate\Support\Facades\Route;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;
use Platform\Hcm\Livewire\Employee\Index as EmployeeIndex;
use Platform\Hcm\Livewire\Employee\Employee as EmployeeShow;
use Platform\Hcm\Livewire\Employer\Index as EmployerIndex;
use Platform\Hcm\Livewire\Employer\Show as EmployerShow;
use Platform\Hcm\Livewire\Tariff\Index as TariffIndex;
use Platform\Hcm\Livewire\JobTitle\Index as JobTitleIndex;
use Platform\Hcm\Livewire\JobActivity\Index as JobActivityIndex;

Route::get('/', Platform\Hcm\Livewire\Dashboard::class)->name('hcm.dashboard');

// Arbeitgeber-Verwaltung
Route::get('/employers', EmployerIndex::class)->name('hcm.employers.index');
Route::get('/employers/{employer}', EmployerShow::class)->name('hcm.employers.show');

// Mitarbeiter-Verwaltung
Route::get('/employees', EmployeeIndex::class)->name('hcm.employees.index');
Route::get('/employees/{employee}', EmployeeShow::class)->name('hcm.employees.show');

// Tarife / Steuerklassen
Route::get('/tariffs', TariffIndex::class)->name('hcm.tariffs.index');

// Stellenbezeichnungen & Tätigkeiten
Route::get('/job-titles', JobTitleIndex::class)->name('hcm.job-titles.index');
Route::get('/job-activities', JobActivityIndex::class)->name('hcm.job-activities.index');

// Arbeitgeber-spezifische Mitarbeiter
Route::get('/employers/{employer}/employees', EmployeeIndex::class)->name('hcm.employers.employees.index');
Route::get('/employers/{employer}/employees/{employee}', EmployeeShow::class)->name('hcm.employers.employees.show');