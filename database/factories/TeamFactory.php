<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'role_id' => 1,
            'bio' => $this->faker->paragraphs(2, true),
            'profile_picture' => 'teams/'.now()->format('Y/m').'/'.$this->faker->uuid.'.jpg',
            'user_id' => \App\Models\User::factory()
        ];
    }
} 