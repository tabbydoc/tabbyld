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
    const ROW_HEADING_TITLE = 'RowHeading1';
    const COLUMN_HEADING_TITLE = 'ColumnHeading';

    public $row_heading_concepts = array();
    public $column_heading_concepts = array();

    public function annotateTableHeading($data, $heading_title)
    {
        $formed_concepts = array();
        $formed_properties = array();
        $formed_entities = array();
        $all_class_query_runtime = 0;
        $all_concept_query_runtime = 0;
        $all_property_query_runtime = 0;
        $class_query_results = array();
        $concept_query_results = array();
        $property_query_results = array();
        $result = array();
        // Формирование массивов концептов (классов) и свойств
        foreach ($data as $item)
            foreach ($item as $heading => $value)
                if ($heading == $heading_title) {
                    $string_array = explode(" | ", $value);
                    foreach ($string_array as $string) {
                        // Формирование правильного значения для поиска концептов (классов)
                        $str = ucwords(strtolower($string));
                        $correct_string = str_replace(' ', '', $str);
                        $formed_concepts[$string] = $correct_string;
                        // Формирование правильного значения для поиска свойств класса (отношений)
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
        // Если нет ошибки в подключении
        if (!$error) {
            // Обход массива сформированных сущностей (классов и концептов)
            foreach ($formed_concepts as $fc_key => $fc_value) {
                // SPARQL-запрос к DBpedia ontology для поиска классов
                $query = "PREFIX dbo: <http://dbpedia.org/ontology/>
                    SELECT dbo:$fc_value ?property ?object
                    WHERE { dbo:$fc_value ?property ?object }
                    LIMIT 1";
                $rows = $sparql_client->query($query, 'rows');
                if ($rows["result"]["rows"]) {
                    array_push($class_query_results, $rows);
                    $all_class_query_runtime += $rows["query_time"];
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
                        $all_concept_query_runtime += $rows["query_time"];
                        $formed_entities[$fc_key] = $fc_key . ' (db:' . $fc_value . ')';
                    }
                    if (!$rows["result"]["rows"]) {
                        // Обход массива сформированных свойств классов (отношений)
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
                                    $all_property_query_runtime += $rows["query_time"];
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
                                        $all_property_query_runtime += $rows["query_time"];
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
        //
        if ($heading_title == self::ROW_HEADING_TITLE)
            $this->row_heading_concepts = $formed_entities;
        //
        if ($heading_title == self::COLUMN_HEADING_TITLE)
            $this->column_heading_concepts = $formed_entities;
        //
        array_push($result, $class_query_results, $concept_query_results, $property_query_results,
            $all_class_query_runtime, $all_concept_query_runtime, $all_property_query_runtime);

        return $result;
    }
}