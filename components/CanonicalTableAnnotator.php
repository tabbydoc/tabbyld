<?php

namespace app\components;

use Yii;
use DOMDocument;
use BorderCloud\SPARQL\SparqlClient;
use vova07\console\ConsoleRunner;

/**
 * Class CanonicalTableAnnotator.
 *
 * @package app\components
 */
class CanonicalTableAnnotator
{
    // Названия меток NER-аннотатора Stanford NLP
    const NUMBER_NER_LABEL = 'NUMBER';
    const DATE_NER_LABEL = 'DATE';
    const TIME_NER_LABEL = 'TIME';
    const MONEY_NER_LABEL = 'MONEY';
    const PERCENT_NER_LABEL = 'PERCENT';
    const NONE_NER_LABEL = 'NONE';
    const LOCATION_NER_LABEL = 'LOCATION';
    const PERSON_NER_LABEL = 'PERSON';
    const ORGANIZATION_NER_LABEL = 'ORGANIZATION';
    const MISC_NER_LABEL = 'MISC';

    // Названия (адреса) классов и экземпляров классов в онтологии DBpedia соответствующие меткам NER
    const LOCATION_ONTOLOGY_CLASS = 'http://dbpedia.org/ontology/Location';
    const PERSON_ONTOLOGY_CLASS = 'http://dbpedia.org/ontology/Person';
    const ORGANISATION_ONTOLOGY_CLASS = 'http://dbpedia.org/ontology/Organisation';
    const NUMBER_ONTOLOGY_INSTANCE = 'http://dbpedia.org/resource/Number';
    const MONEY_ONTOLOGY_INSTANCE = 'http://dbpedia.org/resource/Money';
    const PERCENT_ONTOLOGY_INSTANCE = 'http://dbpedia.org/resource/Percent';
    const DATE_ONTOLOGY_INSTANCE = 'http://dbpedia.org/resource/Date';
    const TIME_ONTOLOGY_INSTANCE = 'http://dbpedia.org/resource/Time';

    const ENDPOINT_NAME = 'https://dbpedia.org/sparql'; // Название точки доступа SPARQL

    const DBPEDIA_ONTOLOGY_SECTION = 'http://dbpedia.org/ontology/'; // Название (адрес) сегмента онтологии DBpedia
    const DBPEDIA_RESOURCE_SECTION = 'http://dbpedia.org/resource/'; // Название (адрес) сегмента ресурсов DBpedia
    const DBPEDIA_PROPERTY_SECTION = 'http://dbpedia.org/property/'; // Название (адрес) сегмента свойств DBpedia

    const DATA_TITLE = 'DATA';                         // Имя первого заголовка столбца канонической таблицы
    const ROW_HEADING_TITLE = 'RowHeading1';           // Имя второго заголовка столбца канонической таблицы
    const COLUMN_HEADING_TITLE = 'ColumnHeading';      // Имя третьего заголовка столбца канонической таблицы

    const LITERAL_STRATEGY = 'Literal annotation';             // Стратегия аннотирования литеральных значений
    const NAMED_ENTITY_STRATEGY = 'Named entities annotation'; // Стратегия аннотирования именованных сущностей

    public $current_annotation_strategy_type; // Текущий тип стратегии аннотирования

    // Массив кандидатов родительских классов для концептов в "DATA"
    public $parent_data_class_candidates = array();
    // Массив кандидатов родительских классов для сущностей в "RowHeading"
    public $parent_row_heading_class_candidates = array();
    // Массив кандидатов родительских классов для сущностей в "ColumnHeading"
    public $parent_column_heading_class_candidates = array();
    // Массив с определенными родительскими классами для концептов в "DATA"
    public $parent_data_classes = array();
    // Массив с определенными родительскими классами для сущностей в "RowHeading"
    public $parent_row_heading_classes = array();
    // Массив сс определенными родительскими классами для сущностей в "ColumnHeading"
    public $parent_column_heading_classes = array();

    public $log = '';           // Текст с записью логов хода выполнения аннотации таблицы
    public $all_query_time = 0; // Общее время выполнения всех SPARQL-запросов
    public $data_entities = array();

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
    public static function getNormalizedEntry($entry)
    {
        // Удаление всех символов кроме букв и цифр из строки
        $normalized_entry = preg_replace ('/[^a-zA-Zа-яА-Я0-9\s]/si','', $entry);
        // Изменение множественных пробелов на один
        $normalized_entry = preg_replace('/[^\S\r\n]+/', ' ', $normalized_entry);
        $normalized_entry = preg_replace('/^(?![\r\n]\s)+|(?![\r\n]\s)+$/m', ' ',
            $normalized_entry);
        // Удаление пробелов из начала и конца строки
        $normalized_entry = trim($normalized_entry);
        // Перевод первой буквы в каждом слове в заглавую
        $normalized_entry = ucwords(mb_strtolower($normalized_entry));
        // Замена пробелов знаком нижнего подчеркивания
        $normalized_entry = str_replace(' ', '_', $normalized_entry);

        return $normalized_entry;
    }

    /**
     * Идентификация типа таблицы по столбцу DATA (блока данных).
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_labels - данные с NER-метками
     */
    public function identifyTableType($data, $ner_labels)
    {
        $formed_data_entries = array();
        $formed_ner_labels = array();
        // Счетчик для кол-ва значений ячеек столбца с данными
        $i = 0;
        // Цикл по всем ячейкам столбца с данными
        foreach ($data as $item)
            foreach ($item as $key => $value)
                if ($key == self::DATA_TITLE) {
                    $i++;
                    $formed_data_entries[$value] = $value;
                    // Счетчик для кол-ва NER-меток для ячеек DATA
                    $j = 0;
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_labels as $ner_item)
                        foreach ($ner_item as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                $j++;
                                if ($i == $j)
                                    $formed_ner_labels[$value] = $ner_value;
                            }
                }
        // Название метки NER
        $global_ner_label = '';
        // Обход массива значений столбца с метками NER
        foreach ($formed_data_entries as $data_key => $entry)
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                if ($data_key == $ner_key)
                    $global_ner_label = $ner_label;
        // Если в столбце DATA содержаться литералы, то устанавливаем стратегию аннотирования литеральных значений
        if (in_array($global_ner_label, array(self::NUMBER_NER_LABEL, self::DATE_NER_LABEL, self::TIME_NER_LABEL,
            self::MONEY_NER_LABEL, self::PERCENT_NER_LABEL)))
            $this->current_annotation_strategy_type = self::LITERAL_STRATEGY;
        // Если в столбце DATA содержаться сущности, то устанавливаем стратегию аннотирования именованных сущностей
        if (in_array($global_ner_label, array(self::NONE_NER_LABEL, self::LOCATION_NER_LABEL,
            self::PERSON_NER_LABEL, self::ORGANIZATION_NER_LABEL, self::MISC_NER_LABEL)))
            $this->current_annotation_strategy_type = self::NAMED_ENTITY_STRATEGY;
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
                FROM <http://dbpedia.org>
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
                FROM <http://dbpedia.org>
                WHERE { ?subject a ?object . FILTER ( contains(str(?subject), '$value') &&
                    ( (strstarts(str(?subject), str(dbo:)) && (str(?object) = str(owl:Class))) ||
                    (strstarts(str(?subject), str(db:)) && (str(?object) = str(owl:Thing))) ) )
            } LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();

        // Запить журнала
        $this->log .= 'Запрос для ' . $value . ': ' . $rows['query_time'] . PHP_EOL;
        $this->all_query_time += $rows['query_time'];

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
        $query = "SELECT ?class
            FROM <http://dbpedia.org>
            WHERE {
                <$entity> ?property ?class
                FILTER (strstarts(str(?class), 'http://dbpedia.org/ontology/'))
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
     * @param $candidate_entities - массив сущностей кандидатов
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getLevenshteinDistance($input_value, $candidate_entities)
    {
        // Массив ранжированных сущностей кандидатов
        $ranked_candidate_entities = array();
        // Массив названий URI DBpedia
        $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION, self::DBPEDIA_PROPERTY_SECTION);
        // Обход всех сущностей кандидатов из набора
        foreach ($candidate_entities as $candidate_entity) {
            // Удаление адреса URI у сущности кандидата
            $candidate_entity_name = str_replace($URIs, '', $candidate_entity);
            // Вычисление расстояния Левенштейна между входным значением и текущим названием сущности кандидата
            $distance = levenshtein($input_value, $candidate_entity_name);
            // Формирование массива ранжированных сущностей кандидатов
            array_push($ranked_candidate_entities, [$candidate_entity, $distance]);
        }

        return $ranked_candidate_entities;
    }

    /**
     * Нахождение связей между сущностями кандидатами.
     *
     * @param $all_candidate_entities - набор всех массивов сущностей кандидатов
     * @param $path - путь для сохранения файлов
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getRelationshipDistance($all_candidate_entities, $path)
    {
        // Обход всех наборов сущностей кандидатов
        foreach ($all_candidate_entities as $label => $candidate_entities) {
            // Кодирование в имени каталога запрещенных символов
            $current_label = self::encodeFileName($label);
            // Если не существует каталога для хранения результатов аннотирования таблицы
            if (!file_exists($path . $label)) {
                // Создание каталога для хранения результатов аннотирования таблицы
                mkdir(Yii::$app->basePath . '/' . $path . $current_label, 0755);
            }
            // Обход всех сущностей кандидатов из набора
            foreach ($candidate_entities as $candidate_entity) {
                // Параллельное нахождение связей между сущностями кандидатов
                $cr = new ConsoleRunner(['file' => '@app/yii']);
                $cr->run('spreadsheet/find-relationship-distance "' . $label .'" "' .
                    $candidate_entity . '" ' . $path);
            }
        }
        // Ожидание формирования всех файлов с оценками
        foreach ($all_candidate_entities as $label => $candidate_entities) {
            if (!empty($candidate_entities)) {
                // Кодирование в имени каталога запрещенных символов
                $current_label = self::encodeFileName($label);
                while (count(array_diff(scandir($path . $current_label . '/'),
                        array('..', '.'))) != count($candidate_entities)) {
                    usleep(1);
                }
            }
        }
        // Набор для всех массивов ранжированных сущностей кандидатов
        $ranked_relationship_distance_candidate_entities = array();
        // Обход всех наборов сущностей кандидатов
        foreach ($all_candidate_entities as $label => $candidate_entities) {
            if (!empty($candidate_entities)) {
                // Кодирование в имени каталога запрещенных символов
                $current_label = self::encodeFileName($label);
                // Открытие каталога с файлами
                if ($handle = opendir($path . $current_label . '/')) {
                    // Массив ранжированных сущностей кандидатов
                    $ranked_candidate_entities = array();
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
                                $pos = strpos($text, 'SPARQL_EXECUTION_TIME');
                                // Если текущая строка является оценкой
                                if ($text != '' && $pos === false) {
                                    // Получение имени файла без расширения
                                    $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME );
                                    // Декодирование имени файла
                                    $candidate_entity_name = self::decodeFileName($file_name_without_extension);
                                    // Добавление пространства имен
                                    $candidate_entity_name = $this::DBPEDIA_RESOURCE_SECTION . $candidate_entity_name;
                                    // Формирование массива ранжированных сущностей кандидатов
                                    array_push($ranked_candidate_entities, [$candidate_entity_name, $text]);
                                }
                            }
                            // Закрытие файла
                            fclose($fp);
                        }
                    }
                    // Закрытие каталога
                    closedir($handle);
                    // Добавление массива ранжированных сущностей кандидатов в общий набор
                    $ranked_relationship_distance_candidate_entities[$label] = $ranked_candidate_entities;
                }
            }
        }

        return $ranked_relationship_distance_candidate_entities;
    }

    /**
     * Получение агрегированных оценок (рангов) сущностей кандидатов для заголовков таблиц.
     *
     * @param $levenshtein_distance_candidate_entities - ранжированный массив сущностей кандидатов по расстоянию Левенштейна
     * @param $a_weight_factor - весовой фактор А для определения важности оценки расстояния Левенштейна
     * @param $relationship_distance_candidate_entities - ранжированный массив сущностей кандидатов по связям
     * @param $b_weight_factor - весовой фактор B для определения важности оценки по связям
     * @return array - ранжированный массив сущностей кандидатов с агрегированными оценками
     */
    public function getAggregatedHeadingRanks($levenshtein_distance_candidate_entities, $a_weight_factor,
                                              $relationship_distance_candidate_entities, $b_weight_factor)
    {
        $ranked_candidate_entities = array();
        // Обход массива с наборами ранжированных сущностей кандидатов по расстоянию Левенштейна
        foreach ($levenshtein_distance_candidate_entities as $ld_key => $ld_items) {
            // Массив для хранения агрегированных оценок
            $full_distance_array = array();
            // Обход массива с наборами ранжированных сущностей кандидатов по связям
            foreach ($relationship_distance_candidate_entities as $rd_key => $rd_items)
                if ($ld_key == $rd_key)
                    foreach ($ld_items as $levenshtein_distance_candidate_entity)
                        foreach ($rd_items as $relationship_distance_candidate_entity)
                            if ($levenshtein_distance_candidate_entity[0] ==
                                $relationship_distance_candidate_entity[0]) {
                                // Нормализация рангов (расстояния) Левенштейна
                                $normalized_levenshtein_distance = 1 - $levenshtein_distance_candidate_entity[1] / 100;
                                // Вычисление агрегированной оценки (ранга)
                                $full_rank = $a_weight_factor * $normalized_levenshtein_distance +
                                    $b_weight_factor * $relationship_distance_candidate_entity[1];
                                // Формирование массива ранжированных сущностей кандидатов с агрегированной оценкой
                                array_push($full_distance_array,
                                    [$levenshtein_distance_candidate_entity[0], $full_rank]);
                            }
            // Сортировка массива ранжированных сущностей кандидатов по убыванию их ранга
            for ($i = 0; $i < count($full_distance_array); $i++)
                for ($j = $i + 1; $j < count($full_distance_array); $j++)
                    if ($full_distance_array[$i][1] <= $full_distance_array[$j][1]) {
                        $temp = $full_distance_array[$j];
                        $full_distance_array[$j] = $full_distance_array[$i];
                        $full_distance_array[$i] = $temp;
                    }
            // Формирование массива ранжированных сущностей кандидатов для текущего значения ячейки
            $ranked_candidate_entities[$ld_key] = $full_distance_array;
        }

        return $ranked_candidate_entities;
    }

    /**
     * Определение близости сущностей кандидатов к классу, который задан NER-меткой.
     *
     * @param $ner_label - NER-метка присвоенная значению ячейки в столбце DATA
     * @param $candidate_entities - массив сущностей кандидатов
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getNerClassDistance($ner_label, $candidate_entities)
    {
        // Массив ранжированных сущностей кандидатов
        $ranked_candidate_entities = array();
        // Определение класса из онтологии DBpedia для соответствующей метки NER
        if ($ner_label == self::LOCATION_NER_LABEL)
            $ner_class = self::LOCATION_ONTOLOGY_CLASS;
        if ($ner_label == self::PERSON_NER_LABEL)
            $ner_class = self::PERSON_ONTOLOGY_CLASS;
        if ($ner_label == self::ORGANIZATION_NER_LABEL)
            $ner_class = self::ORGANISATION_ONTOLOGY_CLASS;
        // Массив названий URI DBpedia
        $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION, self::DBPEDIA_PROPERTY_SECTION);
        // Обход всех сущностей из набора кандидатов
        foreach ($candidate_entities as $candidate_entity) {
            // Удаление адреса URI у сущности из набора кандидатов
            $candidate_entity_name = str_replace($URIs, '', $candidate_entity);
            // Подключение к DBpedia
            $sparql_client = new SparqlClient();
            $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
            // SPARQL-запрос к DBpedia для определения глубины связи текущей сущности из набора кандидатов с классом
            $query = "SELECT count(?intermediate)/2 as ?depth
                FROM <http://dbpedia.org>
                WHERE { <$candidate_entity> rdf:type/rdfs:subClassOf* ?intermediate .
                    ?intermediate rdfs:subClassOf* <$ner_class>
                }";
            $rows = $sparql_client->query($query, 'rows');
            $error = $sparql_client->getErrors();

            // Запить журнала
            $this->log .= 'Запрос для определения глубины связи для ' . $candidate_entity_name . ': ' .
                $rows['query_time'] . PHP_EOL;
            $this->all_query_time += $rows['query_time'];

            // Вычисляемый ранг (оценка) для сущности кандидата
            $rank = 0;
            // Если нет ошибок при запросе, есть результат запроса и глубина не 0, то
            // вычисление ранга (оценки) для текущей сущности из набора кандидатов в соответствии с глубиной
            if (!$error && $rows['result']['rows'] && $rows['result']['rows'][0]['depth'] != 0)
                $rank = 1 / $rows['result']['rows'][0]['depth'];
            // Формирование массива ранжированных сущностей кандидатов
            array_push($ranked_candidate_entities, [$candidate_entity, $rank]);
        }

        return $ranked_candidate_entities;
    }

    /**
     * Определение сходства сущностей кандидатов по заголовкам таблицы.
     *
     * @param $formed_row_heading_labels - массив всех заголовков строки в канонической таблице
     * @param $candidate_entities - массив сущностей кандидатов
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getHeadingDistance($formed_row_heading_labels, $candidate_entities)
    {
        // Массив ранжированных сущностей кандидатов
        $ranked_candidate_entities = array();
        // Массив названий URI DBpedia
        $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION, self::DBPEDIA_PROPERTY_SECTION);
        // Обход всех сущностей из набора кандидатов
        foreach ($candidate_entities as $candidate_entity) {
            // Удаление адреса URI у сущности из набора кандидатов
            $candidate_entity_name = str_replace($URIs, '', $candidate_entity);
            // Подключение к DBpedia
            $sparql_client = new SparqlClient();
            $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
            // SPARQL-запрос к DBpedia для поиска всех классов, которым принадлежит данная сущность
            $section = self::DBPEDIA_ONTOLOGY_SECTION;
            $query = "SELECT ?class
                FROM <http://dbpedia.org>
                WHERE { <$candidate_entity> rdf:type ?class . FILTER(strstarts(str(?class), '$section'))
                }";
            $rows = $sparql_client->query($query, 'rows');
            $error = $sparql_client->getErrors();

            // Запить журнала
            $this->log .= 'Запрос для поиска всех классов для ' . $candidate_entity_name . ': ' .
                $rows['query_time'] . PHP_EOL;
            $this->all_query_time += $rows['query_time'];

            // Если нет ошибок при запросе и есть результат запроса
            if (!$error && $rows['result']['rows']) {
                $rank = 100;
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
                // Формирование массива ранжированных сущностей кандидатов
                array_push($ranked_candidate_entities, [$candidate_entity, $rank]);
            }
        }

        return $ranked_candidate_entities;
    }

    /**
     * Определение сходства сущностей кандидатов по семантической близости.
     *
     * @param $all_candidate_entities - массив для всех массивов сущностей кандидатов
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getSemanticSimilarityDistance($all_candidate_entities)
    {
        // Массив всех ранжированных сущностей кандидатов
        $global_ranked_candidate_entities = array();
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
                                        $current_parent_class_name = str_replace(self::DBPEDIA_ONTOLOGY_SECTION, '',
                                            $current_parent_class);
                                        $comparative_parent_class_name = str_replace(self::DBPEDIA_ONTOLOGY_SECTION, '',
                                            $comparative_parent_class);
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
            // Массив сущностей кандидатов с результирующими рангами
            $ranked_candidate_entities = array();
            // Расчет результирующего ранга для сущностей кандидатов
            foreach ($intermediate_candidate_entities as $intermediate_candidate_entity)
                array_push($ranked_candidate_entities, [$intermediate_candidate_entity[0],
                    ($intermediate_candidate_entity[1] / $coefficient)]);
            // Формирование массива всех ранжированных сущностей кандидатов
            $global_ranked_candidate_entities[$current_entry_name] = $ranked_candidate_entities;

            // Запить журнала
            $this->log .= 'Сформирован массив ранжированных сущностей кандидатов для ' .
                $current_entry_name . ': ' . PHP_EOL;
        }

        return $global_ranked_candidate_entities;
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
        // Массив для хранения значений ячеек заголовков таблицы
        $formed_heading_values = array();
        // Цикл по всем строкам канонической таблицы
        foreach ($data as $row_number => $row) {
            $current_data_value = '';
            $current_heading_values = array();
            // Цикл по столбцам (ячейкам) строки канонической таблицы
            foreach ($row as $heading => $value) {
                // Если столбец с данными
                if ($heading == self::DATA_TITLE)
                    // Запоминание текущего значения ячейки столбца с данными
                    $current_data_value = $value;
                // Если столбцы с заголовками
                if ($heading == self::ROW_HEADING_TITLE || $heading == self::COLUMN_HEADING_TITLE)
                    // Формирование массива со значениями ячеек заголовков для строки
                    array_push($current_heading_values, $value);
            }
            // Формирование массива со значениями ячеек заголовков таблицы
            $formed_heading_values[$current_data_value] = $current_heading_values;
        }
        // Массив для хранения контекста значения ячейки данных таблицы
        $entry_context = array();
        // Определение контекста значения ячейки данных таблицы
        foreach ($formed_heading_values as $current_key => $current_values)
            if ($entry == $current_key) {
                foreach ($formed_heading_values as $comparative_key => $comparative_values)
                    if ($current_key != $comparative_key) {
                        if ($current_values[0] == $comparative_values[0] ||
                            $current_values[1] == $comparative_values[1])
                            array_push($entry_context, $comparative_key);
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
        $query = "SELECT ?subject ?object
            FROM <http://dbpedia.org>
            WHERE {
                { <$candidate_entity> ?property ?object .
                    FILTER(strstarts(str(?object), 'http://dbpedia.org/ontology/') ||
                        strstarts(str(?object), 'http://dbpedia.org/resource/')) .
                    FILTER(strstarts(str(?property), 'http://dbpedia.org/ontology/') ||
                        strstarts(str(?property), 'http://dbpedia.org/resource/'))
                } UNION { ?subject ?property <$candidate_entity> .
                    FILTER(strstarts(str(?subject), 'http://dbpedia.org/ontology/') ||
                        strstarts(str(?subject), 'http://dbpedia.org/resource/')) .
                    FILTER(strstarts(str(?property), 'http://dbpedia.org/ontology/') ||
                        strstarts(str(?property), 'http://dbpedia.org/resource/'))
                }
            }";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();

        // Запить журнала
        $this->log .= 'Запрос поиска контекста для ' . $candidate_entity . ': ' .
            $rows['query_time'] . PHP_EOL;
        $this->all_query_time += $rows['query_time'];

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
     * @param $entry - значение ячейки данных из канонической таблицы
     * @param $candidate_entities - массив всех сущностей кандидатов для текущего значения ячейки
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getContextSimilarityDistance($data, $entry, $candidate_entities)
    {
        // Массив ранжированных сущностей кандидатов
        $ranked_candidate_entities = array();
        // Определение контекста текущего значения ячейки данных таблицы
        $current_entry_context = $this->getEntryContext($data, $entry);
        // Обход сущностей кандидатов
        foreach ($candidate_entities as $candidate_entity) {
            // Массив названий URI DBpedia
            $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION);
            // Определение контекста текущей сущности кандидата
            $current_entity_context = $this->getEntityContext($candidate_entity);
            // Установка первоначального ранга
            $rank = 0;
            // Если контекст сущности кандидата определен
            if ($current_entity_context)
                // Сравнение контекста значения ячейки с контекстом сущности кандидата
                foreach ($current_entry_context as $entry_name)
                    foreach ($current_entity_context as $entity) {
                        // Удаление адреса URI у сущности кандидата
                        $entity_name = str_replace($URIs, '', $entity);
                        // Если названия концептов из двух контекстов совпадают (расстояние Левенштейна между ними = 0)
                        if (levenshtein($entry_name, $entity_name) == 0)
                            // Инкремент текущего ранга
                            $rank += 1;
                    }
            // Формирование массива ранжированных сущностей кандидатов
            array_push($ranked_candidate_entities, [$candidate_entity, $rank]);
        }

        return $ranked_candidate_entities;
    }

    /**
     * Получение агрегированных оценок (рангов) сущностей кандидатов для столбца с данными (именоваными сущностями).
     *
     * @param $levenshtein_distance_candidate_entities - ранжированный массив сущностей кандидатов по расстоянию Левенштейна
     * @param $a_weight_factor - весовой фактор А для определения важности оценки расстояния Левенштейна
     * @param $ner_class_distance_candidate_entities - ранжированный массив сущностей кандидатов по классам, определенных NER-метками
     * @param $b_weight_factor - весовой фактор B для определения важности оценки сходства по классам, определенных NER-метками
     * @param $heading_distance_candidate_entities - ранжированный массив сущностей кандидатов по заголовкам
     * @param $c_weight_factor - весовой фактор С для определения важности оценки сходства по заголовкам
     * @param $semantic_similarity_distance_candidate_entities - ранжированный массив сущностей кандидатов по семантической близости
     * @param $d_weight_factor - весовой фактор D для определения важности оценки сходства по семантической близости
     * @param $context_similarity_distance_candidate_entities - ранжированный массив сущностей кандидатов по контексту упоминания сущности
     * @param $e_weight_factor - весовой фактор E для определения важности оценки сходства по контексту упоминания сущности
     * @return array - ранжированный массив сущностей кандидатов с агрегированными оценками
     */
    public function getAggregatedNamedEntityRanks($levenshtein_distance_candidate_entities, $a_weight_factor,
                                                  $ner_class_distance_candidate_entities, $b_weight_factor,
                                                  $heading_distance_candidate_entities, $c_weight_factor,
                                                  $semantic_similarity_distance_candidate_entities, $d_weight_factor,
                                                  $context_similarity_distance_candidate_entities, $e_weight_factor)
    {
        $ranked_candidate_entities = array();
        // Обход массива с наборами ранжированных сущностей кандидатов по расстоянию Левенштейна
        foreach ($levenshtein_distance_candidate_entities as $ld_entry => $ld_entities) {
            // Массив для хранения агрегированных оценок
            $full_distance_array = array();
            //
            foreach ($ld_entities as $levenshtein_distance_candidate_entity) {
                // Текущий ранг сущности кандидата
                $full_rank = 0;
                // Нормализация рангов (расстояния) Левенштейна
                $normalized_levenshtein_distance = 1 - $levenshtein_distance_candidate_entity[1] / 100;
                // Обход массива с наборами ранжированных сущностей кандидатов по классам, определенных NER-метками
                foreach ($ner_class_distance_candidate_entities as $ncd_entry => $ncd_entities)
                    if ($ld_entry == $ncd_entry)
                        foreach ($ncd_entities as $ner_class_distance_candidate_entity)
                            if ($levenshtein_distance_candidate_entity[0] ==
                                $ner_class_distance_candidate_entity[0]) {
                                // Вычисление агрегированной оценки (ранга)
                                $full_rank = $a_weight_factor * $normalized_levenshtein_distance +
                                    $b_weight_factor * $ner_class_distance_candidate_entity[1];
                            }
                // Обход массива с наборами ранжированных сущностей кандидатов по заголовкам
                foreach ($heading_distance_candidate_entities as $hd_entry => $hd_entities)
                    if ($ld_entry == $hd_entry)
                        foreach ($hd_entities as $heading_distance_candidate_entity)
                            if ($levenshtein_distance_candidate_entity[0] ==
                                $heading_distance_candidate_entity[0]) {
                                // Вычисление агрегированной оценки (ранга)
                                $full_rank += $c_weight_factor * $heading_distance_candidate_entity[1];
                            }
                // Обход массива с наборами ранжированных сущностей кандидатов по семантической близости
                foreach ($semantic_similarity_distance_candidate_entities as $ssd_entry => $ssd_entities)
                    if ($ld_entry == $ssd_entry)
                        foreach ($ssd_entities as $semantic_similarity_distance_candidate_entity)
                            if ($levenshtein_distance_candidate_entity[0] ==
                                $semantic_similarity_distance_candidate_entity[0]) {
                                // Вычисление агрегированной оценки (ранга)
                                $full_rank += $d_weight_factor * $semantic_similarity_distance_candidate_entity[1];
                            }
                // Обход массива с наборами ранжированных сущностей кандидатов по контексту упоминания сущности
                foreach ($context_similarity_distance_candidate_entities as $csd_entry => $csd_entities)
                    if ($ld_entry == $csd_entry)
                        foreach ($csd_entities as $context_similarity_distance_candidate_entity)
                            if ($levenshtein_distance_candidate_entity[0] ==
                                $context_similarity_distance_candidate_entity[0]) {
                                // Вычисление агрегированной оценки (ранга)
                                $full_rank += $e_weight_factor * $context_similarity_distance_candidate_entity[1];
                            }
                // Формирование массива ранжированных сущностей кандидатов с агрегированной оценкой
                array_push($full_distance_array, [$levenshtein_distance_candidate_entity[0], $full_rank]);
            }
            // Сортировка массива ранжированных сущностей кандидатов по убыванию их ранга
            for ($i = 0; $i < count($full_distance_array); $i++)
                for ($j = $i + 1; $j < count($full_distance_array); $j++)
                    if ($full_distance_array[$i][1] <= $full_distance_array[$j][1]) {
                        $temp = $full_distance_array[$j];
                        $full_distance_array[$j] = $full_distance_array[$i];
                        $full_distance_array[$i] = $temp;
                    }
            // Формирование массива ранжированных сущностей кандидатов для текущего значения ячейки
            $ranked_candidate_entities[$ld_entry] = $full_distance_array;
        }

        return $ranked_candidate_entities;
    }

    /**
     * Аннотирование столбцов "RowHeading" и "ColumnHeading" содержащих значения заголовков исходной таблицы.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $heading_title - имя столбца
     * @param $path - путь для сохранения файла
     * @return array - массив с результатми поиска сущностей в онтологии DBpedia
     */
    public function annotateTableHeading($data, $heading_title, $path)
    {
        // Массив неповторяющихся корректных значений заголовков столбцов
        $formed_heading_labels = array();
        // Формирование массива неповторяющихся корректных значений для заголовков столбцов
        foreach ($data as $item)
            foreach ($item as $heading => $value)
                if ($heading == $heading_title) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $key => $string)
                        // Формирование массива корректных значений заголовков столбцов для поиска сущностей кандидатов
                        $formed_heading_labels[$string] = self::getNormalizedEntry($string);
                }
        // Обход массива корректных значений заголовков столбцов для поиска классов и концептов
        foreach ($formed_heading_labels as $key => $heading_label) {
            // Параллельное формирование сущностей кандидатов (классов и объектов)
            $cr = new ConsoleRunner(['file' => '@app/yii']);
            $cr->run('spreadsheet/get-candidate-entities "' . $heading_label . '" "' . $key . '" ' . $path);
        }
        // Ожидание формирования сущностей кандидатов
        while (count(array_diff(scandir($path), array('..', '.'))) != count($formed_heading_labels)) {
            sleep(1);
        }

        // Набор для хранения всех массивов сущностей кандидатов
        $all_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов по расстоянию Левенштейна
        $levenshtein_distance_candidate_entities = array();
        // Обход массива корректных значений заголовков столбцов для формирования набора всех массивов сущностей кандидатов
        foreach ($formed_heading_labels as $key => $heading_label) {
            // Массив сущностей кандидатов
            $candidate_entities = array();
            // Кодирование в имени файла запрещенных символов
            $correct_key = self::encodeFileName($key);
            // Открытие файла
            $fp = fopen($path . $correct_key . '.log', "r");
            // Чтение файла до конца
            while (!feof($fp)) {
                // Чтение строки из файла и удаление переноса строк
                $text = str_replace("\r\n", '', fgets($fp));
                // Поиск в строке метки "SPARQL_EXECUTION_TIME"
                $pos = strpos($text, 'SPARQL_EXECUTION_TIME');
                // Формирование массива сущностей кандидатов
                if ($text != '' && $pos === false)
                    array_push($candidate_entities, $text);
            }
            // Добавление массива сущностей кандидатов в общий набор
            $all_candidate_entities[$key] = $candidate_entities;
            // Вычисление расстояния Левенштейна для каждой сущности из набора кандидатов
            $levenshtein_distance_candidate_entities[$key] = $this->getLevenshteinDistance($heading_label,
                $candidate_entities);
        }
        // Открытие файла на запись для сохранения результатов поиска сущностей кандидатов
        $levenshtein_results_file = fopen($path . 'levenshtein_results.log', 'a');
        // Запись в файл логов аннотирования таблицы с оценкой расстояния Левенштейна
        $json = json_encode($levenshtein_distance_candidate_entities, JSON_PRETTY_PRINT);
        $json = str_replace('\/', '/', $json);
        fwrite($levenshtein_results_file, $json);
        // Закрытие файла
        fclose($levenshtein_results_file);

        // Нахождение связей между сущностями кандидатов
        $relationship_distance_candidate_entities = $this->getRelationshipDistance($all_candidate_entities, $path);
        // Открытие файла на запись для сохранения результатов поиска сущностей кандидатов
        $relationship_distance_results_file = fopen($path . 'relationship_distance_results.log', 'a');
        // Запись в файл логов аннотирования таблицы с оценкой расстояния Левенштейна
        $json = json_encode($relationship_distance_candidate_entities, JSON_PRETTY_PRINT);
        $json = str_replace('\/', '/', $json);
        fwrite($relationship_distance_results_file, $json);
        // Закрытие файла
        fclose($relationship_distance_results_file);

        // Получение агрегированных оценок (рангов) для сущностей кандидатов
        $ranked_candidate_entities = $this->getAggregatedHeadingRanks($levenshtein_distance_candidate_entities,
            1, $relationship_distance_candidate_entities, 1);

        return $ranked_candidate_entities;
    }

    /**
     * Аннотирование содержимого столбца "DATA" с литеральными значениями.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_labels - данные с NER-метками
     * @param $path - путь для сохранения файла
     * @return array - массив с результатми поиска объектов и классов в онтологии DBpedia
     */
    public function annotateTableLiteralData($data, $ner_labels, $path)
    {
        $formed_data_entries = array();
        $formed_ner_labels = array();
        // Цикл по всем ячейкам столбца с данными
        foreach ($data as $row_number => $row)
            foreach ($row as $key => $value)
                if ($key == self::DATA_TITLE) {
                    // Формирование массива корректных значений ячеек столбца с данными
                    $formed_data_entries[$value] = self::getNormalizedEntry($value);
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_labels as $ner_row_number => $ner_row)
                        foreach ($ner_row as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                // Формирование массива соответсвий NER-меток значениям столбца с данными
                                if ($row_number == $ner_row_number)
                                    $formed_ner_labels[$value] = $ner_value;
                            }
                }
        // Обход массива корректных значений столбца с данными для поиска подходящих объектов или классов
        foreach ($formed_data_entries as $key => $entry)
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                if ($key == $ner_key) {
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
                    // Параллельное аннотирование ячеек с данными в фоновом режиме
                    $cr = new ConsoleRunner(['file' => '@app/yii']);
                    $cr->run('spreadsheet/annotate-literal-cell "' . $ner_resource . '" "' . $key . '" ' . $path);
                }
        // Ожидание формирования файлов с результатами аннотирования ячеек данных
        while (count(array_diff(scandir($path), array('..', '.'))) != count($formed_data_entries)) {
            sleep(1);
        }
        // Массив ранжированных сущностей для столбца с данными "DATA"
        $ranked_candidate_entities = array();
        // Открытие каталога с файлами
        if ($handle = opendir($path)) {
            // Чтения элементов (файлов) каталога
            while (false !== ($file_name = readdir($handle))) {
                // Если элемент каталога не является каталогом
                if ($file_name != '.' && $file_name != '..') {
                    // Открытие файла
                    $fp = fopen($path . $file_name, "r");
                    // Чтение файла до конца
                    while (!feof($fp)) {
                        // Чтение строки из файла и удаление переноса строк
                        $text = str_replace("\r\n", '', fgets($fp));
                        // Поиск в строке метки "SPARQL_EXECUTION_TIME"
                        $pos = strpos($text, 'SPARQL_EXECUTION_TIME');
                        // Если текущая строка является сущностью из онологии DBPedia
                        if ($text != '' && $pos === false) {
                            // Получение имени файла без расширения
                            $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME );
                            // Декодирование в имени файла запрещенных символов
                            $decode_file_name = self::decodeFileName($file_name_without_extension);
                            // Формирование массива с найденными сущностями для столбца "DATA"
                            $ranked_candidate_entities[$decode_file_name] = $text;
                        }
                    }
                    // Закрытие файла
                    fclose($fp);
                }
            }
            // Закрытие каталога
            closedir($handle);
        }

        return $ranked_candidate_entities;
    }

    /**
     * Аннотирование содержимого столбца "DATA" с именованными сущностями.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_labels - данные с NER-метками
     * @return array - массив с результатми поиска концептов в онтологии DBpedia
     */
    public function annotateTableEntityData($data, $ner_labels)
    {
        $formed_data_entries = array();
        $formed_ner_labels = array();
        $formed_heading_labels = array();
        // Цикл по всем ячейкам канонической таблицы
        foreach ($data as $row_number => $row) {
            $current_data_value = '';
            $heading_labels = array();
            foreach ($row as $heading => $value) {
                // Если столбец с данными
                if ($heading == self::DATA_TITLE) {
                    // Формирование массива корректных значений ячеек столбца с данными
                    $formed_data_entries[$value] = self::getNormalizedEntry($value);
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_labels as $ner_row_number => $ner_row)
                        foreach ($ner_row as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                // Формирование массива соответсвий NER-меток значениям столбца с данными
                                if ($row_number == $ner_row_number)
                                    $formed_ner_labels[$value] = $ner_value;
                            }
                }
                // Если столбцы с заголовками
                if ($heading == self::ROW_HEADING_TITLE || $heading == self::COLUMN_HEADING_TITLE) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $key => $string)
                        // Формирование массива корректных значений ячеек заголовков для строки
                        array_push($heading_labels, self::getNormalizedEntry($string));
                }
            }
            // Формирование массива корректных значений ячеек заголовков таблицы
            $formed_heading_labels[$current_data_value] = $heading_labels;
        }
        // Массив для хранения массивов сущностей кандидатов
        $all_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов по расстоянию Левенштейна
        $levenshtein_distance_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов, определенных по их связям с классами, полученных NER-метками
        $ner_class_distance_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов по заголовкам таблицы
        $heading_distance_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов по сходству контекста упоминания сущности
        $context_similarity_distance_candidate_entities = array();
        // Обход массива корректных значений столбца данных для поиска референтных сущностей
        foreach ($formed_data_entries as $data_key => $entry) {

            $this->log .= '**************************' . PHP_EOL;

            // Формирование набора сущностей кандидатов
            $candidate_entities = $this->getCandidateEntities($entry, self::DBPEDIA_RESOURCE_SECTION);

            $this->log .= PHP_EOL;

            // Если набор сущностей кандидатов сформирован
            if ($candidate_entities) {
                // Вычисление расстояния Левенштейна для каждой сущности из набора кандидатов
                $levenshtein_distance_candidate_entities[$data_key] = $this->getLevenshteinDistance($entry,
                    $candidate_entities);
                // Обход массива корректных значений NER-меток для столбца данных
                foreach ($formed_ner_labels as $ner_key => $ner_label)
                    if ($data_key == $ner_key)
                        // Вычисление сходства между сущностями из набора кандидатов и классом, определенного NER-меткой
                        $ner_class_distance_candidate_entities[$data_key] = $this->getNerClassDistance($ner_label,
                            $candidate_entities);

                $this->log .= PHP_EOL;

                // Обход массива всех заголовков таблицы
                foreach ($formed_heading_labels as $heading_key => $formed_row_heading_labels)
                    if ($data_key == $heading_key) {
                        // Вычисление сходства между сущностями из набора кандидатов по заголовкам
                        $heading_distance_candidate_entities[$data_key] = $this->getHeadingDistance(
                            $formed_row_heading_labels, $candidate_entities);
                    }

                $this->log .= PHP_EOL;

                // Вычисление сходства между сущностями из набора кандидатов по контексту упоминания сущности
                $context_similarity_distance_candidate_entities[$data_key] = $this->getContextSimilarityDistance($data,
                    $data_key, $candidate_entities);

                // Массив сущностей кандидатов с их родительскими классами
                $candidate_entities_with_classes = array();
                // Обход сущностей кандидатов
                foreach ($candidate_entities as $candidate_entity) {
                    // Получение родительских классов для текущей сущности кандидата
                    $parent_classes = $this->getParentClasses($candidate_entity);
                    // Формирование массива сущностей кандидатов с их родительскими классами
                    $candidate_entities_with_classes[$candidate_entity] = $parent_classes;
                }
                // Добавление массива сущностей кандидатов с их классами в общий набор
                $all_candidate_entities[$data_key] = $candidate_entities_with_classes;
            }
        }
        // Вычисление сходства между сущностями из набора кандидатов по семантической близости
        $semantic_similarity_distance_candidate_entities = $this->getSemanticSimilarityDistance($all_candidate_entities);

        // Получение агрегированных оценок (рангов) для сущностей кандидатов
        $ranked_candidate_entities = $this->getAggregatedNamedEntityRanks($levenshtein_distance_candidate_entities, 1,
            $ner_class_distance_candidate_entities, 1, $heading_distance_candidate_entities, 1,
            $semantic_similarity_distance_candidate_entities, 1, $context_similarity_distance_candidate_entities, 1);

        $this->log .= '**************************' . PHP_EOL;
        $this->log .= 'Общее время на запросы: ' . $this->all_query_time . ' (сек.)' . PHP_EOL;
        $this->log .= 'Общее время на запросы: ' . $this->all_query_time / 60 . ' (мин.)' . PHP_EOL;
        $this->log .= '**************************';

        return $ranked_candidate_entities;
    }

    /**
     * Определение списка кадидатов родительских классов и определение родительского класса из списка по умолчанию.
     *
     * @param $sparql_client - объект клиента подключения к DBpedia
     * @param $entity - сущность (концепт или класс) для которой необходимо найти родительские классы
     * @param $heading_title - название заголовка столбца канонической таблицы
     */
    public function searchParentClasses($sparql_client, $entity, $heading_title)
    {
        // SPARQL-запрос к DBpedia ontology для поиска родительских классов
        $query = "SELECT <$entity> ?property ?object
            FROM <http://dbpedia.org>
            WHERE {
                <$entity> ?property ?object
                FILTER (strstarts(str(?object), 'http://dbpedia.org/ontology/'))
            }
            LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        if (isset($rows['result']) && $rows['result']['rows']) {
            if ($heading_title == self::DATA_TITLE) {
                $this->parent_data_class_candidates[$entity] =
                    self::rankingParentClassCandidates($sparql_client, $rows['result']['rows']);
                $this->parent_data_classes[$entity] = $this->parent_data_class_candidates[$entity][0][0];
            }
            if ($heading_title == self::ROW_HEADING_TITLE) {
                $this->parent_row_heading_class_candidates[$entity] =
                    self::rankingParentClassCandidates($sparql_client, $rows['result']['rows']);
                $this->parent_row_heading_classes[$entity] = $this->parent_row_heading_class_candidates[$entity][0][0];
            }
            if ($heading_title == self::COLUMN_HEADING_TITLE) {
                $this->parent_column_heading_class_candidates[$entity] =
                    self::rankingParentClassCandidates($sparql_client, $rows['result']['rows']);
                $this->parent_column_heading_classes[$entity] =
                    $this->parent_column_heading_class_candidates[$entity][0][0];
            }
        }
    }

    /**
     * Ранжирование списка кадидатов родительских классов.
     *
     * @param $sparql_client - объект клиента подключения к DBpedia
     * @param $parent_class_candidates - массив кадидатов родительских классов
     * @return array - массив ранжированных кадидатов родительских классов
     */
    public function rankingParentClassCandidates($sparql_client, $parent_class_candidates)
    {
        // Массив для ранжированных кандидатов родительских классов
        $ranked_parent_class_candidates = array();
        // Цикл по кандидатам родительских классов
        foreach($parent_class_candidates as $parent_data_class_candidate) {
            $parent_class = $parent_data_class_candidate['object'];
            // SPARQL-запрос к DBpedia для поиска эквивалентных классов
            $query = "SELECT ?class
                FROM <http://dbpedia.org>
                WHERE {
                    ?class owl:equivalentClass <$parent_class>
                    FILTER (strstarts(str(?class), 'http://dbpedia.org/ontology/'))
                }
                LIMIT 100";
            $rows = $sparql_client->query($query, 'rows');
            // Запоминание эквивалентного класса, если он был найден
            if (isset($rows['result']) && $rows['result']['rows'])
                $parent_class = $rows['result']['rows'][0]['class'];
            // SPARQL-запрос к DBpedia для определения кол-ва потомков данного класса до глобального класса owl:Thing
            $query = "SELECT COUNT(*) as ?Triples
                FROM <http://dbpedia.org>
                WHERE {
                    <$parent_class> rdfs:subClassOf+ ?class
                }
                LIMIT 100";
            $rows = $sparql_client->query($query, 'rows');
            // Добавление ранжированного кандидата родительского класса в массив
            array_push($ranked_parent_class_candidates,
                [$parent_data_class_candidate['object'], $rows['result']['rows'][0]['Triples']]);
        }
        // Сортировка массива ранжированных кандидатов родительских классов по убыванию их ранга
        for ($i = 0; $i < count($ranked_parent_class_candidates); $i++)
            for ($j = $i + 1; $j < count($ranked_parent_class_candidates); $j++)
                if ($ranked_parent_class_candidates[$i][1] <= $ranked_parent_class_candidates[$j][1]) {
                    $temp = $ranked_parent_class_candidates[$j];
                    $ranked_parent_class_candidates[$j] = $ranked_parent_class_candidates[$i];
                    $ranked_parent_class_candidates[$i] = $temp;
                }

        return $ranked_parent_class_candidates;
    }

    /**
     * Генерация документа в формате RDF/XML.
     */
    public function generateRDFXMLCode()
    {
        // Создание документа DOM с кодировкой UTF8
        $xml = new DomDocument('1.0', 'UTF-8');
        // Создание корневого узла RDF с определением пространства имен
        $rdf_element = $xml->createElement('rdf:RDF');
        $rdf_element->setAttribute('xmlns:rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $rdf_element->setAttribute('xmlns:dbo', self::DBPEDIA_ONTOLOGY_SECTION);
        $rdf_element->setAttribute('xmlns:db', self::DBPEDIA_RESOURCE_SECTION);
        $rdf_element->setAttribute('xmlns:dbp', self::DBPEDIA_PROPERTY_SECTION);
        // Добавление корневого узла в XML-документ
        $xml->appendChild($rdf_element);
        // Создание узла триплета "Number"
        $number_element = $xml->createElement('rdf:Description');
        $number_element->setAttribute('rdf:about', 'http://dbpedia.org/resource/Number');
        $number_flag = false;
        // Создание узла триплета "Date"
        $date_element = $xml->createElement('rdf:Description');
        $date_element->setAttribute('rdf:about', 'http://dbpedia.org/resource/Date');
        $date_flag = false;
        // Цикл по всем найденным кандидатам для столбца DATA
        foreach($this->data_entities as $key => $value) {
            if ($value == 'http://dbpedia.org/resource/Number') {
                // Добавление узла триплета "Number" в корневой узел RDF, если он не добавлен
                if (!$number_flag) {
                    $rdf_element->appendChild($number_element);
                    $number_flag = true;
                }
                // Добавление объектов для триплета "Number"
                $node_element = $xml->createElement('dbp:titleNumber', $key);
                $number_element->appendChild($node_element);
            }
            if ($value == 'http://dbpedia.org/resource/Date') {
                // Добавление узла триплета "Date" в корневой узел RDF, если он не добавлен
                if (!$date_flag) {
                    $rdf_element->appendChild($date_element);
                    $date_flag = true;
                }
                // Добавление объектов для триплета "Date"
                $node_element = $xml->createElement('dbp:title', $key);
                $date_element->appendChild($node_element);
            }
        }
        // Сохранение RDF-файла
        $xml->formatOutput = true;
        $xml->save('example.rdf');
    }
}