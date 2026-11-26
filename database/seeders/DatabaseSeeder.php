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
            ['q' => 'Describe a moment when you felt you truly belonged somewhere.', 'd' => 'Who was there? What made it feel that way?'],
            ['q' => 'What is one thing you would change about how your school teaches languages?', 'd' => 'Think about class time, materials, or how students practice.'],
            ['q' => 'Write about a person who changed the way you think.', 'd' => 'What did they say or do? How are you different now?'],
            ['q' => 'What does a good teacher do that others do not?', 'd' => 'Give a concrete example from your experience.'],
            ['q' => 'Describe a place that feels like home to you.', 'd' => 'Use sensory details: sights, sounds, smells.'],
        ];
        $hebrew = [
            ['q' => 'ספרו על רגע שבו הרגשתם שייכות אמיתית.', 'd' => 'מי היה שם? מה גרם לתחושה הזאת?'],
            ['q' => 'מה הייתם משנים בדרך שבה מלמדים אנגלית?', 'd' => 'חשבו על שיעורים, חומרים או תרגול.'],
            ['q' => 'כתבו על אדם ששינה את הדרך שבה אתם חושבים.', 'd' => 'מה הוא או היא אמרו או עשו? איך אתם אחרים היום?'],
            ['q' => 'מה עושה מורה טוב שאחרים לא עושים?', 'd' => 'תנו דוגמה קונקרטית מהניסיון שלכם.'],
            ['q' => 'תארו מקום שמרגיש לכם כמו בית.', 'd' => 'השתמשו בפרטים חושיים: מראות, קולות, ריחות.'],
        ];

        foreach (['Warm-up', 'Memories', 'Looking ahead'] as $i => $title) {
            $stage = Stage::create(['title' => $title]);
            foreach (range(0, 4) as $n) {
                $stage->prompts()->create([
                    'body' => "({$title}) ".$english[$n]['q'],
                    'details' => $english[$n]['d'],
                    'language' => Language::English,
                ]);
                $stage->prompts()->create([
                    'body' => "({$title}) ".$hebrew[$n]['q'],
                    'details' => $hebrew[$n]['d'],
                    'language' => Language::Hebrew,
                ]);
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
