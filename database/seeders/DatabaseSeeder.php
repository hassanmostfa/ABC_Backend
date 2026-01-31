<?php

namespace Database\Seeders;

use App\Models\Admin;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions first
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            CharitySeeder::class,
            KuwaitGovernorateSeeder::class,
            KuwaitAreaSeeder::class,
            SettingSeeder::class,
            FaqSeeder::class,
        ]);

        // Create a test admin with Super Admin role
        $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->first();
        
        Admin::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'phone' => '01717171717',
            'role_id' => $superAdminRole->id,
        ]);
    }
}
