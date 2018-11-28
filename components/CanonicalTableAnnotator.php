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

    public $row_heading_entities = array();    // Массив найденных сущностей для столбца "RowHeading"
    public $column_heading_entities = array(); // Массив найденных сущностей для столбца "ColumnHeading"

    /**
     * Аннотиция столбцов содержащих значения заголовков таблицы (RowHeading и ColumnHeading)
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
                        // Формирование массива корректных значений для поискаа концептов (классов)
                        $str = ucwords(strtolower($string));
                        $correct_string = str_replace(' ', '', $str);
                        $formed_concepts[$string] = $correct_string;
                        // Формирование массива корректных значений для поискаа свойств классов (отношений)
                        $str = lcfirst(ucwords(strtolower($string)));
                        $correct_string = str_replace(' ', '', $str);
                        $formed_properties[$string] = $correct_string;
                    }
                }
        // Подключение к DBpedia
        $endpoint = "http://dbpedia.org/sparql";
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
                if ($rows["result"]["rows"]) {
                    array_push($class_query_results, $rows);
                    $formed_entities[$fc_key] = $fc_key . ' (dbo:' . $fc_value . ')';
                }
                if (!$rows["result"]["rows"]) {
                    // SPARQL-запрос к DBpedia resource для поиска концептов
                    $query = "PREFIX db: <http://dbpedia.org/resource/>
                        SELECT db:$fc_value ?property ?object
                        WHERE { db:$fc_value ?property ?object }
                        LIMIT 1";
                    $rows = $sparql_client->query($query, 'rows');
                    if ($rows["result"]["rows"]) {
                        array_push($concept_query_results, $rows);
                        $formed_entities[$fc_key] = $fc_key . ' (db:' . $fc_value . ')';
                    }
                    if (!$rows["result"]["rows"]) {
                        // Обход массива корректных знаечний столбцов для поиска свойств класса (отношений)
                        foreach ($formed_properties as $fp_key => $fp_value) {
                            if ($fp_key == $fc_key) {
                                // SPARQL-запрос к DBpedia ontology для поиска свойств классов (отношений)
                                $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                                    SELECT ?concept dbo:$fp_value ?object
                                    WHERE { ?concept dbo:$fp_value ?object }
                                    LIMIT 1";
                                $rows = $sparql_client->query($query, 'rows');
                                if ($rows["result"]["rows"]) {
                                    array_push($property_query_results, $rows);
                                    $formed_entities[$fp_key] = $fp_key . ' (dbo:' . $fp_value . ')';
                                }
                                if (!$rows["result"]["rows"]) {
                                    // SPARQL-запрос к DBpedia property для поиска свойств классов (отношений)
                                    $query = "PREFIX dbp: <http://dbpedia.org/property/>
                                        SELECT ?concept dbp:$fp_value ?object
                                        WHERE { ?concept dbp:$fp_value ?object }
                                        LIMIT 1";
                                    $rows = $sparql_client->query($query, 'rows');
                                    if ($rows["result"]["rows"]) {
                                        array_push($property_query_results, $rows);
                                        $formed_entities[$fp_key] = $fp_key . ' (dbp:' . $fp_value . ')';
                                    }
                                    if (!$rows["result"]["rows"])
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
}