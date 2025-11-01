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
use Platform\Hcm\Livewire\PayrollType\Index as PayrollTypeIndex;
use Platform\Hcm\Livewire\PersonGroup\Index as PersonGroupIndex;
use Platform\Hcm\Livewire\InsuranceStatus\Index as InsuranceStatusIndex;
use Platform\Hcm\Livewire\PensionType\Index as PensionTypeIndex;
use Platform\Hcm\Livewire\EmploymentRelationship\Index as EmploymentRelationshipIndex;
use Platform\Hcm\Livewire\LevyType\Index as LevyTypeIndex;

Route::get('/', Platform\Hcm\Livewire\Dashboard::class)->name('hcm.dashboard');

// Arbeitgeber-Verwaltung
Route::get('/employers', EmployerIndex::class)->name('hcm.employers.index');
Route::get('/employers/{employer}', EmployerShow::class)->name('hcm.employers.show');
Route::get('/employers/{employer}/benefits', \Platform\Hcm\Livewire\Employer\BenefitsIndex::class)->name('hcm.employers.benefits.index');

// Mitarbeiter-Verwaltung
Route::get('/employees', EmployeeIndex::class)->name('hcm.employees.index');
Route::get('/employees/{employee}', EmployeeShow::class)->name('hcm.employees.show');
Route::get('/employees/{employee}/benefits', \Platform\Hcm\Livewire\Employee\BenefitsIndex::class)->name('hcm.employees.benefits.index');
Route::get('/employees/{employee}/issues', \Platform\Hcm\Livewire\Employee\IssuesIndex::class)->name('hcm.employees.issues.index');

// Verträge
Route::get('/contracts/{contract}', \Platform\Hcm\Livewire\Contract\Show::class)->name('hcm.contracts.show');

// Krankenkassen
Route::get('/health-insurance-companies', \Platform\Hcm\Livewire\HealthInsuranceCompany\Index::class)->name('hcm.health-insurance-companies.index');
Route::get('/health-insurance-companies/{healthInsuranceCompany}', \Platform\Hcm\Livewire\HealthInsuranceCompany\Show::class)->name('hcm.health-insurance-companies.show');

// Tarife / Steuerklassen
Route::get('/tariffs', TariffIndex::class)->name('hcm.tariffs.index');

// Tarif-Übersicht
Route::get('/tariff-overview', \Platform\Hcm\Livewire\Tariff\Overview::class)->name('hcm.tariff-overview');

// Tarifverträge
Route::get('/tariff-agreements', \Platform\Hcm\Livewire\TariffAgreement\Index::class)->name('hcm.tariff-agreements.index');
Route::get('/tariff-agreements/{tariffAgreement}', \Platform\Hcm\Livewire\TariffAgreement\Show::class)->name('hcm.tariff-agreements.show');

// Tarifgruppen
Route::get('/tariff-groups', \Platform\Hcm\Livewire\TariffGroup\Index::class)->name('hcm.tariff-groups.index');
Route::get('/tariff-groups/{tariffGroup}', \Platform\Hcm\Livewire\TariffGroup\Show::class)->name('hcm.tariff-groups.show');

// Tarifstufen
Route::get('/tariff-levels', \Platform\Hcm\Livewire\TariffLevel\Index::class)->name('hcm.tariff-levels.index');
Route::get('/tariff-levels/{tariffLevel}', \Platform\Hcm\Livewire\TariffLevel\Show::class)->name('hcm.tariff-levels.show');

// Tarifsätze
Route::get('/tariff-rates', \Platform\Hcm\Livewire\TariffRate\Index::class)->name('hcm.tariff-rates.index');
Route::get('/tariff-rates/{tariffRate}', \Platform\Hcm\Livewire\TariffRate\Show::class)->name('hcm.tariff-rates.show');

// Stellenbezeichnungen & Tätigkeiten
Route::get('/job-titles', JobTitleIndex::class)->name('hcm.job-titles.index');
Route::get('/job-activities', JobActivityIndex::class)->name('hcm.job-activities.index');

// Lohnarten
Route::get('/payroll-types', PayrollTypeIndex::class)->name('hcm.payroll-types.index');
Route::get('/payroll-types/export/csv', [PayrollTypeIndex::class, 'exportCsv'])->name('hcm.payroll-types.export-csv');
Route::get('/payroll-types/export/pdf', [PayrollTypeIndex::class, 'exportPdf'])->name('hcm.payroll-types.export-pdf');

// Personengruppenschlüssel (Lookup)
Route::get('/person-groups', PersonGroupIndex::class)->name('hcm.person-groups.index');

// Versicherungsstatus (Lookup)
Route::get('/insurance-statuses', InsuranceStatusIndex::class)->name('hcm.insurance-statuses.index');

// Rentenarten (Lookup)
Route::get('/pension-types', PensionTypeIndex::class)->name('hcm.pension-types.index');

// Beschäftigungsverhältnisse (Lookup)
Route::get('/employment-relationships', EmploymentRelationshipIndex::class)->name('hcm.employment-relationships.index');

// Umlagearten (Lookup)
Route::get('/levy-types', LevyTypeIndex::class)->name('hcm.levy-types.index');

// Arbeitgeber-spezifische Mitarbeiter
Route::get('/employers/{employer}/employees', EmployeeIndex::class)->name('hcm.employers.employees.index');
Route::get('/employers/{employer}/employees/{employee}', EmployeeShow::class)->name('hcm.employers.employees.show');

// Benefits & Ausgaben (global)
Route::get('/benefits', \Platform\Hcm\Livewire\Benefits\Index::class)->name('hcm.benefits.index');
Route::get('/issues', \Platform\Hcm\Livewire\Issues\Index::class)->name('hcm.issues.index');