<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MonitorServer extends Command
{
    protected $signature = 'monitor:server';
    protected $description = 'Alias for discord:status — send server status to Discord';

    public function handle(): int
    {
        return $this->call(DiscordStatus::class);
    }
}
