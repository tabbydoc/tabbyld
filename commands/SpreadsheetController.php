<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use moonland\phpexcel\Excel;
use BorderCloud\SPARQL\SparqlClient;
use app\modules\main\models\ExcelFileForm;
use app\components\CanonicalTableAnnotator;

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
     * @param $path - путь для сохранения файла
     */
    public function actionAnnotateLiteralCell($ner_resource, $entry, $path)
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
        // Кодирование в имени файла запрещенных символов
        $correct_entry = CanonicalTableAnnotator::encodeFileName($entry);
        // Открытие файла на запись для сохранения результатов аннотирования ячейки с данными
        $result_file = fopen($path . $correct_entry . '.log', 'a');
        // Запись в файл логов результатов аннотирования ячейки с данными
        fwrite($result_file, $found_entity . PHP_EOL);
        // Запись в файл логов времени выполнения запроса
        fwrite($result_file, 'SPARQL_EXECUTION_TIME: ' . $rows['query_time']);
        // Закрытие файла
        fclose($result_file);
    }

    /**
     * Поиск и формирование массива сущностей кандидатов.
     *
     * @param $value - значение для поиска сущностей кандидатов по вхождению
     * @param $entry - оригинальное значение в ячейки таблицы
     * @param $path - путь для сохранения файла
     */
    public function actionGetCandidateEntities($value, $entry, $path)
    {
        // Массив сущностей кандидатов
        $candidate_entities = array();
        // Подключение к DBpedia
        $sparql_client = new SparqlClient();
        $sparql_client->setEndpointRead(CanonicalTableAnnotator::ENDPOINT_NAME);
        // SPARQL-запрос к DBpedia для поиска сущностей кандидатов
        $query = "PREFIX db: <http://dbpedia.org/resource/>
            PREFIX owl: <http://www.w3.org/2002/07/owl#>
            SELECT ?subject rdf:type ?object
            FROM <http://dbpedia.org>
            WHERE { ?subject a ?object . FILTER ( regex(str(?subject), '$value', 'i') &&
                (strstarts(str(?subject), str(db:)) && (str(?object) = str(owl:Thing))) )
        } LIMIT 100";
        $rows = $sparql_client->query($query, 'rows');
        $error = $sparql_client->getErrors();
        // Если нет ошибок при запросе и есть результат запроса
        if (!$error && $rows['result']['rows'])
            // Формирование массива сущностей кандидатов
            foreach ($rows['result']['rows'] as $row)
                array_push($candidate_entities, $row['subject']);
        // Кодирование в имени файла запрещенных символов
        $correct_entry = CanonicalTableAnnotator::encodeFileName($entry);
        // Открытие файла на запись для сохранения результатов поиска сущностей кандидатов
        $result_file = fopen($path . $correct_entry . '.log', 'a');
        // Запись в файл логов результатов аннотирования ячейки с данными
        foreach ($candidate_entities as $candidate_entity) {
            $correct_candidate_entity = str_replace('\/', '/', $candidate_entity);
            fwrite($result_file, $correct_candidate_entity . PHP_EOL);
        }
        // Запись в файл логов времени выполнения запроса
        fwrite($result_file, 'SPARQL_EXECUTION_TIME: ' . $rows['query_time']);
        // Закрытие файла
        fclose($result_file);
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
                // Если элемент каталога не является каталогом, файлом с результатами вычисления расстояния Левенштейна
                // и если название файла не совпадает с текущим выбранным элементом
                if ($file_name != '.' && $file_name != '..' && is_file($file_name) &&
                    $file_name != 'levenshtein_results.log' && $file_name != $correct_current_label . '.log') {
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
                        // Поиск в строке метки "SPARQL_EXECUTION_TIME"
                        $pos = strpos($text, 'SPARQL_EXECUTION_TIME');
                        // Формирование массива сущностей кандидатов
                        if ($text != '' && $pos === false)
                            array_push($candidate_entities, $text);
                    }
                    // Закрытие файла
                    fclose($fp);
                    // Добавление массива сущностей кандидатов в общий набор
                    $all_candidate_entities[$current_label] = $candidate_entities;
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
        // Начало отсчета времени выполнения скрипта
        $start = microtime(true);
        // Открытие каталога с набором данных электронных таблиц
        if ($handle = opendir('web/dataset/')) {
            // Чтения элементов (файлов) каталога
            while (false !== ($file_name = readdir($handle))) {
                // Если элемент каталога не является другим каталогом
                if ($file_name != '.' && $file_name != '..') {
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
                    // Если не существует директории для хранения результатов аннотирования таблицы
                    if (!file_exists('web/results/' . $file_name_without_extension . '/'))
                        // Создание директории для хранения результатов аннотирования таблицы
                        mkdir(Yii::$app->basePath . '\web\results\\' .
                            $file_name_without_extension, 0755);
                    // Путь до файла с результатами аннотирования ячеек столбца с данными "DATA"
                    $data_path = 'web/results/' . $file_name_without_extension . '/data_result/';
                    // Создание директории для хранения результатов аннотирования ячеек столбца с данными "DATA"
                    if (!file_exists($data_path))
                        mkdir(Yii::$app->basePath . '\web\results\\' . $file_name_without_extension .
                            '\data_result', 0755);
                    // Путь до файла с результатами аннотирования ячеек столбца с данными "RowHeading"
                    $row_heading_path = 'web/results/' . $file_name_without_extension . '/row_heading_result/';
                    // Создание директории для хранения результатов аннотирования ячеек столбца с заголовками "RowHeading"
                    if (!file_exists($row_heading_path))
                        mkdir(Yii::$app->basePath . '\web\results\\' . $file_name_without_extension .
                            '\row_heading_result', 0755);
                    // Путь до файла с результатами аннотирования ячеек столбца с данными "ColumnHeading"
                    $column_heading_path = 'web/results/' . $file_name_without_extension . '/column_heading_result/';
                    // Создание директории для хранения результатов аннотирования ячеек столбца с заголовками "ColumnHeading"
                    if (!file_exists($column_heading_path))
                        mkdir(Yii::$app->basePath . '\web\results\\' . $file_name_without_extension .
                            '\column_heading_result', 0755);

                    // Массивы для ранжированных сущностей кандидатов
                    $ranked_data_candidate_entities = array();
                    $ranked_row_heading_candidate_entities = array();
                    $ranked_column_heading_candidate_entities = array();
                    // Создание объекта аннотатора таблиц
                    $annotator = new CanonicalTableAnnotator();
                    // Идентификация типа таблицы по столбцу DATA
                    $annotator->identifyTableType($data, $ner_data);
                    // Если установлена стратегия аннотирования литеральных значений
                    if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::LITERAL_STRATEGY) {
                        // Аннотирование столбца "DATA"
                        $ranked_data_candidate_entities = $annotator->annotateTableLiteralData($data,
                            $ner_data, $data_path);
                        // Аннотирование столбца "RowHeading"
                        $ranked_row_heading_candidate_entities = $annotator->annotateTableHeading($data,
                            CanonicalTableAnnotator::ROW_HEADING_TITLE, $row_heading_path);
                        // Аннотирование столбца "ColumnHeading"
                        $ranked_column_heading_candidate_entities = $annotator->annotateTableHeading($data,
                            CanonicalTableAnnotator::COLUMN_HEADING_TITLE, $column_heading_path);
                        // Генерация RDF-документа в формате RDF/XML
                        //$annotator->generateRDFXMLCode();
                    }
                    // Если установлена стратегия аннотирования именованных сущностей
                    if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::NAMED_ENTITY_STRATEGY) {
                        // Аннотирование столбца "DATA"
                        $ranked_data_candidate_entities = $annotator->annotateTableEntityData($data, $ner_data);
                    }

                    // Открытие файла на запись для сохранения результатов аннотирования таблицы
                    $result_file = fopen(Yii::$app->basePath . '\web\results\\' .
                        $file_name_without_extension . '\results.log', 'a');
                    // Запись в файл логов результаты аннотирования таблицы
                    $json = json_encode($ranked_data_candidate_entities, JSON_PRETTY_PRINT);
                    $json = str_replace('\/', '/', $json);
                    fwrite($result_file, $json);
                    fwrite($result_file, PHP_EOL . '**************************************' . PHP_EOL);
                    $json = json_encode($ranked_row_heading_candidate_entities, JSON_PRETTY_PRINT);
                    $json = str_replace('\/', '/', $json);
                    fwrite($result_file, $json);
                    fwrite($result_file, PHP_EOL . '**************************************' . PHP_EOL);
                    $json = json_encode($ranked_column_heading_candidate_entities, JSON_PRETTY_PRINT);
                    $json = str_replace('\/', '/', $json);
                    fwrite($result_file, $json);
                    // Закрытие файла
                    fclose($result_file);

                    // Определение общего кол-ва и кол-ва аннотированных ячеек канонической таблицы
                    $total_number = 0;
                    $annotated_entity_number = 0;
                    foreach ($data as $item)
                        foreach ($item as $heading => $value) {
                            if ($heading == CanonicalTableAnnotator::DATA_TITLE) {
                                $total_number++;
                                foreach ($ranked_data_candidate_entities as $entry => $entity)
                                    if ($value == $entry && !empty($entity))
                                        $annotated_entity_number++;
                            }
                            if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE) {
                                $string_array = explode(" | ", $value);
                                foreach ($string_array as $key => $string) {
                                    $total_number++;
                                    foreach ($ranked_row_heading_candidate_entities as $entry => $entity)
                                        if ($string == $entry && !empty($entity))
                                            $annotated_entity_number++;
                                }
                            }
                            if ($heading == CanonicalTableAnnotator::COLUMN_HEADING_TITLE) {
                                $string_array = explode(" | ", $value);
                                foreach ($string_array as $key => $string) {
                                    $total_number++;
                                    foreach ($ranked_column_heading_candidate_entities as $entry => $entity)
                                        if ($string == $entry && !empty($entity))
                                            $annotated_entity_number++;
                                }
                            }
                        }
                    // Высисление оценки полноты
                    $recall = $annotated_entity_number / $total_number;
                    // Открытие файла на запись для сохранения оценки полноты
                    $runtime_file = fopen(Yii::$app->basePath . '\web\results\runtime.log', 'a');
                    // Запись в файл логов оценки полноты
                    fwrite($runtime_file, 'Полнота (recall) для ' . $file_name_without_extension . ': ' .
                        $recall . PHP_EOL);
                    // Закрытие файла
                    fclose($runtime_file);
                }
            }
            // Закрытие каталога набора данных
            closedir($handle);
        }
        // Открытие файла на запись для сохранения времени выполнения скрипта
        $runtime_file = fopen(Yii::$app->basePath . '\web\results\runtime.log', 'a');
        // Сохранение времени выполнения скрипта
        $runtime = round(microtime(true) - $start, 4);
        // Запись в файл логов времени выполнения скрипта
        fwrite($runtime_file, 'Время выполнения скрипта: ' . $runtime . ' сек.' . PHP_EOL);
        fwrite($runtime_file, '**********************************************************' . PHP_EOL);
        fwrite($runtime_file, 'Время выполнения скрипта: ' . ($runtime / 60) . ' мин.' . PHP_EOL);
        fwrite($runtime_file, '**********************************************************' . PHP_EOL);
        fwrite($runtime_file, 'Время выполнения скрипта: ' . (($runtime / 60) / 60) . ' час.' . PHP_EOL);
        // Закрытие файла
        fclose($runtime_file);
    }
}