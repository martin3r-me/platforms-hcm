<?php

use Illuminate\Support\Facades\Route;
use Platform\Hcm\Livewire\Public\ContractSigning;

Route::get('/contract/{token}', ContractSigning::class)->name('hcm.public.contract-signing');
