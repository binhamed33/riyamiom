<?php

namespace App\Exceptions;

use App\Services\DiscordService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class DiscordExceptionHandler
{
    public function register(): void
    {
    }

    public function report(Throwable $e): void
    {
        if (app()->bound('exceptions')) {
            app('exceptions')->report($e);
        }

        if ($this->shouldReportToDiscord($e)) {
            try {
                $discord = app(DiscordService::class);
                $discord->logException($e);
            } catch (\Throwable $t) {
                \Illuminate\Support\Facades\Log::error('Failed to send exception to Discord: ' . $t->getMessage());
            }
        }
    }

    private function shouldReportToDiscord(Throwable $e): bool
    {
        $ignored = [
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
        ];

        foreach ($ignored as $ignoredClass) {
            if ($e instanceof $ignoredClass) {
                return false;
            }
        }

        return !empty(config('discord.log_webhook_url'));
    }
}
