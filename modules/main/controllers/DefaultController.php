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
        $property_query_results = array();
        $class_query_time = 0;
        $property_query_time = 0;
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
                // Обход XSLX-данных
                foreach ($data as $key => $item)
                    foreach ($item as $heading => $value)
                        if ($heading == 'ColumnHeading') {
                            $string_array = explode(" | ", $value);
                            foreach ($string_array as $string) {
                                // Формирование правильного значения для поиска класса
                                $str = ucwords(strtolower($string));
                                $correct_string = str_replace(' ', '', $str);
                                // Подключение к DBpedia
                                $endpoint = "http://dbpedia.org/sparql";
                                $sparql_client = new SparqlClient();
                                $sparql_client->setEndpointRead($endpoint);
                                $error = $sparql_client->getErrors();
                                if (!$error) {
                                    // SPARQL-запрос к DBpedia для поиска класса
                                    $query = "
                                        PREFIX dbpo: <http://dbpedia.org/ontology/>
                                        SELECT dbpo:$correct_string ?property ?concept
                                        WHERE { dbpo:$correct_string ?property ?concept }
                                        LIMIT 1
                                    ";
                                    $rows = $sparql_client->query($query, 'rows');
                                    $class_query_time += $rows["query_time"];
                                    array_push($class_query_results, $rows);
                                    if ($rows["result"]["rows"])
                                        $data[$key][$heading] = $value . ' (dbpo:' . $correct_string . ')';
                                    //
                                    if (!$rows["result"]["rows"]) {
                                        // Формирование правильного значения для поиска свойств класса
                                        $str = lcfirst(ucwords(strtolower($string)));
                                        $correct_string = str_replace(' ', '', $str);
                                        // SPARQL-запрос к DBpedia для поиска свойства класса
                                        $query = "
                                            PREFIX dbpo: <http://dbpedia.org/ontology/>
                                            SELECT ?class dbpo:$correct_string ?concept
                                            WHERE { ?class dbpo:$correct_string ?concept }
                                            LIMIT 1
                                        ";
                                        $rows = $sparql_client->query($query, 'rows');
                                        $property_query_time += $rows["query_time"];
                                        array_push($property_query_results, $rows);
                                        if ($rows["result"]["rows"])
                                            $data[$key][$heading] = $value . ' (dbpo:' . $correct_string . ')';
                                    }
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
            'class_query_time'=>$class_query_time,
            'property_query_time'=>$property_query_time
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