<?php

namespace app\commands;

use yii\console\Controller;
use moonland\phpexcel\Excel;
use BorderCloud\SPARQL\SparqlClient;
use app\modules\main\models\ExcelFileForm;
use app\components\CanonicalTableAnnotator;
use app\modules\main\models\CellValue;
use app\modules\main\models\AnnotatedRow;
use app\modules\main\models\CandidateEntity;
use app\modules\main\models\AnnotatedDataset;
use app\modules\main\models\AnnotatedCanonicalTable;

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
        echo 'yii spreadsheet/annotate-literal-cell' . PHP_EOL;
        echo 'yii spreadsheet/get-candidate-entities' . PHP_EOL;
        echo 'yii spreadsheet/find-relationship-distance' . PHP_EOL;
    }

    /**
     * Аннотирование ячейки с литеральными данными в столбце "DATA".
     *
     * @param $ner_resource - NER-метка
     * @param $entry - значение ячейки
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     */
    public function actionAnnotateLiteralCell($ner_resource, $entry, $canonical_table_id)
    {
        // Переменная хранения сущности для ячейки с литеральными данными в столбце "DATA"
        $found_entity = '';
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для поиска точного совпадения c сущностью
        $query = "SELECT ?subject ?property ?object
            FROM <http://dbpedia.org>
            WHERE {
                ?subject ?property ?object . FILTER(?subject = <$ner_resource>)
            } LIMIT 1";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows'])
            // Запоминание найденной сущности в онтологии DBpedia
            $found_entity = $rows['result']['rows'][0]['subject'];
        // Сохранение значения ячейки канонической таблицы в БД
        $cell_value_model = new CellValue();
        $cell_value_model->name = $entry;
        $cell_value_model->type = CellValue::DATA;
        $cell_value_model->execution_time = $rows['query_time'];
        $cell_value_model->annotated_canonical_table = $canonical_table_id;
        $cell_value_model->save();
        // Сохранение найденной сущности кандидата в БД
        $candidate_entity_model = new CandidateEntity();
        $candidate_entity_model->entity = $found_entity;
        $candidate_entity_model->aggregated_rank = 1;
        $candidate_entity_model->cell_value = $cell_value_model->id;
        $candidate_entity_model->save();
    }

    /**
     * Поиск и формирование массива сущностей кандидатов.
     *
     * @param $value - значение для поиска сущностей кандидатов по вхождению
     * @param $entry - оригинальное значение в ячейки таблицы
     * @param $heading_title - имя столбца
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     */
    public function actionGetCandidateEntities($value, $entry, $heading_title, $canonical_table_id)
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для поиска сущностей кандидатов
        $query = "PREFIX dbr: <http://dbpedia.org/resource/>
            PREFIX owl: <http://www.w3.org/2002/07/owl#>
            SELECT ?subject rdf:type ?object
            FROM <http://dbpedia.org>
            WHERE { { ?subject a ?object . FILTER( (?subject = dbr:$value) && (strstarts(str(?subject), str(dbr:))) ) } 
                UNION { ?subject a ?object . FILTER ( regex(str(?subject), '$value', 'i') &&
                    (strstarts(str(?subject), str(dbr:)) && (str(?object) = str(owl:Thing))) ) }
        } LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Массив сущностей кандидатов
        $candidate_entities = array();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows'])
            // Формирование массива сущностей кандидатов (повторяющиеся сущности кандидаты не добавляются)
            foreach ($rows['result']['rows'] as $row)
                if (!in_array($row['subject'], $candidate_entities))
                    array_push($candidate_entities, $row['subject']);
        // Сохранение значения ячейки канонической таблицы в БД
        $cell_value_model = new CellValue();
        $cell_value_model->name = $entry;
        if ($heading_title == CanonicalTableAnnotator::ROW_HEADING_TITLE)
            $cell_value_model->type = CellValue::ROW_HEADING;
        if ($heading_title == CanonicalTableAnnotator::COLUMN_HEADING_TITLE)
            $cell_value_model->type = CellValue::COLUMN_HEADING;
        $cell_value_model->execution_time = $rows['query_time'];
        $cell_value_model->annotated_canonical_table = $canonical_table_id;
        $cell_value_model->save();
        // Обход всех сущностей кандидатов
        foreach ($candidate_entities as $candidate_entity) {
            // Сохранение сущности кандидата в БД
            $candidate_entity_model = new CandidateEntity();
            $candidate_entity_model->entity = $candidate_entity;
            $candidate_entity_model->cell_value = $cell_value_model->id;
            $candidate_entity_model->save();
        }
    }

    /**
     * Вычисление оценки (расстояния) близости сущностей кандидатов по связям между друг другом.
     *
     * @param $current_label - текущее оригинальное значение в ячейки таблицы
     * @param $current_candidate_entity - текущее значение сущности кандидата, для которого ищуться связи
     * @param $path - путь для сохранения файлов
     */
    public function actionFindRelationshipDistance($current_label, $current_candidate_entity, $path)
    {
        // Кодирование в имени файла запрещенных символов
        $correct_current_label = CanonicalTableAnnotator::encodeFileName($current_label);
        // Набор всех массивов сущностей кандидатов
        $all_candidate_entities = array();
        // Открытие каталога с файлами
        if ($handle = opendir($path)) {
            // Чтения элементов (файлов) каталога
            while (false !== ($file_name = readdir($handle))) {
                // Поиск в строке символов расширения ".log"
                $pos = strpos($file_name, '.log');
                // Если элемент каталога не является каталогом, файлом с результатами вычисления расстояния Левенштейна
                // и если название файла не совпадает с текущим выбранным элементом
                if ($file_name != '.' && $file_name != '..' && $pos != false) {
                    // Массив сущностей кандидатов
                    $candidate_entities = array();
                    // Кодирование в имени файла запрещенных символов
                    $correct_file_name = CanonicalTableAnnotator::encodeFileName($file_name);
                    // Открытие файла
                    $fp = fopen($path . $correct_file_name, "r");
                    // Чтение файла до конца
                    while (!feof($fp)) {
                        // Чтение строки из файла и удаление переноса строк
                        $text = str_replace("\r\n", '', fgets($fp));
                        // Формирование массива сущностей кандидатов
                        if ($text != '')
                            array_push($candidate_entities, $text);
                    }
                    // Закрытие файла
                    fclose($fp);
                    // Получение имени файла без расширения
                    $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME );
                    // Добавление массива сущностей кандидатов в общий набор
                    $all_candidate_entities[$file_name_without_extension] = $candidate_entities;
                }
            }
            // Закрытие каталога
            closedir($handle);
        }
        // Текущее расстояние
        $distance = 0;
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // Текст SPARQL-запроса
        $query = "";
        // Обход массивов сущностей кандидатов из всего набора
        foreach ($all_candidate_entities as $label => $candidate_entities)
            // Если это не один и тот же массив с сущностями кандидатами
            if ($current_label != $label)
                // Обход всех сущностей кандидатов из массива
                foreach ($candidate_entities as $candidate_entity)
                    if ($query == "")
                        // SPARQL-запрос к DBpedia для поиска сущностей кандидатов
                        $query = "SELECT COUNT(*) as ?Triples
                            FROM <http://dbpedia.org>
                            WHERE {{ <$current_candidate_entity> ?property <$candidate_entity> }";
                    else
                        $query .= " UNION { <$current_candidate_entity> ?property <$candidate_entity> }";
        // Закрытие условия в тексте SPARQL-запроса
        $query .= "}";
        // Выполнение SPARQL-запроса
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows'])
            // Запоминание кол-ва найденных триплетов связей (итоговой оценки для сущности кандидата)
            $distance = $rows['result']['rows'][0]['Triples'];
        // Массив названий URI DBpedia
        $URIs = array(CanonicalTableAnnotator::DBPEDIA_ONTOLOGY_SECTION,
            CanonicalTableAnnotator::DBPEDIA_RESOURCE_SECTION, CanonicalTableAnnotator::DBPEDIA_PROPERTY_SECTION);
        // Удаление URI из названия сущности кандидата
        $current_candidate_entity_name = str_replace($URIs, '', $current_candidate_entity);
        // Кодирование в имени файла запрещенных символов
        $correct_current_candidate_entity_name = CanonicalTableAnnotator::encodeFileName($current_candidate_entity_name);
        // Открытие файла на запись для сохранения результатов поиска сущностей кандидатов
        $result_file = fopen($path . $correct_current_label . '/' .
            $correct_current_candidate_entity_name . '.log', 'a');
        // Запись в файл логов оценки (расстояния)
        fwrite($result_file, $distance . PHP_EOL);
        // Запись в файл логов времени выполнения запроса
        fwrite($result_file, 'SPARQL_EXECUTION_TIME: ' . $rows['query_time']);
        // Закрытие файла
        fclose($result_file);
    }

    /**
     * Команда запуска аннотатора канонических таблиц.
     */
    public function actionAnnotate()
    {
        // Начало отсчета времени обработки всего набора данных
        $dataset_runtime = microtime(true);
        // Сохранение информации об аннотированном наборе данных электронных таблиц в БД
        $annotated_dataset_model = new AnnotatedDataset();
        $annotated_dataset_model->name = 'test_dataset';
        $annotated_dataset_model->status = AnnotatedDataset::PUBLIC_STATUS;
        $annotated_dataset_model->recall = 0;
        $annotated_dataset_model->precision = 0;
        $annotated_dataset_model->runtime = 0;
        $annotated_dataset_model->save();
        // Открытие каталога с набором данных электронных таблиц
        if ($handle = opendir('web/dataset/')) {
            // Чтения элементов (файлов) каталога
            while (false !== ($file_name = readdir($handle))) {
                // Если элемент каталога не является другим каталогом
                if ($file_name != '.' && $file_name != '..') {
                    // Начало отсчета времени обработки таблицы
                    $table_runtime = microtime(true);
                    // Получение данных из файла XSLX
                    $data = Excel::import('web/dataset/' . $file_name, [
                        'setFirstRecordAsKeys' => true,
                        'setIndexSheetByName' => true,
                        'getOnlySheet' => ExcelFileForm::CANONICAL_FORM,
                    ]);
                    // Получение данных о метках NER из файла XSLX
                    $ner_data = Excel::import('web/dataset/' . $file_name, [
                        'setFirstRecordAsKeys' => true,
                        'setIndexSheetByName' => true,
                        'getOnlySheet' => ExcelFileForm::NER_TAGS,
                    ]);
                    // Получение имени файла без расширения
                    $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME );
                    // Сохранение информации об аннотированной канонической таблице в БД
                    $annotated_canonical_table_model = new AnnotatedCanonicalTable();
                    $annotated_canonical_table_model->name = $file_name_without_extension;
                    $annotated_canonical_table_model->total_element_number = 0;
                    $annotated_canonical_table_model->annotated_element_number = 0;
                    $annotated_canonical_table_model->recall = 0;
                    $annotated_canonical_table_model->precision = 0;
                    $annotated_canonical_table_model->runtime = 0;
                    $annotated_canonical_table_model->annotated_dataset = $annotated_dataset_model->id;
                    $annotated_canonical_table_model->save();

                    // Создание объекта аннотатора таблиц
                    $annotator = new CanonicalTableAnnotator();
                    // Идентификация типа таблицы по столбцу DATA
                    $annotator->identifyTableType($data, $ner_data);
                    // Если установлена стратегия аннотирования литеральных значений
                    if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::LITERAL_STRATEGY) {
                        // Аннотирование столбца "DATA"
                        $annotator->annotateTableLiteralData($data, $ner_data, $annotated_canonical_table_model->id);
                        // Аннотирование столбца "RowHeading"
                        $annotator->annotateTableHeading($data, CanonicalTableAnnotator::ROW_HEADING_TITLE,
                            $annotated_canonical_table_model->id);
                        // Аннотирование столбца "ColumnHeading"
                        $annotator->annotateTableHeading($data,
                           CanonicalTableAnnotator::COLUMN_HEADING_TITLE,
                            $annotated_canonical_table_model->id);
                        // Генерация RDF-документа в формате RDF/XML
                        //$anotator->generateRDFXMLCode();
                    }
                    // Если установлена стратегия аннотирования именованных сущностей
                    if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::NAMED_ENTITY_STRATEGY) {
                        // Аннотирование столбца "DATA"
                        $ranked_data_candidate_entities = $annotator->annotateTableEntityData($data, $ner_data);
                    }

                    // Обход массива данных исходной канонической таблицы
                    foreach ($data as $item) {
                        // Создание новой модели строки в канонической таблицы
                        $annotated_row_model = new AnnotatedRow();
                        // Обход массива строки исходной канонической таблицы
                        foreach ($item as $heading => $value) {
                            if ($heading == CanonicalTableAnnotator::DATA_TITLE) {
                                // Подсчет общего кол-ва ячеек канонической таблицы
                                $annotated_canonical_table_model->total_element_number++;
                                // Присвоение оригинального значения ячейки
                                $annotated_row_model->data = $value;
                                // Поиск всех значений ячеек столбца с данными "DATA"
                                $cell_values = CellValue::find()
                                    ->where(['annotated_canonical_table' => $annotated_canonical_table_model->id,
                                        'type' => CellValue::DATA])
                                    ->all();
                                // Обход всех значений ячеек столбца "DATA"
                                foreach ($cell_values as $cell_value)
                                    if ($value == $cell_value->name) {
                                        // Подсчет кол-ва аннотированных ячеек канонической таблицы
                                        $annotated_canonical_table_model->annotated_element_number++;
                                        // Получение первой записи с сущностью кандидатом с самым высоким агрегированным рангом
                                        $candidate_entity = CandidateEntity::find()
                                            ->where(['cell_value' => $cell_value->id])
                                            ->orderBy('aggregated_rank DESC')
                                            ->one();
                                        // Присвоение значению ячейки референтной сущности кандидата
                                        $annotated_row_model->data = $candidate_entity->entity;
                                    }
                            }
                            if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE) {
                                $string_array = explode(" | ", $value);
                                foreach ($string_array as $key => $string) {
                                    $existing_entity = '';
                                    // Подсчет общего кол-ва ячеек канонической таблицы
                                    $annotated_canonical_table_model->total_element_number++;
                                    // Поиск значения ячейки столбца с данными "RowHeading"
                                    $cell_value = CellValue::find()
                                        ->where(['annotated_canonical_table' => $annotated_canonical_table_model->id,
                                            'name' => $string, 'type' => CellValue::ROW_HEADING])
                                        ->one();
                                    // Если выборка значения ячейки не пустая и значения ячеек совпадают
                                    if (!empty($cell_value) && $string == $cell_value->name) {
                                        // Получение первой записи с сущностью кандидатом с самым высоким агрегированным рангом
                                        $candidate_entity = CandidateEntity::find()
                                            ->where(['cell_value' => $cell_value->id])
                                            ->orderBy('aggregated_rank DESC')
                                            ->one();
                                        // Если выборка сущности кандидата не пустая
                                        if (!empty($candidate_entity)) {
                                            // Подсчет кол-ва аннотированных ячеек канонической таблицы
                                            $annotated_canonical_table_model->annotated_element_number++;
                                            // Запоминание референтной сущности кандидата
                                            $existing_entity = $candidate_entity->entity;
                                        }
                                    }
                                    // Присвоение значению ячейки референтной сущности кандидата
                                    if ($annotated_row_model->row_heading != '' && $existing_entity == '')
                                        $annotated_row_model->row_heading .= ' | ' . $string;
                                    if ($annotated_row_model->row_heading != '' && $existing_entity != '')
                                        $annotated_row_model->row_heading .= ' | ' . $existing_entity;
                                    if ($annotated_row_model->row_heading == '' && $existing_entity == '')
                                        $annotated_row_model->row_heading = $string;
                                    if ($annotated_row_model->row_heading == '' && $existing_entity != '')
                                        $annotated_row_model->row_heading = $existing_entity;
                                }
                            }
                            if ($heading == CanonicalTableAnnotator::COLUMN_HEADING_TITLE) {
                                $string_array = explode(" | ", $value);
                                foreach ($string_array as $key => $string) {
                                    $existing_entity = '';
                                    // Подсчет общего кол-ва ячеек канонической таблицы
                                    $annotated_canonical_table_model->total_element_number++;
                                    // Поиск всех значений ячеек столбца с данными "ColumnHeading"
                                    $cell_value = CellValue::find()
                                        ->where(['annotated_canonical_table' => $annotated_canonical_table_model->id,
                                            'name' => $string, 'type' => CellValue::COLUMN_HEADING])
                                        ->one();
                                    // Если выборка значения ячейки не пустая и значения ячеек совпадают
                                    if (!empty($cell_value) && $string == $cell_value->name) {
                                        // Получение первой записи с сущностью кандидатом с самым высоким агрегированным рангом
                                        $candidate_entity = CandidateEntity::find()
                                            ->where(['cell_value' => $cell_value->id])
                                            ->orderBy('aggregated_rank DESC')
                                            ->one();
                                        // Если выборка сущности кандидата не пустая
                                        if (!empty($candidate_entity)) {
                                            // Запоминание референтной сущности кандидата
                                            $existing_entity = $candidate_entity->entity;
                                            // Подсчет кол-ва аннотированных ячеек канонической таблицы
                                            $annotated_canonical_table_model->annotated_element_number++;
                                        }
                                    }
                                    // Присвоение значению ячейки референтной сущности кандидата
                                    if ($annotated_row_model->column_heading != '' && $existing_entity == '')
                                        $annotated_row_model->column_heading .= ' | ' . $string;
                                    if ($annotated_row_model->column_heading != '' && $existing_entity != '')
                                        $annotated_row_model->column_heading .= ' | ' . $existing_entity;
                                    if ($annotated_row_model->column_heading == '' && $existing_entity == '')
                                        $annotated_row_model->column_heading = $string;
                                    if ($annotated_row_model->column_heading == '' && $existing_entity != '')
                                        $annotated_row_model->column_heading = $existing_entity;
                                }
                            }
                        }
                        // Сохранение строки канонической таблицы в БД
                        $annotated_row_model->annotated_canonical_table = $annotated_canonical_table_model->id;
                        $annotated_row_model->save();
                    }
                    // Поиск всех строк текущей аннотированной канонической таблицы
                    $all_annotated_rows = AnnotatedRow::find()
                        ->where(['annotated_canonical_table' => $annotated_canonical_table_model->id])
                        ->all();
                    // Формирование и сохранение аннотированной таблицы в файл Excel (XLSX)
                    Excel::export([
                        'models' => $all_annotated_rows,
                        'format' => 'Xlsx',
                        'fileName' => $file_name_without_extension,
                        'savePath' => 'web/results/',
                        'columns' => ['data', 'row_heading', 'column_heading'],
                        'headers' => [
                            'data' => 'DATA',
                            'row_heading' => 'RowHeading1',
                            'column_heading' => 'ColumnHeading'
                        ],
                    ]);
                    // Вычисление оценки полноты и запись ее в БД
                    $annotated_canonical_table_model->recall =
                        $annotated_canonical_table_model->annotated_element_number /
                        $annotated_canonical_table_model->total_element_number;
                    // Запись времени обработки таблицы
                    $annotated_canonical_table_model->runtime = round(microtime(true) -
                        $table_runtime, 4);
                    // Обновление полей в БД
                    $annotated_canonical_table_model->updateAttributes(['total_element_number',
                        'annotated_element_number', 'recall', 'runtime']);
                }
            }
            // Закрытие каталога набора данных
            closedir($handle);
        }
        // Вычисление и запись общего времени выполнения аннотирования набора данных в БД
        $annotated_dataset_model->runtime = round(microtime(true) - $dataset_runtime, 4);
        $annotated_dataset_model->updateAttributes(['runtime']);
    }
}