<div class="h-full bg-white border-r border-gray-200 w-64 flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-gray-200">
        <div class="d-flex items-center gap-3">
            @svg('heroicon-o-users', 'w-6 h-6 text-primary')
            <h2 class="text-lg font-semibold text-gray-900">HCM</h2>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-2">
        <!-- Hauptnavigation -->
        <div class="space-y-1">
            <a href="{{ route('hcm.dashboard') }}" 
               class="d-flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors {{ request()->routeIs('hcm.dashboard') ? 'bg-primary text-on-primary' : 'text-gray-700 hover:bg-gray-100' }}">
                @svg('heroicon-o-home', 'w-5 h-5')
                Dashboard
            </a>
            
            <a href="{{ route('hcm.employers.index') }}" 
               class="d-flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors {{ request()->routeIs('hcm.employers.*') ? 'bg-primary text-on-primary' : 'text-gray-700 hover:bg-gray-100' }}">
                @svg('heroicon-o-building-office', 'w-5 h-5')
                Arbeitgeber
            </a>
            
            <a href="{{ route('hcm.employees.index') }}" 
               class="d-flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors {{ request()->routeIs('hcm.employees.*') ? 'bg-primary text-on-primary' : 'text-gray-700 hover:bg-gray-100' }}">
                @svg('heroicon-o-user-group', 'w-5 h-5')
                Mitarbeiter
            </a>
        </div>

        <!-- Statistiken -->
        <div class="pt-4 border-t border-gray-200">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Übersicht</h3>
            <div class="space-y-2">
                <div class="d-flex justify-between items-center px-3 py-2 text-sm">
                    <span class="text-gray-600">Arbeitgeber</span>
                    <span class="font-medium text-gray-900">{{ $this->stats['total_employers'] }}</span>
                </div>
                <div class="d-flex justify-between items-center px-3 py-2 text-sm">
                    <span class="text-gray-600">Mitarbeiter</span>
                    <span class="font-medium text-gray-900">{{ $this->stats['total_employees'] }}</span>
                </div>
                <div class="d-flex justify-between items-center px-3 py-2 text-sm">
                    <span class="text-gray-600">Aktiv</span>
                    <span class="font-medium text-green-600">{{ $this->stats['active_employees'] }}</span>
                </div>
            </div>
        </div>

        <!-- Neueste Arbeitgeber -->
        @if($this->recentEmployers->count() > 0)
        <div class="pt-4 border-t border-gray-200">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Neueste Arbeitgeber</h3>
            <div class="space-y-1">
                @foreach($this->recentEmployers as $employer)
                    <a href="{{ route('hcm.employers.show', ['employer' => $employer->id]) }}" 
                       class="d-flex items-center gap-2 px-3 py-2 text-sm rounded-md transition-colors text-gray-700 hover:bg-gray-100">
                        @svg('heroicon-o-building-office', 'w-4 h-4 text-gray-400')
                        <span class="truncate">{{ $employer->display_name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Neueste Mitarbeiter -->
        @if($this->recentEmployees->count() > 0)
        <div class="pt-4 border-t border-gray-200">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Neueste Mitarbeiter</h3>
            <div class="space-y-1">
                @foreach($this->recentEmployees as $employee)
                    <a href="{{ route('hcm.employees.show', ['employee' => $employee->id]) }}" 
                       class="d-flex items-center gap-2 px-3 py-2 text-sm rounded-md transition-colors text-gray-700 hover:bg-gray-100">
                        @svg('heroicon-o-user', 'w-4 h-4 text-gray-400')
                        <span class="truncate">{{ $employee->full_name ?? 'Unbekannt' }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </nav>

    <!-- Footer -->
    <div class="p-4 border-t border-gray-200">
        <div class="text-xs text-gray-500 text-center">
            HCM Module v1.0
        </div>
    </div>
</div>