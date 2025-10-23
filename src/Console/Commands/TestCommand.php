<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'hcm:test';
    protected $description = 'Test command';

    public function handle(): int
    {
        $this->info('Test command works!');
        return 0;
    }
}
