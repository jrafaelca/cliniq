<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $cardiologyCategoryId = Category::query()
            ->where('slug', Category::SLUG_CARDIOLOGY)
            ->value('id');

        if ($cardiologyCategoryId === null) {
            return;
        }

        Topic::query()->updateOrCreate(
            ['category_id' => $cardiologyCategoryId, 'slug' => Topic::SLUG_ACUTE_MYOCARDIAL_INFARCTION],
            ['name' => 'Infarto agudo'],
        );

        Topic::query()->updateOrCreate(
            ['category_id' => $cardiologyCategoryId, 'slug' => Topic::SLUG_ARRHYTHMIAS],
            ['name' => 'Arritmias'],
        );
    }
}
