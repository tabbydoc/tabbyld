<?php

namespace app\components;

use BorderCloud\SPARQL\SparqlClient;

/**
 * Class CanonicalTableAnnotator.
 *
 * @package app\components
 */
class CanonicalTableAnnotator
{
    // Названия меток NER-аннотатора StanfordNLP
    const NUMBER_NER_LABEL = 'NUMBER';
    const DATE_NER_LABEL = 'DATE';
    const UNDEFINED_NER_LABEL = 'UNDEFINED';
    const DURATION_NER_LABEL = 'DURATION';

    const ENDPOINT_NAME = 'http://dbpedia.org/sparql'; // Название точки доступа SPARQL
    const DATA_TITLE = 'DATA';                         // Имя первого заголовка столбца канонической таблицы
    const ROW_HEADING_TITLE = 'RowHeading1';           // Имя второго заголовка столбца канонической таблицы
    const COLUMN_HEADING_TITLE = 'ColumnHeading';      // Имя третьего заголовка столбца канонической таблицы

    const LITERAL_STRATEGY = 'Literal annotation';             // Стратегия аннотирования литеральных значений
    const NAMED_ENTITY_STRATEGY = 'Named entities annotation'; // Стратегия аннотирования именованных сущностей

    public $annotation_strategy_type; // Тип стратегии аннотирования

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
        if ($ner_label == self::NUMBER_NER_LABEL || $ner_label == self::DATE_NER_LABEL)
            $this->annotation_strategy_type = self::LITERAL_STRATEGY;
        // Если в столбце DATA содержаться сущности, то устанавливаем стратегию аннотирования именованных сущностей
        if ($ner_label == self::UNDEFINED_NER_LABEL || $ner_label == self::DURATION_NER_LABEL)
            $this->annotation_strategy_type = self::NAMED_ENTITY_STRATEGY;
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
        $formed_concepts = array();
        $formed_ner_concepts = array();
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
                    $formed_concepts[$value] = $correct_string;
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
                                    $formed_ner_concepts[$value] = $correct_string;
                                }
                            }
                }
        $concept_query_results = array();
        // Обход массива корректных значений столбца с данными для поиска подходящих объектов или классов
        foreach ($formed_concepts as $key => $value)
            foreach ($formed_ner_concepts as $ner_key => $ner_value)
                if ($key == $ner_key) {
                    // Подключение к DBpedia
                    $sparql_client = new SparqlClient();
                    $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
                    // SPARQL-запрос к DBpedia для поиска точного совпадения с классом или объектом
                    $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                        PREFIX db: <http://dbpedia.org/resource/>
                        SELECT ?subject ?property ?object {
                            ?subject ?property ?object . FILTER (?subject = dbo:$ner_value || ?subject = db:$ner_value)
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
            $query = "SELECT ?subject ?property ?object
                FROM <http://dbpedia.org>
                WHERE {
                    ?subject ?property ?object
                    FILTER regex(str(?subject), '$value', 'i')
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
        $URIs = array('http://dbpedia.org/ontology/', 'http://dbpedia.org/resource/', 'http://dbpedia.org/property/');
        // Обход всех сущностей кандидатов из набора
        foreach ($candidate_entities as $candidate_entity) {
            // Удаление адреса URI у сущности кандидата
            $candidate_entity_name = str_replace($URIs, '', $candidate_entity);
            // Вычисление расстояния Левенштейна между входным значением и текущим названием сущности кандидата
            $distance = levenshtein($input_value, $candidate_entity_name);
            // Формирование массива ранжированных сущностей кандидатов
            array_push($ranked_candidate_entities, [$candidate_entity_name, $distance]);
        }
        // Сортировка массива ранжированных сущностей кандидатов по возрастанию их ранга (расстояния Левенштейна)
        for ($i = 0; $i < count($ranked_candidate_entities); $i++)
            for ($j = $i + 1; $j < count($ranked_candidate_entities); $j++)
                if ($ranked_candidate_entities[$i][1] >= $ranked_candidate_entities[$j][1]) {
                    $temp = $ranked_candidate_entities[$j];
                    $ranked_candidate_entities[$j] = $ranked_candidate_entities[$i];
                    $ranked_candidate_entities[$i] = $temp;
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
        $formed_entities = array();
        // Формирование массивов неповторяющихся корректных значений столбцов для поиска сущностей в отнологии
        foreach ($data as $item)
            foreach ($item as $heading => $value)
                if ($heading == $heading_title) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $key => $string)
                        // Формирование массива корректных значений для поиска сущностей
                        $formed_entities[$string] = [$key, str_replace(' ', '', $string)];
                }
        $class_query_results = array();
        $ranked_candidate_entities = array();
        // Обход массива корректных значений столбцов для поиска классов и концептов
        foreach ($formed_entities as $key => $value) {
            // Если текущее значение ячейки отмечено как начальное
            if ($value[0] == 0) {
                // Формирование набора классов кандидатов
                $candidate_entities = $this->getCandidateEntities($value[1], 'http://dbpedia.org/ontology/');
                // Если набор классов кандидатов сформирован, то вычисляем для них расстояние Левенштейна
                if ($candidate_entities)
                    $ranked_candidate_entities[$key] = $this->getLevenshteinDistance($value[1], $candidate_entities);
                else {
                    // Формирование набора объектов и свойств кандидатов
                    $candidate_entities = $this->getCandidateEntities($value[1], 'http://dbpedia.org/resource/');
                    // Если набор объектов и свойств кандидатов сформирован, то вычисляем для них расстояние Левенштейна
                    if ($candidate_entities)
                        $ranked_candidate_entities[$key] = $this->getLevenshteinDistance($value[1], $candidate_entities);
                }
            }
            // Если текущее значение ячейки не является начальным
            if ($value[0] == 1) {
                // Формирование набора классов, объектов и свойств кандидатов
                $candidate_entities = $this->getCandidateEntities($value[1]);
                // Если набор сущностей кандидатов сформирован, то вычисляем для них расстояние Левенштейна
                if ($candidate_entities)
                    $ranked_candidate_entities[$key] = $this->getLevenshteinDistance($value[1], $candidate_entities);
            }
        }
        // Сохранение результатов аннотирования для столбцов с заголовками
        if ($heading_title == self::ROW_HEADING_TITLE)
            $this->row_heading_entities = $ranked_candidate_entities;
        if ($heading_title == self::COLUMN_HEADING_TITLE)
            $this->column_heading_entities = $ranked_candidate_entities;

        return $class_query_results;
    }

    /**
     * Аннотирование содержимого столбца "DATA" с именованными сущностями.
     *
     * @param $data - данные каноничечкой таблицы
     * @return array - массив с результатми поиска концептов в онтологии DBpedia
     */
    public function annotateTableEntityData($data)
    {
        $formed_concepts = array();
        // Формирование массива неповторяющихся корректных значений столбца с данными для поиска концептов в отнологии
        foreach ($data as $item)
            foreach ($item as $key => $value)
                if ($key == self::DATA_TITLE) {
                    // Формирование массива корректных значений для поиска концептов (классов)
                    $str = ucwords(strtolower($value));
                    $correct_string = str_replace(' ', '', $str);
                    $formed_concepts[$value] = $correct_string;
                }
        $concept_query_results = array();
        // Обход массива корректных значений столбца с данными для поиска подходящих концептов
        foreach ($formed_concepts as $key => $value) {
            // Подключение к DBpedia
            $sparql_client = new SparqlClient();
            $sparql_client->setEndpointRead(self::ENDPOINT_NAME);
            // SPARQL-запрос к DBpedia resource для поиска концептов
            $query = "PREFIX db: <http://dbpedia.org/resource/>
                SELECT db:$value ?property ?object
                WHERE { db:$value ?property ?object }
                LIMIT 1";
            $rows = $sparql_client->query($query, 'rows');
            $error = $sparql_client->getErrors();
            // Если нет ошибок при запросе и есть результат запроса
            if (!$error && $rows['result']['rows']) {
                array_push($concept_query_results, $rows);
                $this->data_entities[$key] = 'http://dbpedia.org/resource/' . $value;
                // Определение возможных родительских классов для найденного концепта
                $this->searchParentClasses($sparql_client, $this->data_entities[$key], self::DATA_TITLE);
            }
        }

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
}