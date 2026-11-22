<?php

namespace Database\Seeders;

use App\Enums\Language;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo data only. Admins sign in with Google (see ADMIN_EMAILS).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::adminEmails() as $email) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => strstr($email, '@', true) ?: $email,
                    'password' => null,
                    'email_verified_at' => now(),
                ],
            );
        }

        $english = [
            'Describe a moment when you felt you truly belonged somewhere.',
            'What is one thing you would change about how your school teaches languages?',
            'Write about a person who changed the way you think.',
            'What does a good teacher do that others do not?',
            'Describe a place that feels like home to you.',
        ];
        $hebrew = [
            'ספרו על רגע שבו הרגשתם שייכות אמיתית.',
            'מה הייתם משנים בדרך שבה מלמדים אנגלית?',
            'כתבו על אדם ששינה את הדרך שבה אתם חושבים.',
            'מה עושה מורה טוב שאחרים לא עושים?',
            'תארו מקום שמרגיש לכם כמו בית.',
        ];

        foreach (['Warm-up', 'Memories', 'Looking ahead'] as $i => $title) {
            $stage = Stage::create(['title' => $title]);
            foreach (range(0, 4) as $n) {
                $stage->prompts()->create(['body' => "({$title}) ".$english[$n], 'language' => Language::English]);
                $stage->prompts()->create(['body' => "({$title}) ".$hebrew[$n], 'language' => Language::Hebrew]);
            }
        }

        foreach ([
            'Test Participant' => 'test.participant@talmaisrael.com',
            'Noa Levi' => 'noa.levi@talmaisrael.com',
            'Sam Cohen' => 'sam.cohen@talmaisrael.com',
        ] as $name => $email) {
            Participant::create(['name' => $name, 'email' => $email]);
        }
    }
}
