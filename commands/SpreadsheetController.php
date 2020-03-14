<?php

namespace app\commands;

use yii\console\Controller;
use moonland\phpexcel\Excel;
use BorderCloud\SPARQL\SparqlClient;
use app\modules\main\models\ExcelFileForm;
use app\components\CanonicalTableAnnotator;
use app\modules\main\models\CellValue;
use app\modules\main\models\HeadingRank;
use app\modules\main\models\ParentClass;
use app\modules\main\models\NerClassRank;
use app\modules\main\models\AnnotatedRow;
use app\modules\main\models\EntityContext;
use app\modules\main\models\CandidateEntity;
use app\modules\main\models\AnnotatedDataset;
use app\modules\main\models\ContextSimilarity;
use app\modules\main\models\SemanticSimilarity;
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
        echo 'yii spreadsheet/find-relationship-rank' . PHP_EOL;
        echo 'yii spreadsheet/get-ner-class-rank' . PHP_EOL;
        echo 'yii spreadsheet/get-heading-rank' . PHP_EOL;
        echo 'yii spreadsheet/get-entity-context' . PHP_EOL;
        echo 'yii spreadsheet/get-parent-classes' . PHP_EOL;
    }

    /**
     * Аннотирование ячейки с литеральными данными в столбце "DATA".
     *
     * @param $ner_resource - NER-метка
     * @param $entry - значение ячейки
     * @param $heading_title - имя столбца
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     */
    public function actionAnnotateLiteralCell($ner_resource, $entry, $heading_title, $canonical_table_id)
    {
        // Сохранение значения ячейки канонической таблицы в БД
        $cell_value_model = new CellValue();
        $cell_value_model->name = $entry;
        if ($heading_title == CanonicalTableAnnotator::DATA_TITLE)
            $cell_value_model->type = CellValue::DATA;
        if ($heading_title == CanonicalTableAnnotator::ROW_HEADING_TITLE)
            $cell_value_model->type = CellValue::ROW_HEADING;
        if ($heading_title == CanonicalTableAnnotator::COLUMN_HEADING_TITLE)
            $cell_value_model->type = CellValue::COLUMN_HEADING;
        $cell_value_model->execution_time = 0;
        $cell_value_model->annotated_canonical_table = $canonical_table_id;
        $cell_value_model->save();
        // Сохранение найденной сущности кандидата в БД
        $candidate_entity_model = new CandidateEntity();
        $candidate_entity_model->entity = $ner_resource;
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
        if ($heading_title == CanonicalTableAnnotator::DATA_TITLE)
            $cell_value_model->type = CellValue::DATA;
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
    public function actionFindRelationshipRank($current_label, $current_candidate_entity, $path)
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
     * Определение близости сущности кандидата к классу, который задан NER-меткой.
     *
     * @param $ner_label - NER-метка присвоенная значению ячейки в столбце DATA
     * @param $candidate_entity_name - имя сущности кандидата
     * @param $candidate_entity_id - идентификатор сущности кандидата
     */
    public function actionGetNerClassRank($ner_label, $candidate_entity_name, $candidate_entity_id)
    {
        // Определение класса из онтологии DBpedia для соответствующей метки NER
        $ner_class = '';
        if ($ner_label == CanonicalTableAnnotator::LOCATION_NER_LABEL)
            $ner_class = CanonicalTableAnnotator::LOCATION_ONTOLOGY_CLASS;
        if ($ner_label == CanonicalTableAnnotator::PERSON_NER_LABEL)
            $ner_class = CanonicalTableAnnotator::PERSON_ONTOLOGY_CLASS;
        if ($ner_label == CanonicalTableAnnotator::ORGANIZATION_NER_LABEL)
            $ner_class = CanonicalTableAnnotator::ORGANISATION_ONTOLOGY_CLASS;
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для определения глубины связи текущей сущности из набора кандидатов с классом
        $query = "SELECT count(?intermediate)/2 as ?depth
            FROM <http://dbpedia.org>
            WHERE { <$candidate_entity_name> rdf:type/rdfs:subClassOf* ?intermediate .
                ?intermediate rdfs:subClassOf* <$ner_class>
            }";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Вычисляемый ранг (оценка) для сущности кандидата
        $rank = 0;
        // Если нет ошибок при запросе, есть результат запроса и глубина не 0, то
        // вычисление ранга (оценки) для текущей сущности из набора кандидатов в соответствии с глубиной
        if (!$error && $rows['result']['rows'] && $rows['result']['rows'][0]['depth'] != 0)
            $rank = 1 / $rows['result']['rows'][0]['depth'];
        // Сохранение ранга (оценки) близости сущности кандидата к классу, который задан NER-меткой, в БД
        $ner_class_rank_model = new NerClassRank();
        $ner_class_rank_model->rank = (int)$rank;
        $ner_class_rank_model->execution_time = $rows['query_time'];
        $ner_class_rank_model->candidate_entity = $candidate_entity_id;
        $ner_class_rank_model->save();
    }

    /**
     * Определение сходства сущности кандидата по заголовкам таблицы.
     *
     * @param $formed_row_heading_labels - массив заголовков строки в канонической таблице
     * @param $candidate_entity_name - имя сущности кандидата
     * @param $candidate_entity_id - идентификатор сущности кандидата
     */
    public function actionGetHeadingRank($formed_row_heading_labels, $candidate_entity_name, $candidate_entity_id)
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
            SELECT ?class
            FROM <http://dbpedia.org>
            WHERE { <$candidate_entity_name> rdf:type ?class . FILTER(strstarts(str(?class), str(dbo:))) }";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Вычисляемый ранг (оценка) для сущности кандидата
        $rank = 100;
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows']) {
            // Обход всех найденных классов для сущности кандидата
            foreach ($rows['result']['rows'] as $item) {
                $distance = 100;
                // Удаление адреса URI у класса
                $class_name = str_replace(CanonicalTableAnnotator::DBPEDIA_ONTOLOGY_SECTION, '',
                    $item['class']);
                // Обход всех заголовков в строке таблицы
                foreach (explode(',', $formed_row_heading_labels) as $heading_label) {
                    // Вычисление расстояния Левенштейна между классом и текущим названием заголовка
                    $current_distance = levenshtein($class_name, $heading_label);
                    // Определение наименьшего расстояния Левенштейна
                    if ($current_distance < $distance)
                        $distance = $current_distance;
                }
                // Определение наименьшего расстояния Левенштейна для текущего класса
                if ($distance < $rank)
                    $rank = $distance;
            }
        }
        // Сохранение ранга (оценки) сходства сущности кандидата по заголовкам таблицы в БД
        $heading_rank_model = new HeadingRank();
        $heading_rank_model->rank = (int)$rank;
        $heading_rank_model->execution_time = $rows['query_time'];
        $heading_rank_model->candidate_entity = $candidate_entity_id;
        $heading_rank_model->save();
    }

    /**
     * Получение контекста для сущности кандидата.
     *
     * @param $candidate_entity_name - имя сущности кандидата
     * @param $candidate_entity_id - идентификатор сущности кандидата
     */
    public function actionGetEntityContext($candidate_entity_name, $candidate_entity_id)
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для поиска всех триплетов связанных с сущностью кандидатом
        $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
            PREFIX dbr: <http://dbpedia.org/resource/>
            SELECT ?subject ?object
            FROM <http://dbpedia.org>
            WHERE {
                { <$candidate_entity_name> ?property ?object .
                    FILTER(strstarts(str(?object), str(dbo:)) || strstarts(str(?object), str(dbr:))) .
                    FILTER(strstarts(str(?property), str(dbo:)) || strstarts(str(?property), str(dbr:)))
                } UNION { ?subject ?property <$candidate_entity_name> .
                    FILTER(strstarts(str(?subject), str(dbo:)) || strstarts(str(?subject), str(dbr:))) .
                    FILTER(strstarts(str(?property), str(dbo:)) || strstarts(str(?property), str(dbr:)))
                }
            }";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows'])
            // Формирование файлов для хранения контекста сущности
            foreach ($rows['result']['rows'] as $row) {
                if (array_key_exists('subject', $row)) {
                    // Сохранение контекста сущности кандидата
                    $entity_context_model = new EntityContext();
                    $entity_context_model->context = $row['subject'];
                    $entity_context_model->candidate_entity = $candidate_entity_id;
                    $entity_context_model->save();
                }
                if (array_key_exists('object', $row)) {
                    // Сохранение контекста сущности кандидата
                    $entity_context_model = new EntityContext();
                    $entity_context_model->context = $row['object'];
                    $entity_context_model->candidate_entity = $candidate_entity_id;
                    $entity_context_model->save();
                }
            }
        // Создание модели для хранения сходства сущностей кандидатов по контексту упоминания сущности
        $context_similarity_model = new ContextSimilarity();
        $context_similarity_model->rank = 0;
        $context_similarity_model->execution_time = $rows['query_time'];
        $context_similarity_model->candidate_entity = $candidate_entity_id;
        $context_similarity_model->save();
    }

    /**
     * Поиск и формирование массива родительских классов для сущности кандидата.
     *
     * @param $candidate_entity_name - имя сущности кандидата
     * @param $candidate_entity_id - идентификатор сущности кандидата
     */
    public function actionGetParentClasses($candidate_entity_name, $candidate_entity_id)
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia ontology для поиска родительских классов для сущности
        $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
            SELECT ?class
            FROM <http://dbpedia.org>
            WHERE {
                <$candidate_entity_name> ?property ?class . FILTER (strstarts(str(?class), str(dbo:)))
            } LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows']) {
            foreach ($rows['result']['rows'] as $row) {
                // Сохранение родительского класса для сущности кандидата в БД
                $parent_class_model = new ParentClass();
                $parent_class_model->class = $row['class'];
                $parent_class_model->candidate_entity = $candidate_entity_id;
                $parent_class_model->save();
            }
        }
        // Создание модели для хранения сходства между сущностями из набора кандидатов по семантической близости
        $semantic_similarity_model = new SemanticSimilarity();
        $semantic_similarity_model->rank = 0;
        $semantic_similarity_model->execution_time = $rows['query_time'];
        $semantic_similarity_model->candidate_entity = $candidate_entity_id;
        $semantic_similarity_model->save();
    }

    /**
     * Определение кол-ва корректно аннотированных элементов для набора данных Troy200.
     *
     * @param $dbpedia_data - данные о метках DBpedia из файла XLSX
     * @param $all_annotated_rows - набор всех строк текущей аннотированной канонической таблицы
     * @param $annotated_canonical_table_model - модель аннотированной таблицы
     */
    public function calculateTroy200($dbpedia_data, $all_annotated_rows, $annotated_canonical_table_model)
    {
        // Подсчет корректно аннотированных значений ячеек
        foreach ($dbpedia_data as $table_row_key => $table_row)
            foreach ($table_row as $heading => $value) {
                if ($heading == CanonicalTableAnnotator::DATA_TITLE)
                    foreach ($all_annotated_rows as $annotated_row_key => $annotated_row)
                        if ($table_row_key == $annotated_row_key && $value == $annotated_row->data)
                            $annotated_canonical_table_model->correctly_annotated_element_number++;
                if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $string)
                        foreach ($all_annotated_rows as $annotated_row_key => $annotated_row)
                            if ($table_row_key == $annotated_row_key) {
                                $annotated_row_heading = explode(" | ",
                                    $annotated_row->row_heading);
                                foreach ($annotated_row_heading as $annotated_value)
                                    if ($string == $annotated_value)
                                        $annotated_canonical_table_model->correctly_annotated_element_number++;
                            }
                }
                if ($heading == CanonicalTableAnnotator::COLUMN_HEADING_TITLE) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $string)
                        foreach ($all_annotated_rows as $annotated_row_key => $annotated_row)
                            if ($table_row_key == $annotated_row_key) {
                                $annotated_column_heading = explode(" | ",
                                    $annotated_row->column_heading);
                                foreach ($annotated_column_heading as $annotated_value)
                                    if ($string == $annotated_value)
                                        $annotated_canonical_table_model->correctly_annotated_element_number++;
                            }
                }
            }
    }

    /**
     * Определение кол-ва корректно аннотированных элементов для набора данных T2Dv2.
     *
     * @param $dbpedia_data - данные о метках DBpedia из файла XLSX
     * @param $annotated_canonical_table_model - модель аннотированной таблицы
     */
    public function calculateT2Dv2($dbpedia_data, $annotated_canonical_table_model)
    {
        // Обнуление общего кол-ва ячеек канонической таблицы
        $annotated_canonical_table_model->total_element_number = 0;
        // Обнуление кол-ва аннотированных ячеек канонической таблицы
        $annotated_canonical_table_model->annotated_element_number = 0;
        // Поиск всех значений ячеек столбца с данными "DATA"
        $cell_values = CellValue::find()
            ->where(['annotated_canonical_table' => $annotated_canonical_table_model->id,
                'type' => CellValue::DATA])
            ->all();
        // Обход массива данных с метками DBpedia из исходной канонической таблицы
        foreach ($dbpedia_data as $table_row)
            foreach ($table_row as $value)
                if (!empty($value)) {
                    // Подсчет общего кол-ва ячеек канонической таблицы
                    $annotated_canonical_table_model->total_element_number++;
                    // Разбиение строки таблицы на строковые части
                    $string_array = explode(',', $value);
                    // Удаление кавычек с начала и конца из строковой части - оригинального значения ячейки
                    $source_element = trim($string_array[1], '"');
                    // Обход всех значений ячеек столбца "DATA"
                    foreach ($cell_values as $cell_value)
                        // Если значения ячейки из канонической таблицы и БД совпадают
                        if ($cell_value->name == $source_element) {
                            // Получение первой записи с сущностью кандидатом с самым высоким агрегированным рангом
                            $candidate_entity = CandidateEntity::find()
                                ->where(['cell_value' => $cell_value->id])
                                ->orderBy('aggregated_rank DESC')
                                ->one();
                            // Если выборка сущности кандидата не пустая
                            if (!empty($candidate_entity)) {
                                // Подсчет кол-ва аннотированных ячеек канонической таблицы
                                $annotated_canonical_table_model->annotated_element_number++;
                                // Если сущность кандидат равна сущности из проверочной таблицы
                                if ($candidate_entity->entity == $string_array[0])
                                   // Подсчет кол-ва корректно аннотированных ячеек канонической таблицы
                                   $annotated_canonical_table_model->correctly_annotated_element_number++;
                            }
                        }
            }
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
        $annotated_dataset_model->accuracy = 0;
        $annotated_dataset_model->precision = 0;
        $annotated_dataset_model->recall = 0;
        $annotated_dataset_model->f_score = 0;
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
                    // Получение данных из файла XLSX
                    $data = Excel::import('web/dataset/' . $file_name, [
                        'setFirstRecordAsKeys' => true,
                        'setIndexSheetByName' => true,
                        'getOnlySheet' => ExcelFileForm::CANONICAL_FORM,
                    ]);
                    // Получение данных о метках NER из файла XLSX
                    $ner_data = Excel::import('web/dataset/' . $file_name, [
                        'setFirstRecordAsKeys' => true,
                        'setIndexSheetByName' => true,
                        'getOnlySheet' => ExcelFileForm::NER_TAGS,
                    ]);
                    // Получение данных о метках DBpedia из файла XLSX
                    $dbpedia_data = Excel::import('web/dataset/' . $file_name, [
                        'setFirstRecordAsKeys' => false,
                        'setIndexSheetByName' => true,
                        'getOnlySheet' => ExcelFileForm::DBPEDIA_TAGS,
                    ]);
                    // Получение имени файла без расширения
                    $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME );
                    // Сохранение информации об аннотированной канонической таблице в БД
                    $annotated_canonical_table_model = new AnnotatedCanonicalTable();
                    $annotated_canonical_table_model->name = $file_name_without_extension;
                    $annotated_canonical_table_model->total_element_number = 0;
                    $annotated_canonical_table_model->annotated_element_number = 0;
                    $annotated_canonical_table_model->correctly_annotated_element_number = 0;
                    $annotated_canonical_table_model->accuracy = 0;
                    $annotated_canonical_table_model->precision = 0;
                    $annotated_canonical_table_model->recall = 0;
                    $annotated_canonical_table_model->f_score = 0;
                    $annotated_canonical_table_model->runtime = 0;
                    $annotated_canonical_table_model->annotated_dataset = $annotated_dataset_model->id;
                    $annotated_canonical_table_model->save();

                    // Создание объекта аннотатора таблиц
                    $annotator = new CanonicalTableAnnotator();
                    // Аннотирование столбца "DATA"
                    $annotator->annotateTableData($data, $ner_data, $annotated_canonical_table_model->id);
                    // Аннотирование столбца "RowHeading"
                    $annotator->annotateTableHeading($data, $ner_data,
                        CanonicalTableAnnotator::ROW_HEADING_TITLE, $annotated_canonical_table_model->id);
                    // Аннотирование столбца "ColumnHeading"
                    $annotator->annotateTableHeading($data, $ner_data,
                        CanonicalTableAnnotator::COLUMN_HEADING_TITLE, $annotated_canonical_table_model->id);

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
                                        // Получение первой записи с сущностью кандидатом с самым высоким агрегированным рангом
                                        $candidate_entity = CandidateEntity::find()
                                            ->where(['cell_value' => $cell_value->id])
                                            ->orderBy('aggregated_rank DESC')
                                            ->one();
                                        // Если выборка сущности кандидата не пустая
                                        if (!empty($candidate_entity)) {
                                            // Подсчет кол-ва аннотированных ячеек канонической таблицы
                                            $annotated_canonical_table_model->annotated_element_number++;
                                            // Присвоение значению ячейки референтной сущности кандидата
                                            $annotated_row_model->data = $candidate_entity->entity;
                                        }
                                    }
                            }
                            if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE) {
                                $string_array = explode(" | ", $value);
                                foreach ($string_array as $key => $string) {
                                    $existing_entity = '';
                                    // Если есть значение в ячейке
                                    if ($value != '')
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
                                    // Если есть значение в ячейке
                                    if ($value != '')
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
                                            // Подсчет кол-ва аннотированных ячеек канонической таблицы
                                            $annotated_canonical_table_model->annotated_element_number++;
                                            // Запоминание референтной сущности кандидата
                                            $existing_entity = $candidate_entity->entity;
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
                            'row_heading' => 'RowHeading',
                            'column_heading' => 'ColumnHeading'
                        ],
                    ]);
                    // Вычисление правильности (accuracy)
                    $annotated_canonical_table_model->accuracy =
                        $annotated_canonical_table_model->annotated_element_number /
                        $annotated_canonical_table_model->total_element_number;
                    // Определение кол-ва аннотированных элементов для набора данных Troy200
                    //$this->calculateTroy200($dbpedia_data, $all_annotated_rows, $annotated_canonical_table_model);
                    // Определение кол-ва аннотированных элементов для набора данных T2Dv2
                    $this->calculateT2Dv2($dbpedia_data, $annotated_canonical_table_model);
                    // Вычисление точности (precision)
                    $annotated_canonical_table_model->precision =
                        $annotated_canonical_table_model->correctly_annotated_element_number /
                        $annotated_canonical_table_model->annotated_element_number;
                    // Вычисление полноты (recall)
                    $annotated_canonical_table_model->recall =
                        $annotated_canonical_table_model->correctly_annotated_element_number /
                        $annotated_canonical_table_model->total_element_number;
                    // Вычисление F-меры (F1 score)
                    $annotated_canonical_table_model->f_score = (2 * $annotated_canonical_table_model->precision *
                            $annotated_canonical_table_model->recall) / ($annotated_canonical_table_model->precision +
                            $annotated_canonical_table_model->recall);
                    // Запись времени обработки таблицы
                    $annotated_canonical_table_model->runtime = round(microtime(true) -
                        $table_runtime, 4);
                    // Обновление полей в БД
                    $annotated_canonical_table_model->updateAttributes(['total_element_number',
                        'annotated_element_number', 'correctly_annotated_element_number',
                        'accuracy', 'precision', 'recall', 'f_score', 'runtime']);
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