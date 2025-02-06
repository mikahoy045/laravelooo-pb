<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['image', 'video']),
            'file_path' => 'media/'.now()->format('Y/m').'/'.$this->faker->uuid.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1000, 5000000),
            'user_id' => \App\Models\User::factory()
        ];
    }
} 