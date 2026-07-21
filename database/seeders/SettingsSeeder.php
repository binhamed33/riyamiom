<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'office_name', 'value' => 'مكتب حمد الريامي للمحاماة', 'group' => 'office'],
            ['key' => 'office_phone', 'value' => '99331700', 'group' => 'office'],
            ['key' => 'office_email', 'value' => 'info@riyami.om', 'group' => 'office'],
            ['key' => 'office_address', 'value' => 'سلطنة عمان', 'group' => 'office'],
            ['key' => 'office_website', 'value' => 'riyami.om', 'group' => 'office'],
            ['key' => 'office_timezone', 'value' => 'Asia/Muscat', 'group' => 'office'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']]
            );
        }
    }
}
