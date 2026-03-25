<?php

use App\Models\Category;
use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $medicineSubjectId = DB::table('subjects')->where('slug', Subject::SLUG_MEDICINE)->value('id');
        $technicalSubjectId = DB::table('subjects')->where('slug', Subject::SLUG_TECHNICAL)->value('id');

        if ($medicineSubjectId === null) {
            $medicineSubjectId = DB::table('subjects')->insertGetId([
                'name' => 'Medicina',
                'slug' => Subject::SLUG_MEDICINE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($technicalSubjectId === null) {
            $technicalSubjectId = DB::table('subjects')->insertGetId([
                'name' => 'Técnico',
                'slug' => Subject::SLUG_TECHNICAL,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $cardiologyCategoryId = DB::table('categories')
            ->where('subject_id', $medicineSubjectId)
            ->where('slug', Category::SLUG_CARDIOLOGY)
            ->value('id');

        $technicalGeneralCategoryId = DB::table('categories')
            ->where('subject_id', $technicalSubjectId)
            ->where('slug', Category::SLUG_GENERAL)
            ->value('id');

        if ($cardiologyCategoryId === null) {
            $cardiologyCategoryId = DB::table('categories')->insertGetId([
                'subject_id' => $medicineSubjectId,
                'name' => 'Cardiología',
                'slug' => Category::SLUG_CARDIOLOGY,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($technicalGeneralCategoryId === null) {
            $technicalGeneralCategoryId = DB::table('categories')->insertGetId([
                'subject_id' => $technicalSubjectId,
                'name' => 'General',
                'slug' => Category::SLUG_GENERAL,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('questions')
            ->whereNull('subject_id')
            ->update(['subject_id' => $technicalSubjectId]);

        DB::table('questions')
            ->where('subject_id', $medicineSubjectId)
            ->whereNull('category_id')
            ->update(['category_id' => $cardiologyCategoryId]);

        DB::table('questions')
            ->where('subject_id', $technicalSubjectId)
            ->whereNull('category_id')
            ->update(['category_id' => $technicalGeneralCategoryId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('questions')->update([
            'subject_id' => null,
            'category_id' => null,
            'topic_id' => null,
        ]);
    }
};
