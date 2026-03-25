<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class TechnicalQuestionSeeder extends Seeder
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

        foreach ($this->questions() as $payload) {
            $question = Question::query()->updateOrCreate(
                ['statement' => $payload['statement']],
                [
                    'explanation' => $payload['explanation'],
                    'type' => $payload['type'],
                    'subject_id' => $technicalSubjectId,
                    'category_id' => $technicalGeneralCategoryId,
                    'topic_id' => null,
                ],
            );

            $question->options()->delete();

            foreach ($payload['options'] as $option) {
                $question->options()->create([
                    'text' => $option['text'],
                    'is_correct' => $option['is_correct'],
                ]);
            }
        }
    }

    /**
     * @return array<int, array{
     *     statement: string,
     *     explanation: string,
     *     type: string,
     *     options: array<int, array{text: string, is_correct: bool}>
     * }>
     */
    private function questions(): array
    {
        return [
            [
                'statement' => '¿Qué comando muestra las ramas locales en Git?',
                'explanation' => '`git branch` lista las ramas locales del repositorio.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'git status', 'is_correct' => false],
                    ['text' => 'git branch', 'is_correct' => true],
                    ['text' => 'git merge', 'is_correct' => false],
                    ['text' => 'git stash', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué método HTTP se usa para actualizar parcialmente un recurso?',
                'explanation' => 'PATCH se usa para modificaciones parciales de un recurso.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'GET', 'is_correct' => false],
                    ['text' => 'POST', 'is_correct' => false],
                    ['text' => 'PATCH', 'is_correct' => true],
                    ['text' => 'OPTIONS', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál de estos es un motor de base de datos relacional?',
                'explanation' => 'PostgreSQL es un sistema de gestión de base de datos relacional.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'PostgreSQL', 'is_correct' => true],
                    ['text' => 'Redis', 'is_correct' => false],
                    ['text' => 'Elasticsearch', 'is_correct' => false],
                    ['text' => 'RabbitMQ', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué archivo define dependencias de PHP en un proyecto Laravel?',
                'explanation' => 'Composer usa el archivo composer.json para gestionar dependencias.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'package.json', 'is_correct' => false],
                    ['text' => 'composer.json', 'is_correct' => true],
                    ['text' => 'vite.config.ts', 'is_correct' => false],
                    ['text' => '.editorconfig', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál es el puerto por defecto para HTTPS?',
                'explanation' => 'HTTPS usa el puerto 443 por defecto.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '80', 'is_correct' => false],
                    ['text' => '22', 'is_correct' => false],
                    ['text' => '443', 'is_correct' => true],
                    ['text' => '3306', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona protocolos de capa de aplicación.',
                'explanation' => 'HTTP y SMTP son protocolos de capa de aplicación.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'HTTP', 'is_correct' => true],
                    ['text' => 'SMTP', 'is_correct' => true],
                    ['text' => 'TCP', 'is_correct' => false],
                    ['text' => 'IP', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué hace el comando `php artisan migrate`?',
                'explanation' => 'Ejecuta las migraciones pendientes sobre la base de datos.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Limpia la caché de rutas', 'is_correct' => false],
                    ['text' => 'Crea un controlador nuevo', 'is_correct' => false],
                    ['text' => 'Ejecuta migraciones pendientes', 'is_correct' => true],
                    ['text' => 'Compila assets de frontend', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué representa un registro en Redis Streams?',
                'explanation' => 'Cada entrada en un stream es un mensaje con ID ordenado temporalmente.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Un lock distribuido', 'is_correct' => false],
                    ['text' => 'Un mensaje con ID en un log append-only', 'is_correct' => true],
                    ['text' => 'Una tabla relacional', 'is_correct' => false],
                    ['text' => 'Un índice invertido', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona buenas prácticas de seguridad para contraseñas.',
                'explanation' => 'Claves robustas, gestor y 2FA mejoran la seguridad de acceso.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Usar al menos 12 caracteres', 'is_correct' => true],
                    ['text' => 'Usar un gestor de contraseñas', 'is_correct' => true],
                    ['text' => 'Activar 2FA cuando esté disponible', 'is_correct' => true],
                    ['text' => 'Compartir la clave por correo', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué comando instala dependencias Node con pnpm?',
                'explanation' => '`pnpm install` instala dependencias declaradas en package.json.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'pnpm install', 'is_correct' => true],
                    ['text' => 'pnpm build', 'is_correct' => false],
                    ['text' => 'pnpm prune', 'is_correct' => false],
                    ['text' => 'pnpm why', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué directiva Blade imprime contenido escapando HTML?',
                'explanation' => '`{{ ... }}` escapa salida; `{!! ... !!}` no lo hace.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '@if', 'is_correct' => false],
                    ['text' => '{{ $value }}', 'is_correct' => true],
                    ['text' => '{!! $value !!}', 'is_correct' => false],
                    ['text' => '@foreach', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona lenguajes con tipado estático.',
                'explanation' => 'TypeScript y Go aplican verificación estática de tipos.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'TypeScript', 'is_correct' => true],
                    ['text' => 'Go', 'is_correct' => true],
                    ['text' => 'JavaScript', 'is_correct' => false],
                    ['text' => 'PHP', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué cabecera HTTP se usa para enviar un Bearer token?',
                'explanation' => 'Se utiliza la cabecera Authorization con el esquema Bearer.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Content-Type', 'is_correct' => false],
                    ['text' => 'Accept', 'is_correct' => false],
                    ['text' => 'Authorization', 'is_correct' => true],
                    ['text' => 'Cache-Control', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué código HTTP representa “recurso no encontrado”?',
                'explanation' => '404 Not Found indica que el recurso solicitado no existe.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '200', 'is_correct' => false],
                    ['text' => '401', 'is_correct' => false],
                    ['text' => '404', 'is_correct' => true],
                    ['text' => '500', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona prácticas que mejoran rendimiento SQL.',
                'explanation' => 'Índices, paginación y seleccionar columnas necesarias mejoran performance.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Crear índices en columnas filtradas', 'is_correct' => true],
                    ['text' => 'Seleccionar solo columnas necesarias', 'is_correct' => true],
                    ['text' => 'Paginar resultados grandes', 'is_correct' => true],
                    ['text' => 'Usar SELECT * siempre', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué comando lista rutas registradas en Laravel?',
                'explanation' => '`php artisan route:list` muestra rutas, métodos y nombres.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'php artisan config:show', 'is_correct' => false],
                    ['text' => 'php artisan route:list', 'is_correct' => true],
                    ['text' => 'php artisan cache:clear', 'is_correct' => false],
                    ['text' => 'php artisan make:route', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué hook de Vue se ejecuta al montar el componente?',
                'explanation' => '`onMounted` se ejecuta cuando el componente ya fue montado.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'onMounted', 'is_correct' => true],
                    ['text' => 'watchEffect', 'is_correct' => false],
                    ['text' => 'computed', 'is_correct' => false],
                    ['text' => 'provide', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona propiedades ACID de transacciones.',
                'explanation' => 'ACID se compone de Atomicidad, Consistencia, Aislamiento y Durabilidad.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Atomicidad', 'is_correct' => true],
                    ['text' => 'Consistencia', 'is_correct' => true],
                    ['text' => 'Aislamiento', 'is_correct' => true],
                    ['text' => 'Durabilidad', 'is_correct' => true],
                ],
            ],
            [
                'statement' => '¿Qué estado marca un intento de práctica finalizado?',
                'explanation' => 'El estado `finished` representa un intento terminado.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'active', 'is_correct' => false],
                    ['text' => 'draft', 'is_correct' => false],
                    ['text' => 'finished', 'is_correct' => true],
                    ['text' => 'paused', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál es la fórmula usada para calcular el score?',
                'explanation' => 'El score es (correctas / total) * 100.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '(incorrectas / total) * 100', 'is_correct' => false],
                    ['text' => '(correctas / total) * 100', 'is_correct' => true],
                    ['text' => '(correctas - incorrectas) * 10', 'is_correct' => false],
                    ['text' => 'correctas + incorrectas', 'is_correct' => false],
                ],
            ],
        ];
    }
}
