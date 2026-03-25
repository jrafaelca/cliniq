<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class MedicalQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->questions() as $payload) {
            $question = Question::query()->updateOrCreate(
                ['statement' => $payload['statement']],
                [
                    'explanation' => $payload['explanation'],
                    'type' => $payload['type'],
                ],
            );

            foreach ($payload['options'] as $option) {
                $question->options()->updateOrCreate(
                    ['text' => $option['text']],
                    ['is_correct' => $option['is_correct']],
                );
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
                'statement' => '¿Qué sistema del cuerpo transporta oxígeno y nutrientes?',
                'explanation' => 'El sistema circulatorio lleva sangre con oxígeno y nutrientes a los tejidos.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Sistema digestivo', 'is_correct' => false],
                    ['text' => 'Sistema circulatorio', 'is_correct' => true],
                    ['text' => 'Sistema endocrino', 'is_correct' => false],
                    ['text' => 'Sistema linfático', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál es la frecuencia respiratoria normal en un adulto en reposo?',
                'explanation' => 'En adultos en reposo, el rango habitual es entre 12 y 20 respiraciones por minuto.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '6 a 10 rpm', 'is_correct' => false],
                    ['text' => '12 a 20 rpm', 'is_correct' => true],
                    ['text' => '22 a 30 rpm', 'is_correct' => false],
                    ['text' => '30 a 40 rpm', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona signos vitales básicos.',
                'explanation' => 'Presión arterial, pulso, temperatura y frecuencia respiratoria son signos vitales clásicos.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Presión arterial', 'is_correct' => true],
                    ['text' => 'Frecuencia cardíaca', 'is_correct' => true],
                    ['text' => 'Temperatura corporal', 'is_correct' => true],
                    ['text' => 'Frecuencia respiratoria', 'is_correct' => true],
                ],
            ],
            [
                'statement' => '¿Qué vitamina se sintetiza principalmente por exposición solar?',
                'explanation' => 'La síntesis cutánea inducida por sol produce vitamina D.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Vitamina A', 'is_correct' => false],
                    ['text' => 'Vitamina C', 'is_correct' => false],
                    ['text' => 'Vitamina D', 'is_correct' => true],
                    ['text' => 'Vitamina K', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál es un síntoma común de hipoglucemia?',
                'explanation' => 'Sudor frío, temblor y hambre pueden aparecer cuando la glucosa está baja.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Piel seca sin sudor', 'is_correct' => false],
                    ['text' => 'Sudor frío y temblor', 'is_correct' => true],
                    ['text' => 'Poliuria aislada', 'is_correct' => false],
                    ['text' => 'Hipertensión severa aislada', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona medidas efectivas para prevenir infecciones en atención de salud.',
                'explanation' => 'Higiene de manos, uso de EPP y limpieza de superficies reducen riesgo de transmisión.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Higiene de manos', 'is_correct' => true],
                    ['text' => 'Uso adecuado de elementos de protección personal', 'is_correct' => true],
                    ['text' => 'Desinfección de superficies', 'is_correct' => true],
                    ['text' => 'Reutilizar material desechable sin esterilizar', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál es el principal órgano metabolizador de muchos fármacos?',
                'explanation' => 'El hígado participa en el metabolismo de gran parte de los medicamentos.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Pulmón', 'is_correct' => false],
                    ['text' => 'Riñón', 'is_correct' => false],
                    ['text' => 'Hígado', 'is_correct' => true],
                    ['text' => 'Bazo', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cómo se calcula el índice de masa corporal (IMC)?',
                'explanation' => 'IMC = peso en kg dividido por talla en metros al cuadrado.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Peso (kg) / talla (m)', 'is_correct' => false],
                    ['text' => 'Peso (kg) / talla (m)^2', 'is_correct' => true],
                    ['text' => 'Talla (m)^2 / peso (kg)', 'is_correct' => false],
                    ['text' => 'Peso (kg) x talla (m)^2', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona alimentos con mayor aporte de hierro hemo.',
                'explanation' => 'Carnes rojas y vísceras aportan hierro hemo de mayor absorción.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Carne roja', 'is_correct' => true],
                    ['text' => 'Hígado', 'is_correct' => true],
                    ['text' => 'Lentejas', 'is_correct' => false],
                    ['text' => 'Manzana', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Cuál es el rango normal de frecuencia cardíaca en adultos en reposo?',
                'explanation' => 'El rango habitual de pulso en reposo es 60-100 latidos por minuto.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '30 a 50 lpm', 'is_correct' => false],
                    ['text' => '60 a 100 lpm', 'is_correct' => true],
                    ['text' => '110 a 140 lpm', 'is_correct' => false],
                    ['text' => '140 a 180 lpm', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Ante sospecha de accidente cerebrovascular agudo, ¿qué acción es prioritaria?',
                'explanation' => 'El ACV es tiempo-dependiente; activar urgencias rápidamente es clave.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Esperar 24 horas para reevaluar', 'is_correct' => false],
                    ['text' => 'Indicar reposo en casa sin consulta', 'is_correct' => false],
                    ['text' => 'Activar sistema de urgencia y traslado inmediato', 'is_correct' => true],
                    ['text' => 'Dar antibióticos empíricos', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona síntomas de alarma compatibles con síndrome coronario agudo.',
                'explanation' => 'Dolor torácico opresivo, disnea y diaforesis son signos frecuentes de alarma.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Dolor torácico opresivo', 'is_correct' => true],
                    ['text' => 'Disnea', 'is_correct' => true],
                    ['text' => 'Sudoración fría', 'is_correct' => true],
                    ['text' => 'Prurito nasal aislado', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Contra qué enfermedades protege la vacuna triple viral (SRP)?',
                'explanation' => 'SRP protege contra sarampión, rubéola y parotiditis.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Sarampión, rubéola y parotiditis', 'is_correct' => true],
                    ['text' => 'Tétanos, difteria y tos convulsiva', 'is_correct' => false],
                    ['text' => 'Hepatitis A, B y C', 'is_correct' => false],
                    ['text' => 'Influenza A y B', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Para qué tipo de infecciones están indicados los antibióticos?',
                'explanation' => 'Los antibióticos tratan infecciones bacterianas, no virales.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Infecciones bacterianas', 'is_correct' => true],
                    ['text' => 'Todas las infecciones virales', 'is_correct' => false],
                    ['text' => 'Alergias respiratorias', 'is_correct' => false],
                    ['text' => 'Dolor crónico no infeccioso', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona buenas prácticas para medir correctamente la presión arterial.',
                'explanation' => 'Reposo previo, brazalete adecuado y brazo a nivel del corazón mejoran precisión.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Reposar al menos 5 minutos antes', 'is_correct' => true],
                    ['text' => 'Usar manguito de tamaño adecuado', 'is_correct' => true],
                    ['text' => 'Mantener el brazo a nivel del corazón', 'is_correct' => true],
                    ['text' => 'Hablar durante la medición', 'is_correct' => false],
                ],
            ],
            [
                'statement' => '¿Qué valor de glucosa capilar se considera hipoglucemia en adultos?',
                'explanation' => 'Se considera hipoglucemia cuando la glucosa es menor a 70 mg/dL.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Menor a 70 mg/dL', 'is_correct' => true],
                    ['text' => 'Mayor a 200 mg/dL', 'is_correct' => false],
                    ['text' => 'Entre 100 y 120 mg/dL', 'is_correct' => false],
                    ['text' => 'Mayor a 140 mg/dL posprandial', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona factores de riesgo cardiovascular modificables.',
                'explanation' => 'Tabaquismo, sedentarismo, hipertensión no controlada y dislipidemia son modificables.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Tabaquismo', 'is_correct' => true],
                    ['text' => 'Sedentarismo', 'is_correct' => true],
                    ['text' => 'Hipertensión no controlada', 'is_correct' => true],
                    ['text' => 'Dislipidemia', 'is_correct' => true],
                ],
            ],
            [
                'statement' => '¿Qué escala se usa ampliamente para evaluar nivel de conciencia?',
                'explanation' => 'La Escala de Glasgow evalúa apertura ocular, respuesta verbal y motora.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => 'Escala de Apgar', 'is_correct' => false],
                    ['text' => 'Escala de Glasgow', 'is_correct' => true],
                    ['text' => 'Escala de Wells', 'is_correct' => false],
                    ['text' => 'Escala de Child-Pugh', 'is_correct' => false],
                ],
            ],
            [
                'statement' => 'Selecciona recomendaciones generales para hidratación oral en diarrea leve.',
                'explanation' => 'Se sugiere hidratación frecuente con SRO y vigilar signos de deshidratación.',
                'type' => Question::TYPE_MULTIPLE,
                'options' => [
                    ['text' => 'Usar soluciones de rehidratación oral', 'is_correct' => true],
                    ['text' => 'Ofrecer líquidos en pequeñas tomas frecuentes', 'is_correct' => true],
                    ['text' => 'Suspender toda ingesta de líquidos', 'is_correct' => false],
                    ['text' => 'Consultar si aparecen signos de deshidratación', 'is_correct' => true],
                ],
            ],
            [
                'statement' => '¿Cuál es la fórmula del score en esta práctica?',
                'explanation' => 'El score se calcula como (respuestas correctas / total de preguntas) x 100.',
                'type' => Question::TYPE_SINGLE,
                'options' => [
                    ['text' => '(incorrectas / total) x 100', 'is_correct' => false],
                    ['text' => '(correctas / total) x 100', 'is_correct' => true],
                    ['text' => 'correctas - incorrectas', 'is_correct' => false],
                    ['text' => 'correctas x 10', 'is_correct' => false],
                ],
            ],
        ];
    }
}
