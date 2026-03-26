<?php

use Illuminate\Support\Facades\Route;
use Platform\Hcm\Http\Controllers\ContractPdfController;
use Platform\Hcm\Livewire\Public\ContractSigning;
use Platform\Hcm\Livewire\Public\OnboardingPortal;

Route::get('/contract/{token}', ContractSigning::class)->name('hcm.public.contract-signing');
Route::get('/onboarding/{token}', OnboardingPortal::class)->name('hcm.public.onboarding-portal');
Route::get('/onboarding/{token}/contract/{contractId}/pdf', ContractPdfController::class)->name('hcm.public.contract-pdf');
