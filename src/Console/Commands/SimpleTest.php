<?php

namespace Platform\Hcm\Console\Commands;

use Illuminate\Console\Command;

class SimpleTest extends Command
{
    protected $signature = 'hcm:simple-test';
    protected $description = 'Simple test command';

    public function handle(): int
    {
        $this->info('Simple test works!');
        return 0;
    }
}
