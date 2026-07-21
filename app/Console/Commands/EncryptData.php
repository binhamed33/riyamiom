<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EncryptData extends Command
{
    protected $signature = 'app:encrypt-data {--force : Re-encrypt already encrypted data}';
    protected $description = 'Encrypt sensitive data in the database';

    public function handle(): int
    {
        $force = $this->option('force');
        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        // Encrypt Client data
        $this->info('Encrypting Client data...');
        $clientFields = ['phone', 'email', 'address', 'national_id', 'company_name'];
        $clients = Client::all();

        foreach ($clients as $client) {
            foreach ($clientFields as $field) {
                $value = $client->getAttribute($field);
                if ($value && ($force || !str_starts_with($value, 'enc:'))) {
                    try {
                        $encryptedValue = str_starts_with($value, 'enc:') && $force
                            ? Crypt::decryptString(substr($value, 4))
                            : $value;
                        $client->setAttribute($field, 'enc:' . Crypt::encryptString($encryptedValue));
                        $encrypted++;
                    } catch (\Exception $e) {
                        $this->error("  Error encrypting client {$client->id} {$field}: {$e->getMessage()}");
                        $errors++;
                    }
                } else {
                    $skipped++;
                }
            }
            if ($client->isDirty()) {
                $client->saveQuietly();
            }
        }

        // Encrypt LegalCase data
        $this->info('Encrypting LegalCase data...');
        $caseFields = ['description', 'opponent'];
        $cases = LegalCase::withTrashed()->get();

        foreach ($cases as $case) {
            foreach ($caseFields as $field) {
                $value = $case->getAttribute($field);
                if ($value && ($force || !str_starts_with($value, 'enc:'))) {
                    try {
                        $encryptedValue = str_starts_with($value, 'enc:') && $force
                            ? Crypt::decryptString(substr($value, 4))
                            : $value;
                        $case->setAttribute($field, 'enc:' . Crypt::encryptString($encryptedValue));
                        $encrypted++;
                    } catch (\Exception $e) {
                        $this->error("  Error encrypting case {$case->id} {$field}: {$e->getMessage()}");
                        $errors++;
                    }
                } else {
                    $skipped++;
                }
            }
            if ($case->isDirty()) {
                $case->saveQuietly();
            }
        }

        // Encrypt User phone
        $this->info('Encrypting User phone data...');
        $users = User::all();

        foreach ($users as $user) {
            $value = $user->phone;
            if ($value && ($force || !str_starts_with($value, 'enc:'))) {
                try {
                    $encryptedValue = str_starts_with($value, 'enc:') && $force
                        ? Crypt::decryptString(substr($value, 4))
                        : $value;
                    $user->phone = 'enc:' . Crypt::encryptString($encryptedValue);
                    $encrypted++;
                } catch (\Exception $e) {
                    $this->error("  Error encrypting user {$user->id} phone: {$e->getMessage()}");
                    $errors++;
                }
            } else {
                $skipped++;
            }
            if ($user->isDirty('phone')) {
                $user->saveQuietly();
            }
        }

        $this->info("Done! Encrypted: $encrypted, Skipped: $skipped, Errors: $errors");
        return $errors > 0 ? 1 : 0;
    }
}
