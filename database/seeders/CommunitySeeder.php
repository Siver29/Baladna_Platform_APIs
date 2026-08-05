<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommunitySeeder extends Seeder
{
    /**
     * Seed a few community posts and comments.
     */
    public function run(): void
    {
        $citizen = User::where('email', 'citizen@baladna.test')->first() ?? User::factory()->create();
        $others = User::factory()->count(3)->create();

        $posts = [
            'Waste collection problem in our area',
            'Streetlight broken on the main road',
            'Water leak reported but not yet fixed',
            'Pothole on the road near the school',
            'Suggest collecting waste twice a week',
        ];

        foreach ($posts as $index => $title) {
            $post = Post::create([
                'user_id' => $citizen->id,
                'area_id' => null,
                'title' => $title,
                'content' => "Discussion about: {$title}. Share your experience and updates here.",
            ]);

            foreach ($others as $key => $other) {
                if ($key % 2 === 0) {
                    Comment::create([
                        'post_id' => $post->id,
                        'user_id' => $other->id,
                        'content' => "I have the same issue in my neighbourhood. ({$index})",
                    ]);
                }
            }
        }
    }
}
