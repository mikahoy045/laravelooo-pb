<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'developer',
            'description' => 'A developer role',
        ];
    }

    public function designer()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'designer',
                'description' => 'A designer role',
            ];
        });
    }

    public function manager()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'manager',
                'description' => 'A manager role',
            ];
        });
    }

    public function developer()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'developer',
                'description' => 'A developer role',
            ];
        });
    }
} 