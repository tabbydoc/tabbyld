<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use moonland\phpexcel\Excel;
use app\modules\main\models\ExcelFileForm;
use app\components\CanonicalTableAnnotator;

/**
 * SpreadsheetController реализует консольные команды для работы с аннотатором канонических электронных таблиц.
 */
class SpreadsheetController extends Controller
{
    /**
     * Инициализация команд.
     */
    public function actionIndex()
    {
        echo 'yii spreadsheet/annotate' . PHP_EOL;
    }

    /**
     * Команда запуска аннотатора канонических таблиц.
     */
    public function actionAnnotate()
    {
        $one_thousand = 10000;
        for ($i = 1; $i <= 200; $i++) {
            // Массивы ранжированных сущностей кандидатов для значений столбцов DATA, RowHeading, ColumnHeading
            $ranked_data_candidate_entities = array();
            $ranked_row_heading_candidate_entities = array();
            $ranked_column_heading_candidate_entities = array();
            // Получение данных из файла XSLX
            $data = Excel::import(Yii::$app->basePath . '\web\uploads\troy200\C' .
                ($one_thousand + $i) . '.xlsx', [
                'setFirstRecordAsKeys' => true,
                'setIndexSheetByName' => true,
                'getOnlySheet' => ExcelFileForm::CANONICAL_FORM,
            ]);
            // Получение данных о метках NER из файла XSLX
            $ner_data = Excel::import(Yii::$app->basePath . '\web\uploads\troy200\C' .
                ($one_thousand + $i) . '.xlsx', [
                'setFirstRecordAsKeys' => true,
                'setIndexSheetByName' => true,
                'getOnlySheet' => ExcelFileForm::NER_TAGS,
            ]);

            // Создание объекта аннотатора таблиц
            $annotator = new CanonicalTableAnnotator();
            // Идентификация типа таблицы по столбцу DATA
            $annotator->identifyTableType($data, $ner_data);
            // Если установлена стратегия аннотирования литеральных значений
            if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::LITERAL_STRATEGY) {
                // Аннотирование столбца "DATA"
                $ranked_data_candidate_entities = $annotator->annotateTableLiteralData($data, $ner_data);
                // Аннотирование столбца "RowHeading"
                $ranked_row_heading_candidate_entities = $annotator->annotateTableHeading(
                    $data, CanonicalTableAnnotator::ROW_HEADING_TITLE);
                // Аннотирование столбца "ColumnHeading"
                $ranked_column_heading_candidate_entities = $annotator->annotateTableHeading(
                    $data, CanonicalTableAnnotator::COLUMN_HEADING_TITLE);
                // Генерация RDF-документа в формате RDF/XML
                //$annotator->generateRDFXMLCode();
            }
            // Если установлена стратегия аннотирования именованных сущностей
            if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::NAMED_ENTITY_STRATEGY) {
                // Аннотирование столбца "DATA"
                $ranked_data_candidate_entities = $annotator->annotateTableEntityData($data, $ner_data);
            }

            // Определение общего кол-ва и кол-ва аннотированных ячеек канонической таблицы
            $total_number = 0;
            $annotated_entity_number = 0;
            foreach ($data as $item)
                foreach ($item as $heading => $value) {
                    if ($heading == CanonicalTableAnnotator::DATA_TITLE) {
                        $total_number++;
                        foreach ($ranked_data_candidate_entities as $entry => $entity)
                            if ($value == $entry)
                                $annotated_entity_number++;
                    }
                    if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE) {
                        $string_array = explode(" | ", $value);
                        foreach ($string_array as $key => $string) {
                            $total_number++;
                            foreach ($ranked_row_heading_candidate_entities as $entry => $entity)
                                if ($string == $entry)
                                    $annotated_entity_number++;
                        }
                    }
                    if ($heading == CanonicalTableAnnotator::COLUMN_HEADING_TITLE) {
                        $string_array = explode(" | ", $value);
                        foreach ($string_array as $key => $string) {
                            $total_number++;
                            foreach ($ranked_column_heading_candidate_entities as $entry => $entity)
                                if ($string == $entry)
                                    $annotated_entity_number++;
                        }
                    }
                }
            // Высисление оценки полноты
            $recall = $annotated_entity_number / $total_number;

            // Создание директории
            mkdir(Yii::$app->basePath . '\web\uploads\C' . ($one_thousand + $i), 0700);
            // Открытие файла на запись для сохранения времени выполнения запросов
            $runtime_file = fopen(Yii::$app->basePath . '\web\uploads\C' .
                ($one_thousand + $i) . '\runtime.log', 'a');
            // Запись в файл логов оценки полноты
            fwrite($runtime_file, '**************************' . PHP_EOL);
            fwrite($runtime_file, 'Полнота (recall): ' . $recall . PHP_EOL);
            // Запись в файл логов времени выполнения запросов
            fwrite($runtime_file, $annotator->log);
            // Закрытие файла
            fclose($runtime_file);

            // Открытие файла на запись для сохранения результатов аннотирования таблицы
            $result_file = fopen(Yii::$app->basePath . '\web\uploads\C' .
                ($one_thousand + $i) . '\result.log', 'a');
            // Запись в файл логов результаты аннотирования таблицы
            $json = json_encode($ranked_data_candidate_entities, JSON_PRETTY_PRINT);
            $json = str_replace('\/', '/', $json);
            fwrite($result_file, $json);
            fwrite($result_file, PHP_EOL . '**************************************' . PHP_EOL);
            $json = json_encode($ranked_row_heading_candidate_entities, JSON_PRETTY_PRINT);
            $json = str_replace('\/', '/', $json);
            fwrite($result_file, $json);
            fwrite($result_file, PHP_EOL . '**************************************' . PHP_EOL);
            $json = json_encode($ranked_column_heading_candidate_entities, JSON_PRETTY_PRINT);
            $json = str_replace('\/', '/', $json);
            fwrite($result_file, $json);
            // Закрытие файла
            fclose($result_file);
        }
    }
}