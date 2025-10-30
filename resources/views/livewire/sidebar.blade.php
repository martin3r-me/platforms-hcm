{{-- resources/views/vendor/hcm/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        HCM
    </div>
    
    {{-- Abschnitt: Dashboard --}}
    <x-ui-sidebar-list label="Übersicht">
        <x-ui-sidebar-item :href="route('hcm.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Organisation --}}
    <x-ui-sidebar-list label="Organisation">
        <x-ui-sidebar-item :href="route('hcm.employers.index')">
            @svg('heroicon-o-building-office', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Arbeitgeber</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.employees.index')">
            @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Mitarbeiter</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Tarife --}}
    <x-ui-sidebar-list label="Tarife">
        <x-ui-sidebar-item :href="route('hcm.tariff-overview')">
            @svg('heroicon-o-scale', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tarif-Übersicht</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.tariff-agreements.index')">
            @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tarifverträge</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.tariff-groups.index')">
            @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tarifgruppen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.tariff-levels.index')">
            @svg('heroicon-o-bars-3', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tarifstufen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.tariff-rates.index')">
            @svg('heroicon-o-banknotes', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tarifsätze</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Stellen & Tätigkeiten --}}
    <x-ui-sidebar-list label="Stellen & Tätigkeiten">
        <x-ui-sidebar-item :href="route('hcm.job-titles.index')">
            @svg('heroicon-o-briefcase', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Stellenbezeichnungen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.job-activities.index')">
            @svg('heroicon-o-clipboard-document', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tätigkeiten</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Lohn & Soziales --}}
    <x-ui-sidebar-list label="Lohn & Soziales">
        <x-ui-sidebar-item :href="route('hcm.payroll-types.index')">
            @svg('heroicon-o-currency-euro', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Lohnarten</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.health-insurance-companies.index')">
            @svg('heroicon-o-heart', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Krankenkassen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.person-groups.index')">
            @svg('heroicon-o-users', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Personengruppen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.insurance-statuses.index')">
            @svg('heroicon-o-shield-check', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Versicherungsstatus</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.pension-types.index')">
            @svg('heroicon-o-academic-cap', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Rentenarten</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.employment-relationships.index')">
            @svg('heroicon-o-briefcase', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Beschäftigungsverhältnisse</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.levy-types.index')">
            @svg('heroicon-o-banknotes', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Umlagearten</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('hcm.tariffs.index')">
            @svg('heroicon-o-scale', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Tarifklassen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            {{-- Dashboard --}}
            <a href="{{ route('hcm.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Dashboard">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            
            {{-- Organisation --}}
            <a href="{{ route('hcm.employers.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Arbeitgeber">
                @svg('heroicon-o-building-office', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.employees.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Mitarbeiter">
                @svg('heroicon-o-user-group', 'w-5 h-5')
            </a>
            
            {{-- Tarife --}}
            <a href="{{ route('hcm.tariff-agreements.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Tarifverträge">
                @svg('heroicon-o-document-text', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.tariff-groups.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Tarifgruppen">
                @svg('heroicon-o-squares-2x2', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.tariff-levels.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Tarifstufen">
                @svg('heroicon-o-bars-3', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.tariff-rates.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Tarifsätze">
                @svg('heroicon-o-banknotes', 'w-5 h-5')
            </a>
            
            {{-- Stellen & Tätigkeiten --}}
            <a href="{{ route('hcm.job-titles.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Stellenbezeichnungen">
                @svg('heroicon-o-briefcase', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.job-activities.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Tätigkeiten">
                @svg('heroicon-o-clipboard-document', 'w-5 h-5')
            </a>
            
            {{-- Lohn & Soziales --}}
            <a href="{{ route('hcm.payroll-types.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Lohnarten">
                @svg('heroicon-o-currency-euro', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.health-insurance-companies.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Krankenkassen">
                @svg('heroicon-o-heart', 'w-5 h-5')
            </a>
            <a href="{{ route('hcm.tariffs.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Tarifklassen">
                @svg('heroicon-o-scale', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- Abschnitt: Arbeitgeber --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            @if($this->recentEmployers->count() > 0)
                <x-ui-sidebar-list label="Neueste Arbeitgeber">
                    @foreach($this->recentEmployers as $employer)
                        <x-ui-sidebar-item :href="route('hcm.employers.show', ['employer' => $employer->id])">
                            @svg('heroicon-o-building-office', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <span class="truncate text-sm ml-2">{{ $employer->display_name }}</span>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            @else
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">Keine Arbeitgeber</div>
            @endif
        </div>
    </div>

    {{-- Abschnitt: Mitarbeiter --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            @if($this->recentEmployees->count() > 0)
                <x-ui-sidebar-list label="Neueste Mitarbeiter">
                    @foreach($this->recentEmployees as $employee)
                        <x-ui-sidebar-item :href="route('hcm.employees.show', ['employee' => $employee->id])">
                            @svg('heroicon-o-user', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <span class="truncate text-sm ml-2">{{ $employee->full_name ?? 'Unbekannt' }}</span>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            @else
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">Keine Mitarbeiter</div>
            @endif
        </div>
    </div>

    {{-- Statistiken --}}
    <div x-show="!collapsed" class="mt-4 p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
        <div class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Übersicht</div>
        <div class="space-y-2">
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Arbeitgeber</span>
                <span class="font-medium text-[var(--ui-secondary)]">{{ $this->stats['total_employers'] }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Mitarbeiter</span>
                <span class="font-medium text-[var(--ui-secondary)]">{{ $this->stats['total_employees'] }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Aktiv</span>
                <span class="font-medium text-green-600">{{ $this->stats['active_employees'] }}</span>
            </div>
        </div>
    </div>
</div>