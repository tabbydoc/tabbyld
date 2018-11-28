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
                //
                $annotator = new CanonicalTableAnnotator();
                //
                list($class_query_results, $concept_query_results, $property_query_results,
                    $all_class_query_runtime, $all_concept_query_runtime, $all_property_query_runtime
                    ) = $annotator->annotateTableHeading($data, CanonicalTableAnnotator::COLUMN_HEADING_TITLE);
                // Обход XSLX-данных
                foreach ($data as $key => $item)
                    foreach ($item as $heading => $value) {
                        $string_array = explode(" | ", $value);
                        foreach ($string_array as $str_key => $string) {
                            // Обработка столбца ColumnHeading
                            if ($heading == 'ColumnHeading')
                                foreach ($annotator->column_heading_concepts as $chc_key => $formed_entity)
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