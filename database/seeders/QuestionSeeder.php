<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SubjectSeeder::class,
            CategorySeeder::class,
        ]);

        $technicalSubjectId = Subject::query()
            ->where('slug', Subject::SLUG_TECHNICAL)
            ->value('id');

        $technicalGeneralCategoryId = Category::query()
            ->where('subject_id', $technicalSubjectId)
            ->where('slug', Category::SLUG_GENERAL)
            ->value('id');

        if ($technicalSubjectId === null || $technicalGeneralCategoryId === null) {
            return;
        }

        if (Question::query()->exists()) {
            return;
        }

        $questions = [
            [
                'statement' => '¿Cuál es la capital de Chile?',
                'explanation' => 'Santiago es la capital y principal centro administrativo del país.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Valparaíso', 'is_correct' => false],
                    ['text' => 'Concepción', 'is_correct' => false],
                    ['text' => 'Santiago', 'is_correct' => true],
                    ['text' => 'Antofagasta', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué capa de OSI transporta paquetes entre redes?',
                'explanation' => 'La capa de red (nivel 3) se encarga del enrutamiento de paquetes.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Capa de aplicación', 'is_correct' => false],
                    ['text' => 'Capa de red', 'is_correct' => true],
                    ['text' => 'Capa física', 'is_correct' => false],
                    ['text' => 'Capa de sesión', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona lenguajes de programación compilados.',
                'explanation' => 'Rust y Go se compilan a código máquina antes de su ejecución principal.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Rust', 'is_correct' => true],
                    ['text' => 'Go', 'is_correct' => true],
                    ['text' => 'HTML', 'is_correct' => false],
                    ['text' => 'CSS', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué comando de Git crea un commit?',
                'explanation' => '`git commit` crea un nuevo commit con los cambios en staging.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'git clone', 'is_correct' => false],
                    ['text' => 'git pull', 'is_correct' => false],
                    ['text' => 'git commit', 'is_correct' => true],
                    ['text' => 'git fetch', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona prácticas recomendadas de seguridad.',
                'explanation' => 'Validar entradas y usar hash de contraseñas son prácticas base de seguridad.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Guardar contraseñas en texto plano', 'is_correct' => false],
                    ['text' => 'Validar input del usuario', 'is_correct' => true],
                    ['text' => 'Usar hashing de contraseñas', 'is_correct' => true],
                    ['text' => 'Compartir tokens por chat', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué método HTTP se usa típicamente para crear recursos?',
                'explanation' => 'POST es el método estándar para crear recursos en REST.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'POST', 'is_correct' => true],
                    ['text' => 'GET', 'is_correct' => false],
                    ['text' => 'DELETE', 'is_correct' => false],
                    ['text' => 'OPTIONS', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona bases de datos relacionales.',
                'explanation' => 'PostgreSQL y MySQL son motores relacionales.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'PostgreSQL', 'is_correct' => true],
                    ['text' => 'Redis', 'is_correct' => false],
                    ['text' => 'MySQL', 'is_correct' => true],
                    ['text' => 'MongoDB', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál de estos es un framework de PHP?',
                'explanation' => 'Laravel es un framework de PHP ampliamente usado.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Laravel', 'is_correct' => true],
                    ['text' => 'Django', 'is_correct' => false],
                    ['text' => 'Rails', 'is_correct' => false],
                    ['text' => 'Express', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona principios SOLID.',
                'explanation' => 'SRP e ISP son dos de los cinco principios SOLID.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Single Responsibility', 'is_correct' => true],
                    ['text' => 'Interface Segregation', 'is_correct' => true],
                    ['text' => 'YAGNI', 'is_correct' => false],
                    ['text' => 'DRY', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué comando instala dependencias con Composer?',
                'explanation' => '`composer install` instala dependencias definidas en composer.lock/json.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'composer update', 'is_correct' => false],
                    ['text' => 'composer install', 'is_correct' => true],
                    ['text' => 'php artisan serve', 'is_correct' => false],
                    ['text' => 'npm install', 'is_correct' => false],
                ],
            ],
        ];

        foreach ($questions as $payload) {
            $question = Question::query()->create([
                'statement' => $payload['statement'],
                'explanation' => $payload['explanation'],
                'type' => $payload['type'],
                'subject_id' => $technicalSubjectId,
                'category_id' => $technicalGeneralCategoryId,
                'topic_id' => null,
            ]);

            foreach ($payload['options'] as $option) {
                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'text' => $option['text'],
                    'is_correct' => $option['is_correct'],
                ]);
            }
        }
    }
}
