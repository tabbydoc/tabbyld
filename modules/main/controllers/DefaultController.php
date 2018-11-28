<?php

namespace app\modules\main\controllers;

use Yii;
use yii\web\Response;
use yii\web\Controller;
use yii\web\UploadedFile;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\modules\main\models\LoginForm;
use app\modules\main\models\ContactForm;
use app\modules\main\models\XLSXFileForm;
use moonland\phpexcel\Excel;
use BorderCloud\SPARQL\SparqlClient;

/**
 * Default controller for the `main` module
 */
class DefaultController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionSingIn()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('sing-in', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($user = Yii::$app->user->identity) {
            $model->name = $user->username;
            //$model->email = $user->email;
        }
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');
            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Аннотирование таблицы.
     *
     * @return string
     */
    public function actionAnnotateTable()
    {
        $data = array();
        $class_query_results = array();
        $concept_query_results = array();
        $property_query_results = array();
        $all_class_query_runtime = 0;
        $all_concept_query_runtime = 0;
        $all_property_query_runtime = 0;

        // Создание формы файла XLSX
        $file_form = new XLSXFileForm();
        if (Yii::$app->request->isPost) {
            $file_form->xlsx_file = UploadedFile::getInstance($file_form, 'xlsx_file');
            if ($file_form->validate()) {
                // Получение данных из файла XSLX
                $data = Excel::import($file_form->xlsx_file->tempName, [
                    'setFirstRecordAsKeys' => true,
                    'setIndexSheetByName' => true,
                    'getOnlySheet' => 'CANONICAL TABLE',
                ]);

                $formed_concepts = array();
                $formed_properties = array();
                // Формирование массивов концептов (классов) и свойств
                foreach ($data as $item)
                    foreach ($item as $heading => $value)
                        if ($heading == 'ColumnHeading') {
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

                $formed_entities = array();
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

                // Обход XSLX-данных
                foreach ($data as $key => $item)
                    foreach ($item as $heading => $value)
                        // Обработка столбца ColumnHeading
                        if ($heading == 'ColumnHeading') {
                            $string_array = explode(" | ", $value);
                            // Аннотирование данных таблицы найденными сущностями
                            foreach ($string_array as $str_key => $string) {
                                foreach ($formed_entities as $fc_key => $formed_entity)
                                    if ($string == $fc_key) {
                                        if ($str_key > 0)
                                            $data[$key][$heading] .= $formed_entity;
                                        if ($str_key == 0 && count($string_array) == 1)
                                            $data[$key][$heading] = $formed_entity;
                                        if ($str_key == 0 && count($string_array) > 1)
                                            $data[$key][$heading] = $formed_entity . ' | ';
                                    }
                            }
                        }
            }
        }

        return $this->render('annotate-table', [
            'file_form'=>$file_form,
            'data'=>$data,
            'class_query_results'=>$class_query_results,
            'property_query_results'=>$property_query_results,
            'concept_query_results'=>$concept_query_results,
            'all_class_query_runtime'=>$all_class_query_runtime,
            'all_concept_query_runtime'=>$all_concept_query_runtime,
            'all_property_query_runtime'=>$all_property_query_runtime
        ]);
    }

    /**
     * Страница запроса SPARQL.
     *
     * @return string
     */
    public function actionSparqlQuery()
    {
        return $this->render('sparql-query');
    }
}