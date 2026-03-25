<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SubjectSeeder::class);

        $medicineSubjectId = Subject::query()
            ->where('slug', Subject::SLUG_MEDICINE)
            ->value('id');

        $technicalSubjectId = Subject::query()
            ->where('slug', Subject::SLUG_TECHNICAL)
            ->value('id');

        if ($medicineSubjectId === null || $technicalSubjectId === null) {
            return;
        }

        Category::query()->updateOrCreate(
            ['subject_id' => $medicineSubjectId, 'slug' => Category::SLUG_CARDIOLOGY],
            ['name' => 'Cardiología'],
        );

        Category::query()->updateOrCreate(
            ['subject_id' => $medicineSubjectId, 'slug' => Category::SLUG_RESPIRATORY],
            ['name' => 'Respiratorio'],
        );

        Category::query()->updateOrCreate(
            ['subject_id' => $medicineSubjectId, 'slug' => Category::SLUG_GASTROENTEROLOGY],
            ['name' => 'Gastroenterología'],
        );

        Category::query()->updateOrCreate(
            ['subject_id' => $technicalSubjectId, 'slug' => Category::SLUG_GENERAL],
            ['name' => 'General'],
        );
    }
}
