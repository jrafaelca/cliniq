<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SubjectSeeder::class,
            CategorySeeder::class,
            TopicSeeder::class,
            TechnicalQuestionSeeder::class,
            MedicalQuestionSeeder::class,
        ]);
    }
}
