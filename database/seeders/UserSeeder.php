<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::make([
            'name' => 'Zyad Yhia',
            'email' => 'test@test.com',
            'password' => bcrypt('123123123'),
            'email_verified_at' => now(),
        ])->save();
    }
}
