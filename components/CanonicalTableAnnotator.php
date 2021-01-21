<?php

namespace app\components;

use Yii;
use Exception;
use vova07\console\ConsoleRunner;
use BorderCloud\SPARQL\SparqlClient;
use app\modules\main\models\CellValue;
use app\modules\main\models\HeadingRank;
use app\modules\main\models\ParentClass;
use app\modules\main\models\NerClassRank;
use app\modules\main\models\EntityContext;
use app\modules\main\models\CandidateEntity;
use app\modules\main\models\RelationshipRank;
use app\modules\main\models\ContextSimilarity;
use app\modules\main\models\SemanticSimilarity;

/**
 * CanonicalTableAnnotator - класс семантического интерпретатора (аннотатора) данных канонических электронных таблиц.
 *
 * @package app\components
 */
class CanonicalTableAnnotator
{
    // Названия меток NER-аннотатора Stanford NLP
    const NUMBER_NER_LABEL       = 'NUMBER';
    const DATE_NER_LABEL         = 'DATE';
    const TIME_NER_LABEL         = 'TIME';
    const MONEY_NER_LABEL        = 'MONEY';
    const PERCENT_NER_LABEL      = 'PERCENT';
    const NONE_NER_LABEL         = 'NONE';
    const LOCATION_NER_LABEL     = 'LOCATION';
    const PERSON_NER_LABEL       = 'PERSON';
    const ORGANIZATION_NER_LABEL = 'ORGANIZATION';
    const MISC_NER_LABEL         = 'MISC';
    const ORDINAL_NER_LABEL      = 'ORDINAL';

    // Названия (адреса) классов и экземпляров классов в онтологии DBpedia соответствующие меткам NER
    const LOCATION_ONTOLOGY_CLASS     = 'http://dbpedia.org/ontology/Location';
    const PERSON_ONTOLOGY_CLASS       = 'http://dbpedia.org/ontology/Person';
    const ORGANISATION_ONTOLOGY_CLASS = 'http://dbpedia.org/ontology/Organisation';
    const NUMBER_ONTOLOGY_INSTANCE    = 'http://dbpedia.org/resource/Number';
    const MONEY_ONTOLOGY_INSTANCE     = 'http://dbpedia.org/resource/Money';
    const PERCENT_ONTOLOGY_INSTANCE   = 'http://dbpedia.org/resource/Percent';
    const DATE_ONTOLOGY_INSTANCE      = 'http://dbpedia.org/resource/Date';
    const TIME_ONTOLOGY_INSTANCE      = 'http://dbpedia.org/resource/Time';

    const ENDPOINT_NAME  = 'https://dbpedia.org/sparql'; //'http://192.168.19.127:8890/sparql'; // Название точки доступа SPARQL
    const GRAPH_IRI_NAME = '<http://dbpedia.org>'; //'<http://localhost:8890/DBPedia>';       // Название графа знаний

    const DBPEDIA_ONTOLOGY_SECTION = 'http://dbpedia.org/ontology/'; // Название (адрес) сегмента онтологии DBpedia
    const DBPEDIA_RESOURCE_SECTION = 'http://dbpedia.org/resource/'; // Название (адрес) сегмента ресурсов DBpedia
    const DBPEDIA_PROPERTY_SECTION = 'http://dbpedia.org/property/'; // Название (адрес) сегмента свойств DBpedia

    const DATA_TITLE           = 'DATA';          // Имя первого заголовка столбца канонической таблицы
    const ROW_HEADING_TITLE    = 'RowHeading';    // Имя второго заголовка столбца канонической таблицы
    const COLUMN_HEADING_TITLE = 'ColumnHeading'; // Имя третьего заголовка столбца канонической таблицы

    /**
     * Удаление каталога со всеми файлами.
     *
     * @param $path - путь до каталога с файлами
     */
    private static function removeDirectory($path) {
        if ($objs = glob($path . '/*')) {
            foreach($objs as $obj) {
                is_dir($obj) ? self::removeDirectory($obj) : unlink($obj);
            }
        }
        rmdir($path);
    }

    /**
     * Кодирование в имени файла запрещенных символов.
     *
     * @param $file_name - исходное имя файла
     * @return mixed - имя файла с закодированными запрещенными символами
     */
    public static function encodeFileName($file_name) {
        $encoded_file_name = str_replace('\\', '+SS-LS+', $file_name);
        $encoded_file_name = str_replace('/', '+SS-RS+', $encoded_file_name);
        $encoded_file_name = str_replace('*', '+SS-S+', $encoded_file_name);
        $encoded_file_name = str_replace('?', '+SS-Q+', $encoded_file_name);
        $encoded_file_name = str_replace(':', '+SS-C+', $encoded_file_name);
        $encoded_file_name = str_replace('"', '+SS-QM+', $encoded_file_name);
        $encoded_file_name = str_replace('<', '+SS-LB+', $encoded_file_name);
        $encoded_file_name = str_replace('>', '+SS-RB+', $encoded_file_name);
        $encoded_file_name = str_replace('|', '+SS-VL+', $encoded_file_name);

        return $encoded_file_name;
    }

    /**
     * Декодирование в имени файла запрещенных символов.
     *
     * @param $encoded_file_name - имя файла с закодированными запрещенными символами
     * @return mixed - исходное имя файла
     */
    public static function decodeFileName($encoded_file_name) {
        $source_file_name = str_replace('+SS-LS+', '\\', $encoded_file_name);
        $source_file_name = str_replace('+SS-RS+', '/', $source_file_name);
        $source_file_name = str_replace('+SS-S+', '*', $source_file_name);
        $source_file_name = str_replace('+SS-Q+', '?', $source_file_name);
        $source_file_name = str_replace('+SS-C+', ':', $source_file_name);
        $source_file_name = str_replace('+SS-QM+', '"', $source_file_name);
        $source_file_name = str_replace('+SS-LB+', '<', $source_file_name);
        $source_file_name = str_replace('+SS-RB+', '>', $source_file_name);
        $source_file_name = str_replace('+SS-VL+', '|', $source_file_name);

        return $source_file_name;
    }

    /**
     * Получение нормализованной текстовой записи: удаление всех символов, кроме букв, цифр и пробелов,
     * удаление лишних пробелов, замена одинарных пробелов знаком "_".
     *
     * @param $entry - текстовая запись (значение в ячейке таблицы)
     * @return mixed|string|string[]|null - нормализованное текстовое значение для SPARQL-запросов
     */
    private static function getNormalizedEntry($entry)
    {
        // Удаление всех символов кроме букв и цифр из строки
        $normalized_entry = preg_replace ('/[^a-zA-Zа-яА-Я0-9\s]/si','', $entry);
        // Изменение множественных пробелов на один
        $normalized_entry = preg_replace('/[^\S\r\n]+/', ' ', $normalized_entry);
        $normalized_entry = preg_replace('/^(?![\r\n]\s)+|(?![\r\n]\s)+$/m', ' ',
            $normalized_entry);
        // Удаление пробелов из начала и конца строки
        $normalized_entry = trim($normalized_entry);
        // Перевод первой буквы строки в заглавную, все остальные приводятся в нижний регистр
        $normalized_entry = ucfirst(mb_strtolower($normalized_entry));
        // Замена пробелов знаком нижнего подчеркивания
        $normalized_entry = str_replace(' ', '_', $normalized_entry);

        return $normalized_entry;
    }

    /**
     * Поиск и формирование массива сущностей кандидатов.
     *
     * @param $value - значение для поиска сущностей кандидатов по вхождению
     * @param $section - название сегмента в онтологии DBpedia
     * @return bool|array - массив с результатми поиска сущностей кандидатов в онтологии DBpedia
     */
    public function getCandidateEntities($value, $section = '')
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
        // Если указано название сегмента в онтологии DBpedia
        if ($section != '')
            // SPARQL-запрос к DBpedia для поиска сущностей кандидатов из определенного сегмента
            $query = "SELECT ?subject ?property ?object
                FROM " . CanonicalTableAnnotator::GRAPH_IRI_NAME . "
                WHERE {
                    ?subject ?property ?object . FILTER contains(str(?subject), '$value') .
                    FILTER(strstarts(str(?subject), '$section'))
                } LIMIT 100";
        else
            // SPARQL-запрос к DBpedia для поиска сущностей кандидатов
            $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                PREFIX db: <http://dbpedia.org/resource/>
                PREFIX owl: <http://www.w3.org/2002/07/owl#>
                SELECT ?subject rdf:type ?object
                FROM " . CanonicalTableAnnotator::GRAPH_IRI_NAME . "
                WHERE { ?subject a ?object . FILTER ( contains(str(?subject), '$value') &&
                    ( (strstarts(str(?subject), str(dbo:)) && (str(?object) = str(owl:Class))) ||
                    (strstarts(str(?subject), str(db:)) && (str(?object) = str(owl:Thing))) ) )
            } LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows']) {
            // Формирование массива сущностей кандидатов
            $candidate_entities = array();
            foreach ($rows['result']['rows'] as $row)
                array_push($candidate_entities, $row['subject']);

            return $candidate_entities;
        } else
            return false;
    }

    /**
     * Поиск и формирование массива родительских классов для сущности кандидата.
     *
     * @param $entity - сущность кандидат
     * @return array|bool - массив с результатми поиска родительских классов для сущности кандидата в онтологии DBpedia
     */
    public function getParentClasses($entity)
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia ontology для поиска родительских классов для сущности
        $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
            SELECT ?class
            FROM " . CanonicalTableAnnotator::GRAPH_IRI_NAME . "
            WHERE {
                <$entity> ?property ?class . FILTER (strstarts(str(?class), str(dbo:)))
            } LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows']) {
            // Формирование массива родительских классов
            $parent_classes = array();
            foreach ($rows['result']['rows'] as $row)
                array_push($parent_classes, $row['class']);

            return $parent_classes;
        } else
            return false;
    }

    /**
     * Вычисление расстояния Левенштейна между входным значением и сущностями кандидатов.
     *
     * @param $input_value - входное значение (слово)
     * @param $candidate_entities - массив объектов сущностей кандидатов из БД
     */
    public function getLevenshteinDistance($input_value, $candidate_entities)
    {
        // Массив названий URI DBpedia
        $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION, self::DBPEDIA_PROPERTY_SECTION);
        // Обход всех сущностей кандидатов из набора
        foreach ($candidate_entities as $candidate_entity) {
            // Удаление адреса URI у сущности кандидата
            $candidate_entity_name = str_replace($URIs, '', $candidate_entity->entity);
            // Вычисление расстояния Левенштейна между входным значением и текущим названием сущности кандидата
            $distance = levenshtein($input_value, $candidate_entity_name);
            // Сохранение значения оценки расстояния Левенштейна в БД для текущей сущности кандидата
            $candidate_entity->levenshtein_distance = $distance;
            $candidate_entity->updateAttributes(['levenshtein_distance']);
        }
    }

    /**
     * Нахождение связей между сущностями кандидатами.
     *
     * @param $heading_title - имя столбца
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     */
    public function getRelationshipRank($heading_title, $canonical_table_id)
    {
        $path = '';
        $cell_values = array();
        if ($heading_title == self::ROW_HEADING_TITLE) {
            // Путь до папки с результатами аннотирования ячеек столбца с заголовками "RowHeading"
            $path = 'web/results/row_heading_result/';
            // Создание директории для хранения результатов аннотирования ячеек столбца с заголовками "RowHeading"
            if (!file_exists($path))
                mkdir(Yii::$app->basePath . '\web\results\row_heading_result', 0777);
            // Поиск всех значений ячеек для столбца с заголовками "RowHeading"
            $cell_values = CellValue::find()
                ->where(['type' => CellValue::ROW_HEADING, 'annotated_canonical_table' => $canonical_table_id])
                ->all();
        }
        if ($heading_title == self::COLUMN_HEADING_TITLE) {
            // Путь до папки с результатами аннотирования ячеек столбца с заголовками "ColumnHeading"
            $path = 'web/results/column_heading_result/';
            // Создание директории для хранения результатов аннотирования ячеек столбца с заголовками "ColumnHeading"
            if (!file_exists($path))
                mkdir(Yii::$app->basePath . '\web\results\column_heading_result', 0777);
            // Поиск всех значений ячеек для столбца с заголовками "ColumnHeading"
            $cell_values = CellValue::find()
                ->where(['type' => CellValue::COLUMN_HEADING, 'annotated_canonical_table' => $canonical_table_id])
                ->all();
        }
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения ячейки в БД
            $candidate_entities = CandidateEntity::find()->where(['cell_value' => $cell_value->id])->all();
            // Кодирование в имени каталога запрещенных символов
            $current_label = self::encodeFileName($cell_value->name);
            // Если не существует каталога для хранения результатов аннотирования таблицы
            if (!file_exists($path . $current_label)) {
                // Создание каталога для хранения результатов аннотирования таблицы
                mkdir(Yii::$app->basePath . '/' . $path . $current_label, 0777);
            }
            // Открытие файла на запись для сохранения сущностей кандидатов
            $result_file = fopen($path . $current_label . '.log', 'a');
            // Запись в файл логов результатов аннотирования ячейки с данными
            foreach ($candidate_entities as $candidate_entity) {
                $correct_candidate_entity = str_replace('\/', '/', $candidate_entity->entity);
                fwrite($result_file, $correct_candidate_entity . PHP_EOL);
            }
            // Закрытие файла
            fclose($result_file);
        }
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения ячейки в БД
            $candidate_entities = CandidateEntity::find()->where(['cell_value' => $cell_value->id])->all();
            // Обход всех сущностей кандидатов из выборки
            foreach ($candidate_entities as $candidate_entity) {
                // Параллельное нахождение связей между сущностями кандидатов
                $cr = new ConsoleRunner(['file' => '@app/yii']);
                $cr->run('spreadsheet/find-relationship-rank "' . $cell_value->name .'" "' .
                    $candidate_entity->entity . '" ' . $path);
            }
        }
        // Ожидание формирования всех файлов с оценками
        foreach ($cell_values as $cell_value) {
            // Получение кол-ва сущностей кандидатов для текущего значения ячейки в БД
            $candidate_entity_count = CandidateEntity::find()->where(['cell_value' => $cell_value->id])->count();
            // Кодирование в имени каталога запрещенных символов
            $current_label = self::encodeFileName($cell_value->name);
            while (count(array_diff(scandir($path . $current_label . '/'),
                    array('..', '.'))) != $candidate_entity_count) {
                usleep(1);
            }
        }
        // Обход всех наборов сущностей кандидатов
        foreach ($cell_values as $cell_value) {
            // Кодирование в имени каталога запрещенных символов
            $current_label = self::encodeFileName($cell_value->name);
            // Открытие каталога с файлами
            if ($handle = opendir($path . $current_label . '/')) {
                // Чтения элементов (файлов) каталога
                while (false !== ($file_name = readdir($handle))) {
                    // Если элемент каталога не является каталогом
                    if ($file_name != '.' && $file_name != '..') {
                        // Открытие файла
                        $fp = fopen($path . $current_label . '/' . $file_name, "r");
                        // Чтение файла до конца
                        while (!feof($fp)) {
                            // Чтение строки из файла и удаление переноса строк
                            $text = str_replace("\r\n", '', fgets($fp));
                            // Поиск в строке метки "SPARQL_EXECUTION_TIME"
                            $pos = strrpos($text, 'SPARQL_EXECUTION_TIME: ', 0);
                            // Если текущая строка является оценкой
                            if ($text != '' && $pos === false) {
                                // Получение имени файла без расширения
                                $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME );
                                // Декодирование имени файла
                                $candidate_entity_name = self::decodeFileName($file_name_without_extension);
                                // Добавление пространства имен
                                $candidate_entity_name = $this::DBPEDIA_RESOURCE_SECTION . $candidate_entity_name;
                                // Поиск сущности кандидата по имени для текущего значения ячейки в БД
                                $candidate_entity = CandidateEntity::find()
                                    ->where(['entity' => $candidate_entity_name, 'cell_value' => $cell_value->id])
                                    ->one();
                                // Сохранение оценки близости сущностей кандидатов по связям между друг другом в БД
                                $relationship_rank_model = new RelationshipRank();
                                $relationship_rank_model->rank = (int)$text;
                                $relationship_rank_model->execution_time = 0;
                                $relationship_rank_model->candidate_entity = $candidate_entity->id;
                                $relationship_rank_model->save();
                            }
                            // Если текущая строка является временем выполнения
                            if ($pos !== false)
                                if (!empty($relationship_rank_model)) {
                                    // Извлечение времени выполнения из строки
                                    $rest = substr($text, 23);
                                    // Обновление времени выполнения в БД
                                    $relationship_rank_model->execution_time = (double)$rest;
                                    $relationship_rank_model->updateAttributes(['execution_time']);
                                }
                        }
                        // Закрытие файла
                        fclose($fp);
                    }
                }
                // Закрытие каталога
                closedir($handle);
            }
        }
        // Удаление каталога со всеми файлами
        self::removeDirectory($path);
    }

    /**
     * Получение агрегированных оценок (рангов) сущностей кандидатов для заголовков таблиц.
     *
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     * @param $ld_weight_factor - весовой фактор для определения важности оценки расстояния Левенштейна
     * @param $ncr_weight_factor - весовой фактор для определения важности оценки сходства по классам, определенных NER-метками
     * @param $r_weight_factor - весовой фактор для определения важности оценки по связям
     */
    public function getAggregatedHeadingRanks($canonical_table_id, $ld_weight_factor,
                                              $ncr_weight_factor, $r_weight_factor)
    {
        // Поиск всех значений ячеек для столбцов с заголовками "RowHeading" и "ColumnHeading"
        $cell_values = CellValue::find()
            ->where(['type' => [CellValue::ROW_HEADING, CellValue::COLUMN_HEADING],
                'annotated_canonical_table' => $canonical_table_id])
            ->all();
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения ячейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход всех сущностей кандидатов из выборки
            foreach ($candidate_entities as $candidate_entity) {
                // Нормализация рангов (расстояния) Левенштейна
                $normalized_levenshtein_distance = 1 - $candidate_entity->levenshtein_distance / 100;
                // Поиск оценки (ранга) сходства между сущностью из набора кандидатов и классом, определенного NER-меткой
                $ner_class_rank = NerClassRank::find()
                    ->where(['candidate_entity' => $candidate_entity->id])
                    ->one();
                // Поиск оценки (ранга) связей между сущностями кандидатами для текущей сущности кандидата
                $relationship_rank = RelationshipRank::find()
                    ->where(['candidate_entity' => $candidate_entity->id])
                    ->one();
                // Вычисление агрегированной оценки (ранга)
                $candidate_entity->aggregated_rank = $ld_weight_factor * $normalized_levenshtein_distance +
                    $ncr_weight_factor * $ner_class_rank->rank + $r_weight_factor * 1;//$relationship_rank->rank;
                // Сохранение агрегированной оценки (ранга) в БД
                $candidate_entity->updateAttributes(['aggregated_rank']);
            }
        }
    }

    /**
     * Определение близости сущности кандидата к классу, который задан NER-меткой.
     *
     * @param $ner_label - NER-метка присвоенная значению ячейки в столбце DATA
     * @param $candidate_entity_name - имя сущности кандидата
     * @param $candidate_entity_id - идентификатор сущности кандидата
     */
    public function getNerClassRank($ner_label, $candidate_entity_name, $candidate_entity_id)
    {
        // Определение класса из онтологии DBpedia для соответствующей метки NER
        $ner_class = '';
        if ($ner_label == self::LOCATION_NER_LABEL)
            $ner_class = self::LOCATION_ONTOLOGY_CLASS;
        if ($ner_label == self::PERSON_NER_LABEL)
            $ner_class = self::PERSON_ONTOLOGY_CLASS;
        if ($ner_label == self::ORGANIZATION_NER_LABEL)
            $ner_class = self::ORGANISATION_ONTOLOGY_CLASS;
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для определения глубины связи текущей сущности из набора кандидатов с классом
        $query = "SELECT count(?intermediate)/2 as ?depth
            FROM " . CanonicalTableAnnotator::GRAPH_IRI_NAME . "
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
     * @param $formed_row_heading_labels - массив всех заголовков строки в канонической таблице
     * @param $candidate_entity_name - имя сущности кандидата
     * @param $candidate_entity_id - идентификатор сущности кандидата
     */
    public function getHeadingRank($formed_row_heading_labels, $candidate_entity_name, $candidate_entity_id)
    {
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
        $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
            SELECT ?class
            FROM " . CanonicalTableAnnotator::GRAPH_IRI_NAME . "
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
                $class_name = str_replace(self::DBPEDIA_ONTOLOGY_SECTION, '', $item['class']);
                // Обход всех заголовков в строке таблицы
                foreach ($formed_row_heading_labels as $heading_label) {
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
     * Определение сходства сущностей кандидатов по семантической близости.
     *
     * @param $all_candidate_entities - массив для всех массивов сущностей кандидатов
     * @param $canonical_table_id
     */
    public function getSemanticSimilarityDistance($all_candidate_entities, $canonical_table_id)
    {
        // Сравнение классов сущностей кандидатов между собой
        foreach ($all_candidate_entities as $current_entry_name => $current_candidate_entities) {
            $global_ranked_classes = array();
            foreach ($all_candidate_entities as $comparative_entry_name => $comparative_candidate_entities) {
                if ($current_entry_name != $comparative_entry_name)
                    foreach ($current_candidate_entities as $current_entity_name => $current_parent_classes) {
                        $ranked_classes = array();
                        foreach ($comparative_candidate_entities as $comparative_entity_name => $comparative_parent_classes) {
                            if ($current_parent_classes != false && $comparative_parent_classes != false) {
                                $selected_class = '';
                                $global_min = 100;
                                foreach ($current_parent_classes as $current_parent_class) {
                                    $min_distance = 100;
                                    foreach ($comparative_parent_classes as $comparative_parent_class) {
                                        // Удаление адреса URI у классов
                                        $current_parent_class_name = str_replace(self::DBPEDIA_ONTOLOGY_SECTION,
                                            '', $current_parent_class);
                                        $comparative_parent_class_name = str_replace(self::DBPEDIA_ONTOLOGY_SECTION,
                                            '', $comparative_parent_class);
                                        // Вычисление расстояния Левенштейна между названиями классов
                                        $distance = levenshtein($current_parent_class_name,
                                            $comparative_parent_class_name);
                                        // Определение самого минимального расстояния
                                        if ($min_distance > $distance)
                                            $min_distance = $distance;
                                    }
                                    // Выбор класса с самым минимальным расстоянием Левенштейна
                                    if ($global_min > $min_distance) {
                                        $global_min = $min_distance;
                                        $selected_class = $current_parent_class;
                                    }
                                }
                                // Формирование локального массива ранжированных классов
                                if (array_key_exists($selected_class, $ranked_classes))
                                    $ranked_classes[$selected_class] += 1;
                                else
                                    $ranked_classes[$selected_class] = 1;
                            }
                        }
                        // Формирование глобального массива ранжированных классов для всех сущностей кандидатов
                        if (array_key_exists($current_entity_name, $global_ranked_classes)) {
                            foreach ($ranked_classes as $key_mc => $value_mc)
                                if (array_key_exists($key_mc, $global_ranked_classes[$current_entity_name]))
                                    $global_ranked_classes[$current_entity_name][$key_mc] += $value_mc;
                                else
                                    $global_ranked_classes[$current_entity_name][$key_mc] = $value_mc;
                        } else
                            $global_ranked_classes[$current_entity_name] = $ranked_classes;
                        // Сортировка массива ранжированных классов по убыванию
                        arsort($global_ranked_classes[$current_entity_name]);
                    }
            }
            $max_rank = 0;
            $coefficient = 0;
            // Массив сущностей кандидатов с промежуточными значениями рангов
            $intermediate_candidate_entities = array();
            // Присвоение ранга для каждой сущности кандидата
            foreach ($global_ranked_classes as $candidate_entity => $class_ranks) {
                // Получение первого ранга класса (максимального из всех рангов классов)
                $current_rank = reset($class_ranks);
                // Если ранг не равен 0
                if ($current_rank != 0) {
                    // Формирование массива сущностей кандидатов с промежуточными значениями рангов
                    array_push($intermediate_candidate_entities, [$candidate_entity, $current_rank]);
                    // Запоминание максимального ранга
                    if ($max_rank < $current_rank)
                        $max_rank = $current_rank;
                } else
                    array_push($intermediate_candidate_entities, [$candidate_entity, 0]);
            }
            // Выбор коэффициента
            if ($max_rank < 10 && $max_rank >= 1)
                $coefficient = 10;
            if ($max_rank < 100 && $max_rank >= 10)
                $coefficient = 100;
            if ($max_rank < 1000 && $max_rank >= 100)
                $coefficient = 1000;
            if ($max_rank < 10000 && $max_rank >= 1000)
                $coefficient = 10000;
            // Поиск всех значений ячеек для текущей канонической таблицы в БД
            $cell_values = CellValue::find()
                ->where(['type' => CellValue::DATA, 'annotated_canonical_table' => $canonical_table_id])
                ->all();
            // Обход всех значений ячеек текущей канонической таблицы
            foreach ($cell_values as $cell_value)
                if ($cell_value->name == $current_entry_name) {
                    // Поиск всех сущностей кандидатов для текущего значения чейки в БД
                    $candidate_entities = CandidateEntity::find()
                        ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                        ->all();
                    // Обход всех сущностей кандидатов из выборки
                    foreach ($candidate_entities as $candidate_entity)
                        foreach ($intermediate_candidate_entities as $intermediate_candidate_entity)
                            if ($candidate_entity->entity == $intermediate_candidate_entity[0]) {
                                // Поиск записи о семантической близости для текущей сущности кандидата
                                $semantic_similarity = SemanticSimilarity::find()
                                    ->where(['candidate_entity' => $candidate_entity->id])
                                    ->one();
                                // Расчет результирующего ранга для сущностей кандидатов
                                if ($coefficient != 0)
                                    $semantic_similarity->rank = $intermediate_candidate_entity[1] / $coefficient;
                                else
                                    $semantic_similarity->rank = 0;
                                // Обновление ранга (оценки) сходства сущности кандидата по семантической близости
                                $semantic_similarity->updateAttributes(['rank']);
                            }
                }
        }
    }

    /**
     * Получение контекста для значения ячейки данных из канонической таблицы.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $entry - значение ячейки данных из канонической таблицы
     * @return array - контекст (значения соседних ячеек) для значения ячейки данных из канонической таблицы
     */
    public function getEntryContext($data, $entry)
    {
        // Массив для хранения контекста значения ячейки данных таблицы
        $entry_context = array();
        // Цикл по всем строкам канонической таблицы
        foreach ($data as $row) {
            $current_data_value = '';
            $current_row_heading_value = '';
            $current_column_heading_value = '';
            // Цикл по строкам канонической таблицы
            foreach ($row as $heading => $value) {
                // Запоминание текущего значения ячейки столбца с данными, если оно совпадает со значением из БД
                if ($heading == self::DATA_TITLE && $entry == $value)
                    $current_data_value = $value;
                // Запоминание текущего значения ячейки столбца с заголовком строки
                if ($heading == self::ROW_HEADING_TITLE)
                    $current_row_heading_value = $value;
                // Запоминание текущего значения ячейки столбца с заголовком столбца
                if ($heading == self::COLUMN_HEADING_TITLE)
                    $current_column_heading_value = $value;
            }
            // Если есть текущее значение ячейки столбца с данными
            if ($current_data_value != '') {
                // Цикл по всем строкам канонической таблицы
                foreach ($data as $comparative_row) {
                    $comparative_current_data_value = '';
                    $comparative_current_row_heading_value = '';
                    $comparative_current_column_heading_value = '';
                    // Цикл по строкам канонической таблицы
                    foreach ($comparative_row as $comparative_heading => $comparative_value) {
                        if ($comparative_heading == self::DATA_TITLE)
                            $comparative_current_data_value = $comparative_value;
                        if ($comparative_heading == self::ROW_HEADING_TITLE)
                            $comparative_current_row_heading_value = $comparative_value;
                        if ($comparative_heading == self::COLUMN_HEADING_TITLE)
                            $comparative_current_column_heading_value = $comparative_value;
                    }
                    // Определение контекста значения ячейки данных таблицы
                    if (($current_row_heading_value == $comparative_current_row_heading_value &&
                            $current_row_heading_value != '') ||
                        ($current_column_heading_value == $comparative_current_column_heading_value &&
                            $current_column_heading_value != '')) {
                        array_push($entry_context, $comparative_current_data_value);
                    }
                }
            }
        }

        return $entry_context;
    }

    /**
     * Получение контекста для сущности кандидата.
     *
     * @param $candidate_entity - сущность кандидат
     * @return array|bool - контекст (все субъекты и объекты, связанные с сущностью кандидатом) для сущности кандидата
     */
    public function getEntityContext($candidate_entity)
    {
        // Массив для хранения контекста сущности нандидата
        $entity_context = array();
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для поиска всех триплетов связанных с сущностью кандидатом
        $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
            PREFIX dbr: <http://dbpedia.org/resource/>
            SELECT ?subject ?object
            FROM " . CanonicalTableAnnotator::GRAPH_IRI_NAME . "
            WHERE {
                { <$candidate_entity> ?property ?object .
                    FILTER(strstarts(str(?object), str(dbo:)) || strstarts(str(?object), str(dbr:))) .
                    FILTER(strstarts(str(?property), str(dbo:)) || strstarts(str(?property), str(dbr:)))
                } UNION { ?subject ?property <$candidate_entity> .
                    FILTER(strstarts(str(?subject), str(dbo:)) || strstarts(str(?subject), str(dbr:))) .
                    FILTER(strstarts(str(?property), str(dbo:)) || strstarts(str(?property), str(dbr:)))
                }
            }";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows']) {
            // Формирование массива
            foreach ($rows['result']['rows'] as $row) {
                if (array_key_exists('subject', $row))
                    array_push($entity_context, $row['subject']);
                if (array_key_exists('object', $row))
                    array_push($entity_context, $row['object']);
            }

            return $entity_context;
        } else
            return false;
    }

    /**
     * Определение сходства сущностей кандидатов по контексту упоминания сущности.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $cell_values - все значения ячеек данных из канонической таблицы
     */
    public function getContextSimilarity($data, $cell_values)
    {
        // Массив названий URI DBpedia
        $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION);

        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход сущностей кандидатов
            foreach ($candidate_entities as $candidate_entity) {
                // Параллельное определение контекста текущей сущности кандидата
                $cr = new ConsoleRunner(['file' => '@app/yii']);
                $cr->run('spreadsheet/get-entity-context "' . $candidate_entity->entity . '" ' .
                    $candidate_entity->id);
            }
        }
        // Ожидание формирования контекста сущностей из набора кандидатов
        foreach ($cell_values as $cell_value) {
            $context_similarity_count = 0;
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            $candidate_entity_count = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->count();
            while ($context_similarity_count != $candidate_entity_count) {
                $context_similarity_count = 0;
                foreach ($candidate_entities as $candidate_entity)
                    $context_similarity_count += ContextSimilarity::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->count();
                sleep(1);
            }
        }
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Если выборка сущностей кандидатов не пустая
            if (!empty($candidate_entities)) {
                // Определение контекста текущего значения ячейки данных таблицы
                $current_entry_context = $this->getEntryContext($data, $cell_value->name);
                // Обход сущностей кандидатов
                foreach ($candidate_entities as $candidate_entity) {
                    // Поиск всех сущностей определяющих контекст для текущей сущности кандидата в БД
                    $current_entity_context = EntityContext::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->all();
                    // Установка первоначального ранга
                    $rank = 0;
                    // Сравнение контекста значения ячейки с контекстом сущности кандидата
                    foreach ($current_entry_context as $entry_name)
                        foreach ($current_entity_context as $entity) {
                            // Удаление адреса URI у сущности кандидата
                            $entity_name = str_replace($URIs, '', $entity->context);
                            try {
                                // Если названия концептов из двух контекстов совпадают (расстояние Левенштейна между ними = 0)
                                if (levenshtein($entry_name, $entity_name) == 0)
                                    // Инкремент текущего ранга
                                    $rank += 1;
                            } catch (Exception $e) {
                                //false;
                            }
                        }
                    // Поиск в сходства контекста для текущей сущности кандидата в БД
                    $context_similarity = ContextSimilarity::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->one();
                    // Обновление ранга (оценки) сходства сущностей кандидатов по контексту упоминания сущности
                    $context_similarity->rank = (int)$rank;
                    $context_similarity->updateAttributes(['rank']);
                }
            }
        }
    }

    /**
     * Получение агрегированных оценок (рангов) сущностей кандидатов для столбца с данными (именоваными сущностями).
     *
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     * @param $ld_weight_factor - весовой фактор для определения важности оценки расстояния Левенштейна
     * @param $ncr_weight_factor - весовой фактор для определения важности оценки сходства по классам, определенных NER-метками
     * @param $hr_weight_factor - весовой фактор для определения важности оценки сходства по заголовкам
     * @param $cs_weight_factor - весовой фактор для определения важности оценки сходства по семантической близости
     * @param $ss_weight_factor - весовой фактор для определения важности оценки сходства по контексту упоминания сущности
     */
    public function getAggregatedNamedEntityRanks($canonical_table_id, $ld_weight_factor, $ncr_weight_factor,
                                                  $hr_weight_factor, $cs_weight_factor, $ss_weight_factor)
    {
        // Поиск всех значений ячеек для столбца с данными "DATA"
        $cell_values = CellValue::find()
            ->where(['type' => CellValue::DATA, 'annotated_canonical_table' => $canonical_table_id])
            ->all();
        // Обход всех значений ячеек текущей канонической таблицы для столбца с данными "DATA"
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения ячейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход всех сущностей кандидатов из выборки
            foreach ($candidate_entities as $candidate_entity) {
                // Нормализация рангов (расстояния) Левенштейна
                $normalized_levenshtein_distance = 1 - $candidate_entity->levenshtein_distance / 100;
                // Поиск оценки (ранга) сходства между сущностью из набора кандидатов и классом, определенного NER-меткой
                $ner_class_rank = NerClassRank::find()
                    ->where(['candidate_entity' => $candidate_entity->id])
                    ->one();
                // Поиск оценки (ранга) сходства между сущностью из набора кандидатов по заголовкам
                $heading_rank = HeadingRank::find()
                    ->where(['candidate_entity' => $candidate_entity->id])
                    ->one();
                // Нормализация рангов (расстояния) Левенштейна
                $normalized_heading_rank = 1 - $heading_rank->rank / 100;
                // Поиск оценки (ранга) сходства между сущностями из набора кандидатов по контексту упоминания сущности
                $context_similarity_rank = ContextSimilarity::find()
                    ->where(['candidate_entity' => $candidate_entity->id])
                    ->one();
                // Поиск оценки (ранга) сходства между сущностями из набора кандидатов по семантической близости
                $semantic_similarity_rank = SemanticSimilarity::find()
                    ->where(['candidate_entity' => $candidate_entity->id])
                    ->one();
                // Вычисление агрегированной оценки (ранга)
                $candidate_entity->aggregated_rank = $ld_weight_factor * $normalized_levenshtein_distance +
                    $ncr_weight_factor * $ner_class_rank->rank + $hr_weight_factor * $normalized_heading_rank +
                    $cs_weight_factor * $context_similarity_rank->rank +
                    $ss_weight_factor * $semantic_similarity_rank->rank;
                // Сохранение агрегированной оценки (ранга) в БД
                $candidate_entity->updateAttributes(['aggregated_rank']);
            }
        }
    }

    /**
     * Аннотирование столбцов "RowHeading" и "ColumnHeading" содержащих значения заголовков исходной таблицы.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_data - данные с NER-метками
     * @param $heading_title - имя столбца
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     */
    public function annotateTableHeading($data, $ner_data, $heading_title, $canonical_table_id)
    {
        // Массив неповторяющихся корректных значений заголовков столбцов
        $formed_heading_labels = array();
        // Массив NER-меток для корректных значений заголовков столбцов
        $formed_ner_labels = array();
        // Формирование массива неповторяющихся корректных значений для заголовков столбцов
        foreach ($data as $row_number => $item)
            foreach ($item as $heading => $value)
                if ($heading == $heading_title) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $string) {
                        if (self::getNormalizedEntry($string) != '') {
                            // Формирование массива корректных значений заголовков столбцов для поиска сущностей кандидатов
                            $formed_heading_labels[strval($string)] = self::getNormalizedEntry($string);
                            // Цикл по всем ячейкам столбца с NER-метками
                            foreach ($ner_data as $ner_row_number => $ner_row)
                                foreach ($ner_row as $ner_key => $ner_value)
                                    if ($ner_key == $heading_title) {
                                        // Формирование массива соответсвий NER-меток значениям столбца с данными
                                        if ($row_number == $ner_row_number) {
                                            $ner_string_array = explode(" | ", $ner_value);
                                            foreach ($ner_string_array as $ner_string)
                                                $formed_ner_labels[strval($string)] = $ner_string;
                                        }
                                    }
                        }
                    }
                }
        // Обход массива корректных значений заголовков столбцов для поиска классов и концептов
        foreach ($formed_heading_labels as $key => $heading_label) {
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                if ($key === $ner_key) {
                    // NER-метка по умолчанию
                    $ner_resource = self::NONE_NER_LABEL;
                    // Определение объекта из онтологии DBpedia для соответствующей метки NER
                    if ($ner_label == self::NUMBER_NER_LABEL)
                        $ner_resource = self::NUMBER_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::PERCENT_NER_LABEL)
                        $ner_resource = self::PERCENT_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::MONEY_NER_LABEL)
                        $ner_resource = self::MONEY_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::DATE_NER_LABEL)
                        $ner_resource = self::DATE_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::TIME_NER_LABEL)
                        $ner_resource = self::TIME_ONTOLOGY_INSTANCE;
                    // Объект для запуска на консоль приложений в фоновом режиме
                    $cr = new ConsoleRunner(['file' => '@app/yii']);
                    // Выбор стратегии аннотирования данных
                    if ($ner_resource == self::NONE_NER_LABEL)
                        // Параллельное формирование сущностей кандидатов
                        $cr->run('spreadsheet/get-candidate-entities "' . $heading_label . '" "' .
                            $key . '" ' . $heading_title . ' ' . $canonical_table_id);
                    else
                        // Параллельное аннотирование ячеек с литеральными данными
                        $cr->run('spreadsheet/annotate-literal-cell "' . $ner_resource . '" "' .
                            $key . '" ' . $heading_title . ' ' . $canonical_table_id);
                }
        }
        // Ожидание формирования сущностей кандидатов в БД
        $cell_value_count = 0;
        while ($cell_value_count != count($formed_heading_labels)) {
            if ($heading_title == self::ROW_HEADING_TITLE)
                $cell_value_count = CellValue::find()
                    ->where(['type' => CellValue::ROW_HEADING, 'annotated_canonical_table' => $canonical_table_id])
                    ->count();
            if ($heading_title == self::COLUMN_HEADING_TITLE)
                $cell_value_count = CellValue::find()
                    ->where(['type' => CellValue::COLUMN_HEADING, 'annotated_canonical_table' => $canonical_table_id])
                    ->count();
            sleep(1);
        }
        // Поиск всех значений ячеек для текущей канонической таблицы в БД
        $cell_values = array();
        if ($heading_title == self::ROW_HEADING_TITLE)
            $cell_values = CellValue::find()
                ->where(['type' => CellValue::ROW_HEADING, 'annotated_canonical_table' => $canonical_table_id])
                ->all();
        if ($heading_title == self::COLUMN_HEADING_TITLE)
            $cell_values = CellValue::find()
                ->where(['type' => CellValue::COLUMN_HEADING, 'annotated_canonical_table' => $canonical_table_id])
                ->all();
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Вычисление расстояния Левенштейна для каждой сущности из набора кандидатов
            $this->getLevenshteinDistance(self::getNormalizedEntry($cell_value->name), $candidate_entities);
        }
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход массива корректных значений NER-меток для столбца данных
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                // Если значение ячейки равно ключу из массива с NER-метками
                if ($cell_value->name == $ner_key)
                    // Обход всех сущностей кандидатов из выборки
                    foreach ($candidate_entities as $candidate_entity) {
                        // Параллельное вычисление сходства между сущностью из набора кандидатов и классом,
                        // определенного NER-меткой
                        $cr = new ConsoleRunner(['file' => '@app/yii']);
                        $cr->run('spreadsheet/get-ner-class-rank "' . $ner_label . '" "' .
                            $candidate_entity->entity . '" ' . $candidate_entity->id);
                    }
        }
        // Ожидание формирования оценок сходства между сущностью из набора кандидатов и классом, определенного NER-меткой
        foreach ($cell_values as $cell_value) {
            $ner_class_count = 0;
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            $candidate_entity_count = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->count();
            while ($ner_class_count != $candidate_entity_count) {
                $ner_class_count = 0;
                foreach ($candidate_entities as $candidate_entity)
                    $ner_class_count += NerClassRank::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->count();
                sleep(1);
            }
        }
        // Нахождение связей между сущностями кандидатов
        //$this->getRelationshipRank($heading_title, $canonical_table_id);
        // Агрегирование оценок (рангов) для сущностей кандидатов и сохранение их в БД
        $this->getAggregatedHeadingRanks($canonical_table_id, 1, 1, 1);
    }

    /**
     * Аннотирование содержимого столбца "DATA".
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_data - данные с NER-метками
     * @param $canonical_table_id - идентификатор канонической таблицы в БД
     */
    public function annotateTableData($data, $ner_data, $canonical_table_id)
    {
        $formed_data_entries = array();
        $formed_ner_labels = array();
        $formed_heading_labels = array();
        // Цикл по всем ячейкам канонической таблицы
        foreach ($data as $row_number => $row) {
            $current_data_value = '';
            $heading_labels = array();
            foreach ($row as $heading => $value) {
                // Если столбец с данными и значение ячейки не пустое
                if ($heading == self::DATA_TITLE && $value != '' && self::getNormalizedEntry($value) != '') {
                    // Формирование массива корректных значений ячеек столбца с данными
                    $formed_data_entries[strval($value)] = self::getNormalizedEntry($value);
                    // Запоминание текущего значения ячейки столбца с данными
                    $current_data_value = strval($value);
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_data as $ner_row_number => $ner_row)
                        foreach ($ner_row as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                // Формирование массива соответсвий NER-меток значениям столбца с данными
                                if ($row_number == $ner_row_number)
                                    $formed_ner_labels[strval($value)] = $ner_value;
                            }
                }
                // Если столбцы с заголовками
                if ($heading == self::ROW_HEADING_TITLE || $heading == self::COLUMN_HEADING_TITLE) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $key => $string)
                        if (self::getNormalizedEntry($string) != '')
                            // Формирование массива корректных значений ячеек заголовков для строки
                            array_push($heading_labels, self::getNormalizedEntry($string));
                }
            }
            // Формирование массива корректных значений ячеек заголовков таблицы
            $formed_heading_labels[$current_data_value] = $heading_labels;
        }
        print_r('Формирование набора сущностей кандидатов...' . PHP_EOL);
        // Обход массива корректных значений столбца данных для поиска референтных сущностей
        foreach ($formed_data_entries as $data_key => $entry)
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                if ($data_key == $ner_key) {
                    // NER-метка по умолчанию
                    $ner_resource = self::NONE_NER_LABEL;
                    // Определение объекта из онтологии DBpedia для соответствующей метки NER
                    if ($ner_label == self::NUMBER_NER_LABEL)
                        $ner_resource = self::NUMBER_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::PERCENT_NER_LABEL)
                        $ner_resource = self::PERCENT_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::MONEY_NER_LABEL)
                        $ner_resource = self::MONEY_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::DATE_NER_LABEL)
                        $ner_resource = self::DATE_ONTOLOGY_INSTANCE;
                    if ($ner_label == self::TIME_NER_LABEL)
                        $ner_resource = self::TIME_ONTOLOGY_INSTANCE;
                    // Объект для запуска на консоль приложений в фоновом режиме
                    $cr = new ConsoleRunner(['file' => '@app/yii']);
                    // Выбор стратегии аннотирования данных
                    if ($ner_resource == self::NONE_NER_LABEL)
                        // Параллельное формирование сущностей кандидатов
                        $cr->run('spreadsheet/get-candidate-entities "' . $entry . '" "' .
                            $data_key . '" ' . self::DATA_TITLE . ' ' . $canonical_table_id);
                    else {
                        // Сохранение значения ячейки канонической таблицы в БД
                        $cell_value_model = new CellValue();
                        $cell_value_model->name = strval($data_key);
                        $cell_value_model->type = CellValue::DATA;
                        $cell_value_model->execution_time = 0;
                        $cell_value_model->annotated_canonical_table = $canonical_table_id;
                        $cell_value_model->save();
                        // Сохранение найденной сущности кандидата в БД
                        $candidate_entity_model = new CandidateEntity();
                        $candidate_entity_model->entity = $ner_resource;
                        $candidate_entity_model->aggregated_rank = 1;
                        $candidate_entity_model->cell_value = $cell_value_model->id;
                        $candidate_entity_model->save();
//                        // Параллельное аннотирование ячеек с литеральными данными
//                        $cr->run('spreadsheet/annotate-literal-cell "' . $ner_resource . '" "' .
//                            $data_key . '" ' . self::DATA_TITLE . ' ' . $canonical_table_id);
                    }
                }
        // Ожидание формирования сущностей кандидатов в БД
        $cell_value_count = 0;
        while ($cell_value_count != count($formed_data_entries)) {
            $cell_value_count = CellValue::find()
                ->where(['type' => CellValue::DATA, 'annotated_canonical_table' => $canonical_table_id])
                ->count();
            sleep(1);
//            if ($cell_value_count == 642 && $goo == false) {
//                $cell_values = CellValue::find()
//                    ->where(['type' => CellValue::DATA, 'annotated_canonical_table' => $canonical_table_id])
//                    ->all();
//                foreach ($formed_data_entries as $data_key => $entry) {
//                    $foo = false;
//                    foreach ($cell_values as $cell_value)
//                        if ($data_key === $cell_value->name)
//                            $foo = true;
//                    if (!$foo)
//                        print_r('Вот это значение: ' . $entry . PHP_EOL);
//                }
//                print_r('Update....' . PHP_EOL);
//                $goo = true;
//            }
        }
        print_r('Вычисление расстояния Левенштейна для сущностей кандидатов...' . PHP_EOL);
        // Поиск всех значений ячеек для текущей канонической таблицы в БД
        $cell_values = CellValue::find()
            ->where(['type' => CellValue::DATA, 'annotated_canonical_table' => $canonical_table_id])
            ->all();
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Вычисление расстояния Левенштейна для каждой сущности из набора кандидатов
            $this->getLevenshteinDistance(self::getNormalizedEntry($cell_value->name), $candidate_entities);
        }
        print_r('Вычисление сходства по NER-меткам...' . PHP_EOL);
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value)
            // Обход массива корректных значений NER-меток для столбца данных
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                // Если значение ячейки равно ключу из массива с NER-метками
                if ($cell_value->name === $ner_key) {
                    // Поиск всех сущностей кандидатов для текущего значения чейки в БД
                    $candidate_entities = CandidateEntity::find()
                        ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                        ->all();
                    // Обход всех сущностей кандидатов из выборки
                    foreach ($candidate_entities as $candidate_entity) {
                        // Параллельное вычисление сходства между сущностью из набора кандидатов и классом,
                        // определенного NER-меткой
                        $cr = new ConsoleRunner(['file' => '@app/yii']);
                        $cr->run('spreadsheet/get-ner-class-rank "' . $ner_label . '" "' .
                            $candidate_entity->entity . '" ' . $candidate_entity->id);
                    }
                }
        // Ожидание формирования оценок сходства между сущностью из набора кандидатов и классом, определенного NER-меткой
        foreach ($cell_values as $cell_value) {
            $ner_class_count = 0;
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            $candidate_entity_count = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->count();
            while ($ner_class_count != $candidate_entity_count) {
                $ner_class_count = 0;
                foreach ($candidate_entities as $candidate_entity)
                    $ner_class_count += NerClassRank::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->count();
                sleep(1);
            }
        }
        print_r('Вычисление сходства по заголовкам...' . PHP_EOL);
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход массива всех заголовков таблицы
            foreach ($formed_heading_labels as $heading_key => $formed_row_heading_labels)
                if ($cell_value->name == $heading_key) {
                    // Обход массива со всеми NER-метками
                    foreach ($formed_ner_labels as $ner_key => $ner_label)
                        if ($heading_key === $ner_key) {
                            // NER-метка по умолчанию
                            $ner_resource = self::NONE_NER_LABEL;
                            // Определение объекта из онтологии DBpedia для соответствующей метки NER
                            if ($ner_label == self::NUMBER_NER_LABEL)
                                $ner_resource = self::NUMBER_ONTOLOGY_INSTANCE;
                            if ($ner_label == self::PERCENT_NER_LABEL)
                                $ner_resource = self::PERCENT_ONTOLOGY_INSTANCE;
                            if ($ner_label == self::MONEY_NER_LABEL)
                                $ner_resource = self::MONEY_ONTOLOGY_INSTANCE;
                            if ($ner_label == self::DATE_NER_LABEL)
                                $ner_resource = self::DATE_ONTOLOGY_INSTANCE;
                            if ($ner_label == self::TIME_NER_LABEL)
                                $ner_resource = self::TIME_ONTOLOGY_INSTANCE;
                            // Если NER-метка равна значению по умолчанию
                            if ($ner_resource == self::NONE_NER_LABEL) {
                                // Обход всех сущностей кандидатов из выборки
                                foreach ($candidate_entities as $candidate_entity) {
                                    // Параллельное вычисление сходства между сущностями из набора кандидатов по заголовкам
                                    $cr = new ConsoleRunner(['file' => '@app/yii']);
                                    $cr->run('spreadsheet/get-heading-rank "' .
                                        implode(",", $formed_row_heading_labels) . '" "' .
                                        $candidate_entity->entity . '" ' . $candidate_entity->id);
                                }
                            }
                        }
                }
        }
        // Ожидание формирования оценок сходства между сущностями из набора кандидатов по заголовкам
        foreach ($cell_values as $cell_value) {
            $heading_count = 0;
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            $candidate_entity_count = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->count();
            while ($heading_count != $candidate_entity_count) {
                $heading_count = 0;
                foreach ($candidate_entities as $candidate_entity)
                    $heading_count += HeadingRank::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->count();
                sleep(1);
            }
        }
        print_r('Вычисление сходства по контексту...' . PHP_EOL);
        // Вычисление сходства между сущностями из набора кандидатов по контексту упоминания сущности
        $this->getContextSimilarity($data, $cell_values);
        print_r('Формирование родительских классов...' . PHP_EOL);
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход всех сущностей кандидатов из выборки
            foreach ($candidate_entities as $candidate_entity) {
                // Параллельное получение родительских классов для текущей сущности кандидата
                $cr = new ConsoleRunner(['file' => '@app/yii']);
                $cr->run('spreadsheet/get-parent-classes "' . $candidate_entity->entity . '" ' .
                    $candidate_entity->id);
            }
        }
        // Ожидание формирования записей для сходства между сущностями из набора кандидатов по семантической близости
        foreach ($cell_values as $cell_value) {
            $semantic_similarity_count = 0;
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            $candidate_entity_count = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->count();
            while ($semantic_similarity_count != $candidate_entity_count) {
                $semantic_similarity_count = 0;
                foreach ($candidate_entities as $candidate_entity)
                    $semantic_similarity_count += SemanticSimilarity::find()
                        ->where(['candidate_entity' => $candidate_entity->id])
                        ->count();
                sleep(1);
            }
        }
        // Массив для всех наборов сущностей кандидатов с их родительскими классами
        $all_candidate_entities = array();
        // Обход всех значений ячеек текущей канонической таблицы
        foreach ($cell_values as $cell_value) {
            // Массив сущностей кандидатов с их родительскими классами
            $candidate_entities_with_classes = array();
            // Поиск всех сущностей кандидатов для текущего значения чейки в БД
            $candidate_entities = CandidateEntity::find()
                ->where(['cell_value' => $cell_value->id, 'aggregated_rank' => null])
                ->all();
            // Обход всех сущностей кандидатов из выборки
            foreach ($candidate_entities as $candidate_entity) {
                $foo = array();
                // Поиск всех родительских классов для сущности кандидата в БД
                $parent_classes = ParentClass::find()->where(['candidate_entity' => $candidate_entity->id])->all();
                foreach ($parent_classes as $parent_class)
                    array_push($foo, $parent_class->class);
                // Формирование массива сущностей кандидатов с их родительскими классами
                $candidate_entities_with_classes[$candidate_entity->entity] = $foo;
            }
            // Добавление массива сущностей кандидатов с их классами в общий набор
            $all_candidate_entities[$cell_value->name] = $candidate_entities_with_classes;
        }
        print_r('Вычисление сходства по семантической близости...' . PHP_EOL);
        // Вычисление сходства между сущностями из набора кандидатов по семантической близости
        $this->getSemanticSimilarityDistance($all_candidate_entities, $canonical_table_id);
        print_r('Агрегирование полученных оценок...' . PHP_EOL);
        // Агрегирование оценок (рангов) для сущностей кандидатов и сохранение их в БД
        $this->getAggregatedNamedEntityRanks($canonical_table_id, 1, 1, 1,
            1, 1);
    }
}