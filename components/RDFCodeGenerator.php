<?php

namespace app\components;

use app\modules\main\models\AnnotatedRow;
use app\modules\main\models\AnnotatedCanonicalTable;

/**
 * RDFCodeGenerator.
 * Класс RDFCodeGenerator обеспечивает генерацию кода онтологической базы знаний в формате RDF/XML.
 */
class RDFCodeGenerator
{
    /**
     * Генерация кода RDF-триплетов.
     * @param $data - полученые данные из XLSX-файла электронной таблицы
     * @param $annotated_rows - строки аннотированной таблицы
     * @param $content - текущий текст RDF-файла
     * @return string - сформированный текст RDF-файла
     */
    public function generateTriplets($data, $annotated_rows, $content)
    {
        // Массив меток именованных сущностей
        $named_entity_type = array(
            CanonicalTableAnnotator::LOCATION_ONTOLOGY_CLASS,
            CanonicalTableAnnotator::PERSON_ONTOLOGY_CLASS,
            CanonicalTableAnnotator::ORGANISATION_ONTOLOGY_CLASS
        );
        // Массив меток литеральных сущностей
        $literal_type = array(
            CanonicalTableAnnotator::NUMBER_ONTOLOGY_INSTANCE,
            CanonicalTableAnnotator::MONEY_ONTOLOGY_INSTANCE,
            CanonicalTableAnnotator::PERCENT_ONTOLOGY_INSTANCE,
            CanonicalTableAnnotator::DATE_ONTOLOGY_INSTANCE,
            CanonicalTableAnnotator::TIME_ONTOLOGY_INSTANCE
        );
        // Обход массива данных исходной канонической таблицы
        $i = 0;
        foreach ($data as $item) {
            $j = 0;
            // Обход аннотированной канонической таблицы
            foreach ($annotated_rows as $annotated_row) {
                // Если совпадают номера строк таблиц
                if ($i == $j) {
                    // Обход массива строки исходной канонической таблицы
                    foreach ($item as $heading => $value) {
                        // Обработка блока данных
                        if ($heading == CanonicalTableAnnotator::DATA_TITLE) {
                            if ($annotated_row->data != null) {
                                if (in_array($annotated_row->data, $named_entity_type)) {
                                    $pos = strripos($annotated_row->data, '/');
                                    if ($pos != false) {
                                        $string_value = substr($annotated_row->data, 0, $pos);
                                        $content .= "\t<owl:Thing rdf:about=\"" . $string_value . "\">\r\n";
                                    }
                                }
                                if (in_array($annotated_row->data, $literal_type)) {
                                    $content .= "\t<base:" . $annotated_row->data .
                                        " rdf:about=\"http://www.example.org/#" . $value . "\">\r\n";
                                }
                            }
                        }
                        // Обработка блока заголовка "RowHeading"
                        if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE) {
                            $string_array = explode(" | ", $value);
                            foreach ($string_array as $key => $string) {
                                if ($annotated_row->row_heading != null) {
                                    if (in_array($annotated_row->row_heading, $named_entity_type)) {
                                        $pos = strripos($annotated_row->data, '/');
                                        if ($pos != false) {
                                            $string_value = substr($annotated_row->data, 0, $pos);
                                            $content .= "\t<owl:Thing rdf:about=\"" . $string_value . "\">\r\n";
                                        }
                                    }
                                    if (in_array($annotated_row->row_heading, $literal_type)) {
                                        $content .= "\t<base:" . $annotated_row->row_heading .
                                            " rdf:about=\"http://www.example.org/#" . $string . "\">\r\n";
                                    }
                                }
                            }
                        }
                        // Обработка блока заголовка "ColumnHeading"
                        if ($heading == CanonicalTableAnnotator::COLUMN_HEADING_TITLE) {
                            $string_array = explode(" | ", $value);
                            foreach ($string_array as $key => $string) {
                                if ($annotated_row->column_heading != null) {
                                    if (in_array($annotated_row->column_heading, $named_entity_type)) {
                                        $pos = strripos($annotated_row->data, '/');
                                        if ($pos != false) {
                                            $string_value = substr($annotated_row->data, 0, $pos);
                                            $content .= "\t<owl:Thing rdf:about=\"" . $string_value . "\">\r\n";
                                        }
                                    }
                                    if (in_array($annotated_row->column_heading, $literal_type)) {
                                        $content .= "\t<base:" . $annotated_row->column_heading .
                                            " rdf:about=\"http://www.example.org/#" . $string . "\">\r\n";
                                    }
                                }
                            }
                        }
                    }
                }
                $j++;
            }
            $i++;
        }

        return $content;
    }

    /**
     * Генерация кода базы знаний в формате RDF/XML.
     * @param $annotated_canonical_table_id - идентификатор аннотированной таблицы
     * @param $data - полученые данные из XLSX-файла электронной таблицы
     */
    public function generateRDFCode($annotated_canonical_table_id, $data)
    {
        // Поиск аннотированной таблицы в БД по идентификатору
        $annotated_canonical_table = AnnotatedCanonicalTable::findOne($annotated_canonical_table_id);
        // Поиск всех аннотированных строк в БД по идентификатору аннотированной таблицы
        $annotated_rows = AnnotatedRow::find()
            ->where(array('annotated_canonical_table' => $annotated_canonical_table->id))
            ->all();

        // Определение наименования файла
        $file = 'exported_knowledge_base.rdf';
        // Создание и открытие данного файла на запись, если он не существует
        if (!file_exists($file))
            fopen($file, 'w');

        // Начальное описание RDF-файла (онтологии)
        $content = "<?xml version=\"1.0\"?>\r\n";
        $content .= "<rdf:RDF\r\n";
        $content .= "\txmlns      = 'http://example.org/" . $annotated_canonical_table->name . "#\"\r\n";
        $content .= "\txml:base   = 'http://example.org/" . $annotated_canonical_table->name . "#\"\r\n";
        $content .= "\txmlns:owl  = \"http://www.w3.org/2002/07/owl#\"\r\n";
        $content .= "\txmlns:owl  = \"http://www.w3.org/2002/07/owl#\"\r\n";
        $content .= "\txmlns:rdf  = \"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\r\n";
        $content .= "\txmlns:rdfs = \"http://www.w3.org/2000/01/rdf-schema#\"\r\n";
        $content .= "\txmlns:xsd  = \"http://www.w3.org/2001/XMLSchema#\">\r\n";
        $content .= "\r\n";

        // Вызов метода генерации RDF-триплетов
        if (!empty($annotated_rows))
            $content = self::generateTriplets($data, $annotated_rows, $content . "\r\n");

        // Закрытие тега RDF
        $content .= "</rdf:RDF>";
        // Выдача RDF-файла пользователю для скачивания
        header("Content-type: application/octet-stream");
        header('Content-Disposition: filename="'.$file.'"');
        echo $content;
        exit;
    }
}