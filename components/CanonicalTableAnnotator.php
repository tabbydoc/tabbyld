<?php

namespace app\components;

use DOMDocument;
use BorderCloud\SPARQL\SparqlClient;

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
    const UNDEFINED_NER_LABEL = 'UNDEFINED';
    const LOCATION_NER_LABEL = 'LOCATION';
    const PERSON_NER_LABEL = 'PERSON';
    const ORGANIZATION_NER_LABEL = 'ORGANIZATION';

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

    public $data_entities = array();           // Массив найденных концептов для столбца с данными "DATA"
    public $row_heading_entities = array();    // Массив найденных сущностей для столбца "RowHeading"
    public $column_heading_entities = array(); // Массив найденных сущностей для столбца "ColumnHeading"

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

    /**
     * Идентификация типа таблицы по столбцу DATA.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_labels - данные с NER-метками
     */
    public function identifyTableType($data, $ner_labels)
    {
        $formed_concepts = array();
        $formed_ner_concepts = array();
        // Счетчик для кол-ва значений ячеек столбца с данными
        $i = 0;
        // Цикл по всем ячейкам столбца с данными
        foreach ($data as $item)
            foreach ($item as $key => $value)
                if ($key == self::DATA_TITLE) {
                    $i++;
                    $formed_concepts[$value] = $value;
                    // Счетчик для кол-ва NER-меток для ячеек DATA
                    $j = 0;
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_labels as $ner_item)
                        foreach ($ner_item as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                $j++;
                                if ($i == $j)
                                    $formed_ner_concepts[$value] = $ner_value;
                            }
                }
        // Название метки NER
        $ner_label = '';
        // Обход массива значений столбца с метками NER
        foreach ($formed_concepts as $key => $value)
            foreach ($formed_ner_concepts as $ner_key => $ner_value)
                if ($key == $ner_key)
                    $ner_label = $ner_value;
        // Если в столбце DATA содержаться литералы, то устанавливаем стратегию аннотирования литеральных значений
        if (in_array($ner_label, array(self::NUMBER_NER_LABEL, self::DATE_NER_LABEL, self::TIME_NER_LABEL,
            self::MONEY_NER_LABEL, self::PERCENT_NER_LABEL)))
            $this->current_annotation_strategy_type = self::LITERAL_STRATEGY;
        // Если в столбце DATA содержаться сущности, то устанавливаем стратегию аннотирования именованных сущностей
        if (in_array($ner_label, array(self::UNDEFINED_NER_LABEL, self::LOCATION_NER_LABEL, self::PERSON_NER_LABEL,
            self::ORGANIZATION_NER_LABEL)))
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
                    ?subject ?property ?object
                    FILTER regex(str(?subject), '$value', 'i')
                    FILTER(strstarts(str(?subject), '$section'))
                } LIMIT 10";
        else
            // SPARQL-запрос к DBpedia для поиска сущностей кандидатов
            $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                PREFIX db: <http://dbpedia.org/resource/>
                PREFIX owl: <http://www.w3.org/2002/07/owl#>
                SELECT ?subject rdf:type ?object
                FROM <http://dbpedia.org>
                WHERE { ?subject a ?object . FILTER ( regex(str(?subject), '$value', 'i') &&
                    ( (strstarts(str(?subject), str(dbo:)) && (str(?object) = str(owl:Class))) ||
                    (strstarts(str(?subject), str(db:)) && (str(?object) = str(owl:Thing))) ) )
            } LIMIT 10";
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
            array_push($ranked_candidate_entities, [$candidate_entity_name, $distance]);
        }

        return $ranked_candidate_entities;
    }

    /**
     * Нахождение связей между сущностями кандидатами.
     *
     * @param $all_candidate_entities - множество всех массивов сущностей кандидатов
     * @return array - ранжированный массив сущностей кандидатов
     */
    public function getRelationshipDistance($all_candidate_entities)
    {
        // Массив ранжированных сущностей кандидатов
        $ranked_candidate_entities = array();
        // Массив названий URI DBpedia
        $URIs = array(self::DBPEDIA_ONTOLOGY_SECTION, self::DBPEDIA_RESOURCE_SECTION, self::DBPEDIA_PROPERTY_SECTION);
        // Обход всех наборов сущностей кандидатов
        foreach ($all_candidate_entities as $key => $candidate_entities) {
            // Массив сущностей кандидатов ранжированных по связям
            $relationship_distance_array = array();
            // Обход всех сущностей кандидатов из набора
            foreach ($candidate_entities as $candidate_entity) {
                // Удаление адреса URI у сущности кандидата
                $candidate_entity_name = str_replace($URIs, '', $candidate_entity);
                // Текущее растояние
                $distance = 0;
                // Обход всех сущностей кандидатов из набора
                foreach ($all_candidate_entities as $items)
                    foreach ($items as $item) {
                        // Удаление адреса URI у сущности кандидата
                        $item_name = str_replace($URIs, '', $item);
                        // Если эта не одна и тажа сущность
                        if ($candidate_entity_name != $item_name) {
                            // Подключение к DBpedia
                            $sparql_client = new SparqlClient();
                            $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
                            // SPARQL-запрос к DBpedia для поиска сущностей кандидатов
                            $query = "PREFIX db: <http://dbpedia.org/resource/>
                                SELECT db:$candidate_entity_name ?property db:$item
                                FROM <http://dbpedia.org>
                                WHERE { db:$candidate_entity_name ?property db:$item }
                                LIMIT 1";
                            $rows = $sparql_client->query($query, 'rows');
                            $error = $sparql_client->getErrors();
                            // Если нет ошибок при запросе и есть результат запроса
                            if (!$error && $rows['result']['rows'])
                                $distance++;
                        }
                    }
                // Формирование массива ранжированных сущностей кандидатов по связям
                array_push($relationship_distance_array, [$candidate_entity_name, $distance]);
            }
            // Формирование массива ранжированных сущностей кандидатов для текущего значения ячейки
            $ranked_candidate_entities[$key] = $relationship_distance_array;
        }

        return $ranked_candidate_entities;
    }

    /**
     * Получение агрегированных оценок (рангов) для сущностей кандидатов.
     *
     * @param $levenshtein_distance_candidate_entities - ранжированный массив сущностей кандидатов по расстоянию Левенштейна
     * @param $a_weight_factor - весовой фактор А для определения важности оценки расстояния Левенштейна
     * @param $relationship_distance_candidate_entities - ранжированный массив сущностей кандидатов по связям
     * @param $b_weight_factor - весовой фактор B для определения важности оценки по связям
     * @return array - ранжированный массив сущностей кандидатов с агрегированными оценками
     */
    public function getAggregatedRanks($levenshtein_distance_candidate_entities, $a_weight_factor,
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
                        foreach ($rd_items as $rd_key => $relationship_distance_candidate_entity)
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
     * Аннотирование столбцов "RowHeading" и "ColumnHeading" содержащих значения заголовков исходной таблицы.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $heading_title - имя столбца
     * @return array - массив с результатми поиска сущностей в онтологии DBpedia
     */
    public function annotateTableHeading($data, $heading_title)
    {
        $formed_labels = array();
        // Формирование массивов неповторяющихся корректных значений столбцов для поиска сущностей в отнологии
        foreach ($data as $item)
            foreach ($item as $heading => $value)
                if ($heading == $heading_title) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $key => $string)
                        // Формирование массива корректных значений для поиска сущностей
                        $formed_labels[$string] = [$key, str_replace(' ', '', $string)];
                }
        // Массив для хранения массивов сущностей кандидатов
        $all_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов по расстоянию Левенштейна
        $levenshtein_distance_candidate_entities = array();
        // Обход массива корректных значений столбцов для поиска классов и концептов
        foreach ($formed_labels as $key => $label) {
            // Формирование набора сущностей кандидатов (классов и объектов)
            $candidate_entities = $this->getCandidateEntities($label[1]);
            // Если набор сущностей кандидатов сформирован
            if ($candidate_entities) {
                // Вычисление расстояния Левенштейна для каждой сущности из набора кандидатов
                $levenshtein_distance_candidate_entities[$key] = $this->getLevenshteinDistance($label[1],
                    $candidate_entities);
                // Добавление массива сущностей кандидатов в общий набор
                $all_candidate_entities[$key] = $candidate_entities;
            }
        }
        // Нахождение связей между сущностями кандидатов
        $relationship_distance_candidate_entities = $this->getRelationshipDistance($all_candidate_entities);
        // Получение агрегированных оценок (рангов) для сущностей кандидатов
        $ranked_candidate_entities = $this->getAggregatedRanks($levenshtein_distance_candidate_entities, 1,
            $relationship_distance_candidate_entities, 1);
        // Сохранение результатов аннотирования для столбцов с заголовками
        if ($heading_title == self::ROW_HEADING_TITLE)
            $this->row_heading_entities = $ranked_candidate_entities;
        if ($heading_title == self::COLUMN_HEADING_TITLE)
            $this->column_heading_entities = $ranked_candidate_entities;

        return $ranked_candidate_entities;
    }

    /**
     * Аннотирование содержимого столбца "DATA" с литеральными значениями.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $ner_labels - данные с NER-метками
     * @return array - массив с результатми поиска объектов и классов в онтологии DBpedia
     */
    public function annotateTableLiteralData($data, $ner_labels)
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
                    // Формирование массива корректных значений ячеек столбца с данными
                    $str = ucwords(strtolower($value));
                    $correct_string = str_replace(' ', '', $str);
                    $formed_data_entries[$value] = $correct_string;
                    // Счетчик для кол-ва NER-меток для ячеек DATA
                    $j = 0;
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_labels as $ner_item)
                        foreach ($ner_item as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                $j++;
                                // Формирование массива соответсвий NER-меток значениям столбца с данными
                                if ($i == $j) {
                                    $str = ucwords(strtolower($ner_value));
                                    $correct_string = str_replace(' ', '', $str);
                                    $formed_ner_labels[$value] = $correct_string;
                                }
                            }
                }
        $concept_query_results = array();
        // Обход массива корректных значений столбца с данными для поиска подходящих объектов или классов
        foreach ($formed_data_entries as $key => $entry)
            foreach ($formed_ner_labels as $ner_key => $ner_label)
                if ($key == $ner_key) {
                    // Подключение к DBpedia
                    $sparql_client = new SparqlClient();
                    $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
                    // SPARQL-запрос к DBpedia для поиска точного совпадения с классом или объектом
                    $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                        PREFIX db: <http://dbpedia.org/resource/>
                        SELECT ?subject ?property ?object {
                            ?subject ?property ?object . FILTER (?subject = dbo:$ner_label || ?subject = db:$ner_label)
                        } LIMIT 1";
                    $rows = $sparql_client->query($query, 'rows');
                    $error = $sparql_client->getErrors();
                    // Если нет ошибок при запросе и есть результат запроса
                    if (!$error && $rows['result']['rows']) {
                        array_push($concept_query_results, $rows);
                        $this->data_entities[$key] = $rows['result']['rows'][0]['subject'];
                    }
                }

        return $concept_query_results;
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
        // Счетчик для кол-ва значений ячеек столбца с данными
        $i = 0;
        // Цикл по всем ячейкам столбца с данными
        foreach ($data as $item)
            foreach ($item as $key => $value)
                if ($key == self::DATA_TITLE) {
                    $i++;
                    // Формирование массива корректных значений ячеек столбца с данными
                    $str = ucwords(strtolower($value));
                    $correct_string = str_replace(' ', '', $str);
                    $formed_data_entries[$value] = $correct_string;
                    // Счетчик для кол-ва NER-меток для ячеек DATA
                    $j = 0;
                    // Цикл по всем ячейкам столбца с NER-метками
                    foreach ($ner_labels as $ner_item)
                        foreach ($ner_item as $ner_key => $ner_value)
                            if ($ner_key == self::DATA_TITLE) {
                                $j++;
                                // Формирование массива соответсвий NER-меток значениям столбца с данными
                                if ($i == $j) {
                                    $str = ucwords(strtolower($ner_value));
                                    $correct_string = str_replace(' ', '', $str);
                                    $formed_ner_labels[$value] = $correct_string;
                                }
                            }
                }
        $concept_query_results = array();

        // Массив для хранения массивов сущностей кандидатов
        $all_candidate_entities = array();
        // Массив для ранжированных сущностей кандидатов по расстоянию Левенштейна
        $levenshtein_distance_candidate_entities = array();
        // Обход массива корректных значений столбца данных для поиска сущностей
        foreach ($formed_data_entries as $key => $entry) {
            // Формирование набора сущностей-кандидатов
            $candidate_entities = $this->getCandidateEntities($entry, self::DBPEDIA_RESOURCE_SECTION);
            // Если набор сущностей-кандидатов сформирован
            if ($candidate_entities) {
                // Вычисление расстояния Левенштейна для каждой сущности из набора кандидатов
                $levenshtein_distance_candidate_entities[$key] = $this->getLevenshteinDistance($entry,
                    $candidate_entities);
                // Добавление массива сущностей кандидатов в общий набор
                $all_candidate_entities[$key] = $candidate_entities;
            }
        }
        // Сохранение результатов аннотирования для столбца с данными
        $this->data_entities = $all_candidate_entities;

        return $concept_query_results;
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