<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Traits\Encryptable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RotateAppKey extends Command
{
    protected $signature = 'app:rotate-key {--generate : Force generate new key even if .env has one}';
    protected $description = 'Rotate APP_KEY and re-encrypt all encrypted data safely';

    public function handle(): int
    {
        if (!app()->runningInConsole()) {
            $this->error('This command can only be run from CLI.');
            return 1;
        }

        $oldKey = config('app.key');
        if (!$oldKey || $oldKey === 'base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=') {
            $this->warn('APP_KEY is not set or is a placeholder.');
            if (!$this->confirm('Continue with rotation anyway?')) {
                return 0;
            }
        }

        $this->info('Step 1: Decrypting all encrypted client data with current key...');
        $clients = Client::all();
        $decrypted = [];

        foreach ($clients as $client) {
            $decrypted[$client->id] = [];
            foreach (Client::getEncryptedFields() as $field) {
                if (!empty($client->$field)) {
                    try {
                        $decrypted[$client->id][$field] = Crypt::decryptString($client->$field);
                    } catch (\Exception $e) {
                        $this->warn("Client #{$client->id}: Could not decrypt {$field} - {$e->getMessage()}");
                        $decrypted[$client->id][$field] = null;
                    }
                }
            }
        }

        $this->info("Step 2: Generating new APP_KEY...");
        $newKey = 'base64:' . base64_encode(random_bytes(32));

        $envPath = app()->environmentFilePath();
        $envContent = file_get_contents($envPath);

        if (preg_match('/^APP_KEY=.*$/m', $envContent, $matches)) {
            $envContent = str_replace($matches[0], "APP_KEY={$newKey}", $envContent);
            file_put_contents($envPath, $envContent);
            $this->info('APP_KEY updated in .env');
        } else {
            $this->error('Could not find APP_KEY in .env file.');
            return 1;
        }

        $this->info('Step 3: Clearing config cache and re-booting with new key...');
        $this->callSilent('config:clear');
        file_put_contents($envPath, $envContent);
        $this->callSilent('config:cache');

        $this->info('Step 4: Re-encrypting data with new key...');
        foreach ($clients as $client) {
            $data = $decrypted[$client->id] ?? [];
            $fieldsToUpdate = [];
            foreach (Client::getEncryptedFields() as $field) {
                if (isset($data[$field]) && $data[$field] !== null) {
                    $fieldsToUpdate[$field] = Crypt::encryptString($data[$field]);
                }
            }
            if (!empty($fieldsToUpdate)) {
                DB::table('clients')->where('id', $client->id)->update($fieldsToUpdate);
            }
        }

        $this->info('Step 5: Logging the rotation...');
        \App\Models\AuditLog::create([
            'user_id' => null,
            'action' => 'app_key_rotated',
            'model_type' => 'System',
            'model_id' => null,
            'old_values' => null,
            'new_values' => json_encode(['clients_re_encrypted' => count($clients)]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'CLI',
        ]);

        $this->info("Done! New APP_KEY set and {$clients->count()} client records re-encrypted.");
        return 0;
    }
}
