<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraph,
            'slug' => $this->faker->slug,
            'user_id' => \App\Models\User::factory(),
            'published_at' => now(),
            'banner_path' => 'pages/'.now()->format('Y/m').'/'.$this->faker->uuid.'.jpg',
            'banner_type' => 'image'
        ];
    }
} 