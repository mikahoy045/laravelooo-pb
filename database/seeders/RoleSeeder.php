<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Developer',
                'description' => 'Software Developer role'
            ],
            [
                'name' => 'Designer', 
                'description' => 'UI/UX Designer role'
            ],
            [
                'name' => 'Manager',
                'description' => 'Project Management role'
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
} 