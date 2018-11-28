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
use app\components\CanonicalTableAnnotator;

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
        $row_heading_class_query_results = array();
        $row_heading_concept_query_results = array();
        $row_heading_property_query_results = array();
        $all_row_heading_class_query_runtime = 0;
        $all_row_heading_concept_query_runtime = 0;
        $all_row_heading_property_query_runtime= 0;
        $column_heading_class_query_results = array();
        $column_heading_concept_query_results = array();
        $column_heading_property_query_results = array();
        $all_column_heading_class_query_runtime = 0;
        $all_column_heading_concept_query_runtime = 0;
        $all_column_heading_property_query_runtime= 0;
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
                // Создание объекта аннотатора таблиц
                $annotator = new CanonicalTableAnnotator();
                // Аннотирование столбца "RowHeading"
                list($row_heading_class_query_results, $row_heading_concept_query_results,
                    $row_heading_property_query_results) = $annotator
                    ->annotateTableHeading($data, CanonicalTableAnnotator::ROW_HEADING_TITLE);
                // Аннотирование столбца "ColumnHeading"
                list($column_heading_class_query_results, $column_heading_concept_query_results,
                    $column_heading_property_query_results) = $annotator
                        ->annotateTableHeading($data, CanonicalTableAnnotator::COLUMN_HEADING_TITLE);
                // Обход XSLX-данных
                foreach ($data as $key => $item)
                    foreach ($item as $heading => $value) {
                        $string_array = explode(" | ", $value);
                        foreach ($string_array as $str_key => $string) {
                            // Обработка столбца "RowHeading"
                            if ($heading == CanonicalTableAnnotator::ROW_HEADING_TITLE)
                                foreach ($annotator->row_heading_entities as $chc_key => $formed_entity)
                                    if ($string == $chc_key) {
                                        if ($str_key > 0)
                                            $data[$key][$heading] .= $formed_entity;
                                        if ($str_key == 0 && count($string_array) == 1)
                                            $data[$key][$heading] = $formed_entity;
                                        if ($str_key == 0 && count($string_array) > 1)
                                            $data[$key][$heading] = $formed_entity . ' | ';
                                    }
                            // Обработка столбца "ColumnHeading"
                            if ($heading == CanonicalTableAnnotator::COLUMN_HEADING_TITLE)
                                foreach ($annotator->column_heading_entities as $chc_key => $formed_entity)
                                    if ($string == $chc_key) {
                                        if ($str_key > 0)
                                            $data[$key][$heading] .= $formed_entity;
                                        if ($str_key == 0 && count($string_array) == 1)
                                            $data[$key][$heading] = $formed_entity;
                                        if ($str_key == 0 && count($string_array) > 1)
                                            $data[$key][$heading] = $formed_entity . ' | ';
                                    }
                        }
                    }
                // Формирование итогового времени затраченного на поиск сущностей в DBpedia
                foreach ($row_heading_class_query_results as $class_query_result)
                    $all_row_heading_class_query_runtime += $class_query_result['query_time'];
                foreach ($row_heading_concept_query_results as $concept_query_result)
                    $all_row_heading_concept_query_runtime += $concept_query_result['query_time'];
                foreach ($row_heading_property_query_results as $property_query_result)
                    $all_row_heading_property_query_runtime += $property_query_result['query_time'];
                foreach ($column_heading_class_query_results as $class_query_result)
                    $all_column_heading_class_query_runtime += $class_query_result['query_time'];
                foreach ($column_heading_concept_query_results as $concept_query_result)
                    $all_column_heading_concept_query_runtime += $concept_query_result['query_time'];
                foreach ($column_heading_property_query_results as $property_query_result)
                    $all_column_heading_property_query_runtime += $property_query_result['query_time'];
            }
        }

        return $this->render('annotate-table', [
            'file_form'=>$file_form,
            'data'=>$data,
            'row_heading_class_query_results'=>$row_heading_class_query_results,
            'row_heading_property_query_results'=>$row_heading_property_query_results,
            'row_heading_concept_query_results'=>$row_heading_concept_query_results,
            'column_heading_class_query_results'=>$column_heading_class_query_results,
            'column_heading_concept_query_results'=>$column_heading_concept_query_results,
            'column_heading_property_query_results'=>$column_heading_property_query_results,
            'all_row_heading_class_query_runtime'=>$all_row_heading_class_query_runtime,
            'all_row_heading_concept_query_runtime'=>$all_row_heading_concept_query_runtime,
            'all_row_heading_property_query_runtime'=>$all_row_heading_property_query_runtime,
            'all_column_heading_class_query_runtime'=>$all_column_heading_class_query_runtime,
            'all_column_heading_concept_query_runtime'=>$all_column_heading_concept_query_runtime,
            'all_column_heading_property_query_runtime'=>$all_column_heading_property_query_runtime
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