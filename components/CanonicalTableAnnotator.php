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
    const DATA_TITLE = 'DATA';                    // Имя первого заголовка столбца канонической таблицы
    const ROW_HEADING_TITLE = 'RowHeading1';      // Имя второго заголовка столбца канонической таблицы
    const COLUMN_HEADING_TITLE = 'ColumnHeading'; // Имя третьего заголовка столбца канонической таблицы

    public $data_entities = array();           // Массив найденных концептов для столбца с данными "DATA"
    public $row_heading_entities = array();    // Массив найденных сущностей для столбца "RowHeading"
    public $column_heading_entities = array(); // Массив найденных сущностей для столбца "ColumnHeading"

    /**
     * Аннотирование столбцов "RowHeading" и "ColumnHeading" содержащих значения заголовков исходной таблицы.
     *
     * @param $data - данные каноничечкой таблицы
     * @param $heading_title - имя столбца
     * @return array - массив с результатми поиска сущностей в онтологии DBpedia
     */
    public function annotateTableHeading($data, $heading_title)
    {
        $formed_concepts = array();
        $formed_properties = array();
        $formed_entities = array();
        $class_query_results = array();
        $concept_query_results = array();
        $property_query_results = array();
        // Формирование массивов неповторяющихся корректных значений столбцов для поиска концептов и свойств в отнологии
        foreach ($data as $item)
            foreach ($item as $heading => $value)
                if ($heading == $heading_title) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $string) {
                        // Формирование массива корректных значений для поиска концептов (классов)
                        $str = ucwords(strtolower($string));
                        $correct_string = str_replace(' ', '', $str);
                        $formed_concepts[$string] = $correct_string;
                        // Формирование массива корректных значений для поиска свойств классов (отношений)
                        $str = lcfirst(ucwords(strtolower($string)));
                        $correct_string = str_replace(' ', '', $str);
                        $formed_properties[$string] = $correct_string;
                    }
                }
        // Подключение к DBpedia
        $endpoint = 'http://dbpedia.org/sparql';
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead($endpoint);
        $error = $sparql_client->getErrors();
        // Если нет ошибок при подключении
        if (!$error) {
            // Обход массива корректных значений столбцов для поиска классов и концептов
            foreach ($formed_concepts as $fc_key => $fc_value) {
                // SPARQL-запрос к DBpedia ontology для поиска классов
                $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                    SELECT dbo:$fc_value ?property ?object
                    WHERE { dbo:$fc_value ?property ?object }
                    LIMIT 1";
                $rows = $sparql_client->query($query, 'rows');
                if (isset($rows['result']) && $rows['result']['rows']) {
                    array_push($class_query_results, $rows);
                    $formed_entities[$fc_key] = 'http://dbpedia.org/ontology/' . $fc_value;
                }
                else {
                    // SPARQL-запрос к DBpedia resource для поиска концептов
                    $query = "PREFIX db: <http://dbpedia.org/resource/>
                        SELECT db:$fc_value ?property ?object
                        WHERE { db:$fc_value ?property ?object }
                        LIMIT 1";
                    $rows = $sparql_client->query($query, 'rows');
                    if (isset($rows['result']) && $rows['result']['rows']) {
                        array_push($concept_query_results, $rows);
                        $formed_entities[$fc_key] = 'http://dbpedia.org/resource/' . $fc_value;
                    }
                    else {
                        // Обход массива корректных знаечний столбцов для поиска свойств класса (отношений)
                        foreach ($formed_properties as $fp_key => $fp_value) {
                            if ($fp_key == $fc_key) {
                                // SPARQL-запрос к DBpedia ontology для поиска свойств классов (отношений)
                                $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                                    SELECT ?concept dbo:$fp_value ?object
                                    WHERE { ?concept dbo:$fp_value ?object }
                                    LIMIT 1";
                                $rows = $sparql_client->query($query, 'rows');
                                if (isset($rows['result']) && $rows['result']['rows']) {
                                    array_push($property_query_results, $rows);
                                    $formed_entities[$fp_key] = 'http://dbpedia.org/ontology/' . $fp_value;
                                }
                                else {
                                    // SPARQL-запрос к DBpedia property для поиска свойств классов (отношений)
                                    $query = "PREFIX dbp: <http://dbpedia.org/property/>
                                        SELECT ?concept dbp:$fp_value ?object
                                        WHERE { ?concept dbp:$fp_value ?object }
                                        LIMIT 1";
                                    $rows = $sparql_client->query($query, 'rows');
                                    if (isset($rows['result']) && $rows['result']['rows']) {
                                        array_push($property_query_results, $rows);
                                        $formed_entities[$fp_key] = 'http://dbpedia.org/property/' . $fp_value;
                                    } else
                                        $formed_entities[$fp_key] = $fp_key;
                                }
                            }
                        }
                    }
                }
            }
        }
        // Сохранение результатов аннотирования для столбцов с заголовками
        if ($heading_title == self::ROW_HEADING_TITLE)
            $this->row_heading_entities = $formed_entities;
        if ($heading_title == self::COLUMN_HEADING_TITLE)
            $this->column_heading_entities = $formed_entities;

        return array($class_query_results, $concept_query_results, $property_query_results);
    }

    /**
     * Аннотирование содержимого столбца "DATA".
     *
     * @param $data - данные каноничечкой таблицы
     * @return array - массив с результатми поиска концептов в онтологии DBpedia
     */
    public function annotateTableData($data)
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
        // Подключение к DBpedia
        $endpoint = 'http://dbpedia.org/sparql';
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead($endpoint);
        $error = $sparql_client->getErrors();
        // Если нет ошибок при подключении
        if (!$error) {
            // Обход массива корректных значений столбца с данными для поиска подходящих концептов
            foreach ($formed_concepts as $key => $value) {
                // SPARQL-запрос к DBpedia resource для поиска концептов
                $query = "PREFIX db: <http://dbpedia.org/resource/>
                    SELECT db:$value ?property ?object
                    WHERE { db:$value ?property ?object }
                    LIMIT 1";
                $rows = $sparql_client->query($query, 'rows');
                if (isset($rows['result']) && $rows['result']['rows']) {
                    array_push($concept_query_results, $rows);
                    $this->data_entities[$key] = 'http://dbpedia.org/resource/' . $value;
                } else
                    $this->data_entities[$key] = $key;
            }
        }

        return $concept_query_results;
    }

    /**
     * Отображение сокращенного имени аннотированной сущности.
     *
     * @param $formed_entities - массив всех сформированных сущностей (аннотированных значениям в таблице)
     * @param $cell_label - исходное название ячейки таблицы
     * @param $string_array - массив значений (меток) заголовков в ячейке таблицы
     * @param $current_key - текущий ключ значения (метки) заголовка в ячейке таблицы
     * @param $current_value - текущее значение (метка) заголовка в ячейке таблицы
     * @return string - сформированное название ячейки таблицы
     */
    public function displayAbbreviatedEntity($formed_entities, $cell_label, $string_array, $current_key, $current_value)
    {
        // Массив названий сегментов онтологии DBpedia
        $ontology_segment_name = [
            'http://dbpedia.org/ontology/',
            'http://dbpedia.org/resource/',
            'http://dbpedia.org/property/'
        ];
        // Массив префиксов для сегментов онтологии DBpedia
        $ontology_segment_prefix = ['dbo:', 'db:', 'dbp:'];
        // Цикл по массиву аннотированных элементов
        foreach ($formed_entities as $key => $formed_entity)
            if ($current_value == $key) {
                $abbreviated_concept = str_replace($ontology_segment_name,
                    $ontology_segment_prefix, $formed_entity, $count);
                if ($current_key > 0 && $count == 0)
                    $cell_label .= $current_value;
                if ($current_key == 0 && count($string_array) == 1 && $count == 0)
                    $cell_label = $current_value;
                if ($current_key == 0 && count($string_array) > 1 && $count == 0)
                    $cell_label = $current_value . ' | ';
                if ($current_key > 0 && $count > 0)
                    $cell_label .= $current_value . ' (' . $abbreviated_concept . ')';
                if ($current_key == 0 && count($string_array) == 1 && $count > 0)
                    $cell_label = $current_value . ' (' . $abbreviated_concept . ')';
                if ($current_key == 0 && count($string_array) > 1 && $count > 0)
                    $cell_label = $current_value . ' (' . $abbreviated_concept . ') | ';
            }

        return $cell_label;
    }
}