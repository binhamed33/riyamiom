<?php

namespace App\Logging;

use Monolog\Logger;

class CreateDiscordLogger
{
    public function __invoke(array $config): Logger
    {
        $handler = new DiscordHandler(
            $config['url'] ?? '',
            $config
        );

        return new Logger('discord', [$handler]);
    }
}
