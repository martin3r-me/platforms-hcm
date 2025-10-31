<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Vertrag #{{ $contract->id }}" icon="heroicon-o-document-text">
            <div class="flex items-center gap-2">
                <a href="{{ route('hcm.employees.show', $contract->employee) }}" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]" wire:navigate>
                    ← {{ $contract->employee->full_name ?? $contract->employee->employee_number }}
                </a>
                <x-ui-button variant="secondary" wire:click="toggleEdit">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    {{ $editMode ? 'Abbrechen' : 'Bearbeiten' }}
                </x-ui-button>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Modern Header --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight">
                        Vertrag #{{ $contract->id }}
                    </h1>
                    <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)]">
                        <span class="flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            {{ $contract->employee->full_name ?? $contract->employee->employee_number }}
                        </span>
                        @if($contract->start_date)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-calendar', 'w-4 h-4')
                                Start: {{ $contract->start_date->format('d.m.Y') }}
                            </span>
                        @endif
                        @if($contract->end_date)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-calendar', 'w-4 h-4')
                                Ende: {{ $contract->end_date->format('d.m.Y') }}
                            </span>
                        @endif
                        @if($contract->cost_center)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-building-office', 'w-4 h-4')
                                {{ $contract->cost_center }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                        @if($contract->is_minimum_wage) bg-yellow-100 text-yellow-800
                        @elseif($contract->is_above_tariff) bg-purple-100 text-purple-800
                        @elseif($contract->tariffGroup) bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ $contract->getSalaryTypeDescription() }}
                    </span>
                    @if($contract->is_active)
                        <x-ui-badge variant="success" size="lg">Aktiv</x-ui-badge>
                    @else
                        <x-ui-badge variant="danger" size="lg">Inaktiv</x-ui-badge>
                    @endif
                </div>
            </div>
        </div>

        {{-- Vergütungs-Übersicht --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-center gap-2 mb-6">
                @svg('heroicon-o-currency-euro', 'w-6 h-6 text-green-600')
                <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Vergütung</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                {{-- Effektives Gehalt --}}
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="text-sm font-medium text-green-700 mb-2">Gesamtgehalt</div>
                    <div class="text-3xl font-bold text-green-600">
                        {{ number_format((float)$contract->getEffectiveMonthlySalary(), 2, ',', '.') }} €
                    </div>
                    <div class="text-sm text-green-600">effektiv/Monat</div>
                </div>

                {{-- Tarifsatz --}}
                @if($contract->getCurrentTariffRate())
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <div class="text-sm font-medium text-blue-700 mb-2">Tarifsatz</div>
                        <div class="text-2xl font-bold text-blue-600">
                            {{ number_format((float)$contract->getCurrentTariffRate()->amount, 2, ',', '.') }} €
                        </div>
                        <div class="text-sm text-blue-600">Grundgehalt</div>
                    </div>
                @endif

                {{-- Übertariflich --}}
                @if($contract->is_above_tariff && $contract->above_tariff_amount)
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
                        <div class="text-sm font-medium text-purple-700 mb-2">Übertariflich</div>
                        <div class="text-2xl font-bold text-purple-600">
                            +{{ number_format((float)$contract->above_tariff_amount, 2, ',', '.') }} €
                        </div>
                        <div class="text-sm text-purple-600">Zulage</div>
                    </div>
                @endif
            </div>

            {{-- Tarif-Details --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Tarifliche Zuordnung --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Tarifliche Zuordnung</h3>
                    
                    @if($editMode)
                        <div class="space-y-4">
                            <x-ui-input-select 
                                name="tariff_group_id" 
                                label="Tarifgruppe"
                                wire:model.live="tariff_group_id"
                                :options="$this->tariffGroups->pluck('name', 'id')->toArray()"
                                placeholder="Tarifgruppe wählen..."
                            />
                            
                            <x-ui-input-select 
                                name="tariff_level_id" 
                                label="Tarifstufe"
                                wire:model="tariff_level_id"
                                :options="$this->tariffLevels->pluck('name', 'id')->toArray()"
                                placeholder="Tarifstufe wählen..."
                            />
                        </div>
                    @else
                        <div class="space-y-2">
                            @if($contract->tariffGroup)
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $contract->tariffGroup->code }}
                                    </span>
                                    <span class="text-sm text-[var(--ui-muted)]">{{ $contract->tariffGroup->name }}</span>
                                </div>
                            @endif
                            @if($contract->tariffLevel)
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Stufe {{ $contract->tariffLevel->code }}
                                    </span>
                                    <span class="text-sm text-[var(--ui-muted)]">{{ $contract->tariffLevel->name }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Progression --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Progression</h3>
                    @if($contract->next_tariff_level_date)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="text-sm font-medium text-blue-700 mb-1">Nächste Progression</div>
                            <div class="text-lg font-bold text-blue-600">
                                {{ $contract->next_tariff_level_date->format('d.m.Y') }}
                            </div>
                            <div class="text-xs text-blue-600">
                                {{ $contract->next_tariff_level_date->diffForHumans() }}
                            </div>
                        </div>
                    @else
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="text-sm text-gray-600">Endstufe erreicht</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sozialversicherung & Steuergrundlagen --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-center gap-2 mb-6">
                @svg('heroicon-o-shield-check', 'w-6 h-6 text-teal-600')
                <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Sozialversicherung & Steuer</h2>
            </div>

            @if($editMode)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-ui-input-select 
                        name="primary_job_activity_id" 
                        label="Tätigkeitsschlüssel (Stellen 1–5)"
                        wire:model="primary_job_activity_id"
                        :options="$this->jobActivities->pluck('name','id')->map(fn($n,$id)=>$id)->toArray()"
                        placeholder="Tätigkeit auswählen..."
                    />

                    <x-ui-input-select 
                        name="insurance_status_id" 
                        label="Versicherungsstatus"
                        wire:model="insurance_status_id"
                        :options="$this->insuranceStatuses->pluck('name', 'id')->toArray()"
                        placeholder="Status wählen..."
                    />

                    <x-ui-input-select 
                        name="pension_type_id" 
                        label="Rentenart"
                        wire:model="pension_type_id"
                        :options="$this->pensionTypes->pluck('name', 'id')->toArray()"
                        placeholder="Rentenart wählen..."
                    />

                    <x-ui-input-select 
                        name="employment_relationship_id" 
                        label="Beschäftigungsverhältnis"
                        wire:model="employment_relationship_id"
                        :options="$this->employmentRelationships->pluck('name', 'id')->toArray()"
                        placeholder="Verhältnis wählen..."
                    />

                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)] mb-2">Umlagearten</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @foreach($this->levyTypes as $levy)
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" class="form-checkbox" 
                                           wire:model="selected_levy_type_ids" 
                                           value="{{ $levy->id }}">
                                    <span>{{ $levy->code }} – {{ $levy->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <x-ui-input-select 
                        name="schooling_level" 
                        label="Höchster Schulabschluss (Stelle 6)"
                        wire:model="schooling_level"
                        :options="$this->schoolingLevelOptions"
                        placeholder="Auswahl..."
                    />

                    <x-ui-input-select 
                        name="vocational_training_level" 
                        label="Höchster beruflicher Abschluss (Stelle 7)"
                        wire:model="vocational_training_level"
                        :options="$this->vocationalTrainingLevelOptions"
                        placeholder="Auswahl..."
                    />

                    <x-ui-input-checkbox 
                        model="is_temp_agency"
                        name="is_temp_agency" 
                        label="Arbeitnehmerüberlassung (Stelle 8)"
                        wire:model="is_temp_agency"
                    />

                    <x-ui-input-select 
                        name="contract_form" 
                        label="Vertragsform (Stelle 9)"
                        wire:model="contract_form"
                        :options="$this->contractFormOptions"
                        placeholder="Auswahl..."
                    />
                </div>
            @else
                {{-- Tätigkeitsschlüssel Komplettansicht --}}
                @php
                    $activityKeyParts = [];
                    if ($contract->primaryJobActivity) {
                        $activityKeyParts[0] = $contract->primaryJobActivity->code;
                    }
                    if ($contract->schooling_level) {
                        $activityKeyParts[1] = $contract->schooling_level;
                    }
                    if ($contract->vocational_training_level) {
                        $activityKeyParts[2] = $contract->vocational_training_level;
                    }
                    if ($contract->is_temp_agency) {
                        $activityKeyParts[3] = '1';
                    }
                    if ($contract->contract_form) {
                        $activityKeyParts[4] = $contract->contract_form;
                    }
                    $fullActivityKey = implode('', $activityKeyParts);
                @endphp
                
                @if(count($activityKeyParts) > 0)
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            @svg('heroicon-o-key', 'w-5 h-5 text-blue-600')
                            <h3 class="text-lg font-semibold text-blue-900">Tätigkeitsschlüssel (komplett)</h3>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="text-2xl font-mono font-bold text-blue-700 tracking-wider">{{ $fullActivityKey }}</div>
                            <div class="text-xs text-blue-600">(9-stellig)</div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                            @if($contract->primaryJobActivity)
                                <div class="bg-white rounded p-2">
                                    <div class="font-semibold text-blue-700">Stellen 1-5</div>
                                    <div class="text-blue-600">{{ $contract->primaryJobActivity->code }}</div>
                                    <div class="text-[var(--ui-muted)] mt-1">{{ $contract->primaryJobActivity->name }}</div>
                                </div>
                            @endif
                            @if($contract->schooling_level)
                                <div class="bg-white rounded p-2">
                                    <div class="font-semibold text-blue-700">Stelle 6</div>
                                    <div class="text-blue-600">{{ $contract->schooling_level }}</div>
                                    <div class="text-[var(--ui-muted)] mt-1">{{ $this->schoolingLevelOptions[$contract->schooling_level] ?? '—' }}</div>
                                </div>
                            @endif
                            @if($contract->vocational_training_level)
                                <div class="bg-white rounded p-2">
                                    <div class="font-semibold text-blue-700">Stelle 7</div>
                                    <div class="text-blue-600">{{ $contract->vocational_training_level }}</div>
                                    <div class="text-[var(--ui-muted)] mt-1">{{ $this->vocationalTrainingLevelOptions[$contract->vocational_training_level] ?? '—' }}</div>
                                </div>
                            @endif
                            <div class="bg-white rounded p-2">
                                <div class="font-semibold text-blue-700">Stelle 8</div>
                                <div class="text-blue-600">{{ $contract->is_temp_agency ? '1' : '0' }}</div>
                                <div class="text-[var(--ui-muted)] mt-1">{{ $contract->is_temp_agency ? 'Leiharbeit' : 'Normal' }}</div>
                            </div>
                            @if($contract->contract_form)
                                <div class="bg-white rounded p-2">
                                    <div class="font-semibold text-blue-700">Stelle 9</div>
                                    <div class="text-blue-600">{{ $contract->contract_form }}</div>
                                    <div class="text-[var(--ui-muted)] mt-1">{{ $this->contractFormOptions[$contract->contract_form] ?? '—' }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Tätigkeiten & Stellen</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if($contract->jobTitles && $contract->jobTitles->count() > 0)
                                <div>
                                    <div class="text-sm font-medium text-[var(--ui-secondary)] mb-2">Stellenbezeichnungen</div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($contract->jobTitles as $title)
                                            <x-ui-badge variant="secondary" size="sm">{{ $title->name }}</x-ui-badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if($contract->jobActivities && $contract->jobActivities->count() > 0)
                                <div>
                                    <div class="text-sm font-medium text-[var(--ui-secondary)] mb-2">Tätigkeiten</div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($contract->jobActivities as $activity)
                                            <x-ui-badge variant="info" size="sm">
                                                {{ $activity->code ?? '' }} {{ $activity->name }}
                                            </x-ui-badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Versicherungsstatus</div>
                        <div class="text-sm text-[var(--ui-muted)]">
                            {{ optional($contract->insuranceStatus)->name ?? '—' }}
                            @if($contract->insuranceStatus && $contract->insuranceStatus->code)
                                <span class="text-xs">({{ $contract->insuranceStatus->code }})</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Rentenart</div>
                        <div class="text-sm text-[var(--ui-muted)]">
                            {{ optional($contract->pensionType)->name ?? '—' }}
                            @if($contract->pensionType && $contract->pensionType->code)
                                <span class="text-xs">({{ $contract->pensionType->code }})</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Beschäftigungsverhältnis</div>
                        <div class="text-sm text-[var(--ui-muted)]">
                            {{ optional($contract->employmentRelationship)->name ?? '—' }}
                            @if($contract->employmentRelationship && $contract->employmentRelationship->code)
                                <span class="text-xs">({{ $contract->employmentRelationship->code }})</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Umlagearten</div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($contract->levyTypes as $levy)
                                <x-ui-badge variant="secondary" size="sm">{{ $levy->code }} – {{ $levy->name }}</x-ui-badge>
                            @empty
                                <span class="text-sm text-[var(--ui-muted)]">—</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Übertarifliche Bezahlung --}}
        @if($contract->is_above_tariff || $editMode)
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-center gap-2 mb-6">
                    @svg('heroicon-o-arrow-trending-up', 'w-6 h-6 text-purple-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Übertarifliche Bezahlung</h2>
                </div>
                
                @if($editMode)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-ui-input-checkbox 
                            model="is_above_tariff"
                            name="is_above_tariff" 
                            label="Übertariflich bezahlen"
                            wire:model="is_above_tariff"
                        />
                        
                        @if($is_above_tariff)
                            <x-ui-input-text 
                                name="above_tariff_amount" 
                                label="Übertariflicher Betrag (€)"
                                wire:model="above_tariff_amount"
                                type="number"
                                step="0.01"
                            />
                            
                            <x-ui-input-textarea 
                                name="above_tariff_reason" 
                                label="Grund"
                                wire:model="above_tariff_reason"
                                rows="3"
                            />
                            
                            <x-ui-input-text 
                                name="above_tariff_start_date" 
                                label="Gültig ab"
                                wire:model="above_tariff_start_date"
                                type="date"
                            />
                        @endif
                    </div>
                @else
                    @if($contract->is_above_tariff && $contract->above_tariff_amount)
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
                            <div class="text-2xl font-bold text-purple-600 mb-2">
                                +{{ number_format((float)$contract->above_tariff_amount, 2, ',', '.') }} €
                            </div>
                            @if($contract->above_tariff_reason)
                                <div class="text-sm text-purple-700 mb-2">{{ $contract->above_tariff_reason }}</div>
                            @endif
                            @if($contract->above_tariff_start_date)
                                <div class="text-xs text-purple-600">
                                    Gültig ab: {{ $contract->above_tariff_start_date->format('d.m.Y') }}
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Mindestlohn --}}
        @if($contract->is_minimum_wage || $editMode)
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-center gap-2 mb-6">
                    @svg('heroicon-o-clock', 'w-6 h-6 text-yellow-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Mindestlohn</h2>
                </div>
                
                @if($editMode)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-ui-input-checkbox 
                            model="is_minimum_wage"
                            name="is_minimum_wage" 
                            label="Mindestlohn bezahlen"
                            wire:model="is_minimum_wage"
                        />
                        
                        @if($is_minimum_wage)
                            <x-ui-input-text 
                                name="minimum_wage_hourly_rate" 
                                label="Stundenlohn (€)"
                                wire:model="minimum_wage_hourly_rate"
                                type="number"
                                step="0.01"
                            />
                            
                            <x-ui-input-text 
                                name="minimum_wage_monthly_hours" 
                                label="Monatsstunden"
                                wire:model="minimum_wage_monthly_hours"
                                type="number"
                                step="0.1"
                            />
                            
                            <x-ui-input-textarea 
                                name="minimum_wage_notes" 
                                label="Notizen"
                                wire:model="minimum_wage_notes"
                                rows="3"
                            />
                        @endif
                    </div>
                @else
                    @if($contract->is_minimum_wage)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm font-medium text-yellow-700 mb-1">Stundenlohn</div>
                                    <div class="text-xl font-bold text-yellow-600">
                                        {{ number_format((float)$contract->minimum_wage_hourly_rate, 2, ',', '.') }} €/h
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-yellow-700 mb-1">Monatsstunden</div>
                                    <div class="text-xl font-bold text-yellow-600">
                                        {{ $contract->minimum_wage_monthly_hours }}h
                                    </div>
                                </div>
                            </div>
                            @if($contract->minimum_wage_notes)
                                <div class="mt-4 text-sm text-yellow-700">{{ $contract->minimum_wage_notes }}</div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Arbeitszeit & Urlaub --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-center gap-2 mb-6">
                @svg('heroicon-o-clock', 'w-6 h-6 text-indigo-600')
                <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Arbeitszeit & Urlaub</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Arbeitszeit --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Arbeitszeit</h3>
                    <div class="space-y-3">
                        @if($contract->hours_per_month)
                            <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                                <span class="text-sm font-medium text-indigo-700">Monatsstunden</span>
                                <span class="text-lg font-bold text-indigo-600">{{ $contract->hours_per_month }}h</span>
                            </div>
                        @endif
                        @if($contract->work_days_per_week)
                            <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                                <span class="text-sm font-medium text-indigo-700">Tage/Woche</span>
                                <span class="text-lg font-bold text-indigo-600">{{ $contract->work_days_per_week }}</span>
                            </div>
                        @endif
                        @if($contract->wage_base_type)
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="text-xs text-[var(--ui-muted)] mb-1">Lohngrundart</div>
                                <div class="text-sm font-medium">{{ $contract->wage_base_type }}</div>
                            </div>
                        @endif
                    </div>
                </div>
                
                {{-- Urlaub --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Urlaub</h3>
                    <div class="space-y-3">
                        @if($contract->vacation_entitlement !== null)
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <span class="text-sm font-medium text-green-700">Ansprüche</span>
                                <span class="text-lg font-bold text-green-600">{{ $contract->vacation_entitlement }} Tage</span>
                            </div>
                        @endif
                        @if($contract->vacation_taken !== null)
                            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                                <span class="text-sm font-medium text-yellow-700">Genommen</span>
                                <span class="text-lg font-bold text-yellow-600">{{ $contract->vacation_taken }} Tage</span>
                            </div>
                        @endif
                        @if($contract->vacation_entitlement !== null && $contract->vacation_taken !== null)
                            @php
                                $remaining = $contract->vacation_entitlement - $contract->vacation_taken;
                                $percentage = $contract->vacation_entitlement > 0 
                                    ? ($contract->vacation_taken / $contract->vacation_entitlement) * 100 
                                    : 0;
                            @endphp
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-blue-700">Verbleibend</span>
                                    <span class="text-lg font-bold text-blue-600">{{ $remaining }} Tage</span>
                                </div>
                                <div class="w-full bg-blue-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all" style="width: {{ min($percentage, 100) }}%"></div>
                                </div>
                                <div class="text-xs text-blue-600 mt-1">{{ number_format($percentage, 1) }}% genommen</div>
                            </div>
                        @endif
                        @if($contract->vacation_prev_year !== null)
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="text-xs text-[var(--ui-muted)] mb-1">Vorjahr</div>
                                <div class="text-sm font-medium">{{ $contract->vacation_prev_year }} Tage</div>
                            </div>
                        @endif
                        @if($contract->vacation_expiry_date)
                            <div class="p-3 bg-orange-50 rounded-lg">
                                <div class="text-xs text-orange-700 mb-1">Verfällt am</div>
                                <div class="text-sm font-medium text-orange-600">{{ $contract->vacation_expiry_date->format('d.m.Y') }}</div>
                            </div>
                        @endif
                    </div>
                </div>
                
                {{-- Zusatzinfos --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Weitere Informationen</h3>
                    <div class="space-y-3">
                        @if($contract->probation_end_date)
                            <div class="p-3 bg-purple-50 rounded-lg">
                                <div class="text-xs text-purple-700 mb-1">Probezeit bis</div>
                                <div class="text-sm font-medium text-purple-600">{{ $contract->probation_end_date->format('d.m.Y') }}</div>
                            </div>
                        @endif
                        @if($contract->is_fixed_term)
                            <div class="p-3 bg-red-50 rounded-lg">
                                <div class="text-xs text-red-700 mb-1">Befristet bis</div>
                                <div class="text-sm font-medium text-red-600">
                                    {{ $contract->fixed_term_end_date ? $contract->fixed_term_end_date->format('d.m.Y') : 'Unbekannt' }}
                                </div>
                            </div>
                        @endif
                        @if($contract->company_car_enabled)
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-truck', 'w-5 h-5 text-blue-600')
                                    <span class="text-sm font-medium text-blue-700">Dienstwagen</span>
                                </div>
                            </div>
                        @endif
                        @if($contract->additional_vacation_disability)
                            <div class="p-3 bg-teal-50 rounded-lg">
                                <div class="text-xs text-teal-700 mb-1">Zusatzurlaub (Schwerbehinderung)</div>
                                <div class="text-sm font-medium text-teal-600">{{ $contract->additional_vacation_disability }} Tage</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Progression Historie --}}
        @if($contract->tariffProgressions->count() > 0)
            <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
                <div class="flex items-center gap-2 mb-6">
                    @svg('heroicon-o-chart-bar', 'w-6 h-6 text-blue-600')
                    <h2 class="text-xl font-bold text-[var(--ui-secondary)]">Progression Historie</h2>
                </div>
                
                <div class="space-y-4">
                    @foreach($contract->tariffProgressions as $progression)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                    @svg('heroicon-o-arrow-trending-up', 'w-5 h-5')
                                </div>
                                <div>
                                    <div class="font-medium text-[var(--ui-secondary)]">
                                        @if($progression->fromTariffLevel && $progression->toTariffLevel)
                                            {{ $progression->fromTariffLevel->name }} → {{ $progression->toTariffLevel->name }}
                                        @else
                                            Progression zu {{ $progression->toTariffLevel->name }}
                                        @endif
                                    </div>
                                    <div class="text-sm text-[var(--ui-muted)]">
                                        {{ $progression->progression_date->format('d.m.Y') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if($progression->progression_reason === 'automatic') bg-green-100 text-green-800
                                    @elseif($progression->progression_reason === 'manual') bg-blue-100 text-blue-800
                                    @elseif($progression->progression_reason === 'promotion') bg-purple-100 text-purple-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ $progression->progression_reason_label }}
                                </span>
                                @if($progression->progression_notes)
                                    <div class="text-xs text-[var(--ui-muted)] mt-1">{{ $progression->progression_notes }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Save Button --}}
        @if($editMode)
            <div class="flex justify-end gap-3">
                <x-ui-button variant="secondary" wire:click="toggleEdit">
                    Abbrechen
                </x-ui-button>
                <x-ui-button variant="primary" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    Speichern
                </x-ui-button>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
