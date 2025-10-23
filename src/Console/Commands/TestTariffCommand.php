<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;

class TestTariffCommand extends Command
{
    protected $signature = 'hcm:test-tariff';
    protected $description = 'Test tariff command';

    public function handle(): int
    {
        $this->info('Test tariff command works!');
        return 0;
    }
}
