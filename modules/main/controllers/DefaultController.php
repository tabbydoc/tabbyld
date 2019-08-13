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
use app\modules\main\models\ExcelFileForm;
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
     * Аннотирование канонических таблицы.
     *
     * @return string
     */
    public function actionAnnotateTable()
    {
        // Создание формы файла Excel
        $file_form = new ExcelFileForm();
        // Массив для данных канонической таблицы
        $data = array();
        // Массивы сущностей, связанных с ячейками таблицы
        $data_entities = array();
        $row_heading_entities = array();
        $column_heading_entities = array();
        // Массивы результатов поиска сущностей-кандидатов для ячеек таблицы
        $data_concept_query_results = array();
        $row_heading_concept_query_results = array();
        $column_heading_concept_query_results = array();
        // Если POST-запрос
        if (Yii::$app->request->isPost) {
            $file_form->excel_file = UploadedFile::getInstance($file_form, 'excel_file');
            if ($file_form->validate()) {
                // Получение данных из файла XSLX
                $data = Excel::import($file_form->excel_file->tempName, [
                    'setFirstRecordAsKeys' => true,
                    'setIndexSheetByName' => true,
                    'getOnlySheet' => ExcelFileForm::CANONICAL_TABLE_SHEET,
                ]);
                // Получение данных о метках NER из файла XSLX
                $ner_data = Excel::import($file_form->excel_file->tempName, [
                    'setFirstRecordAsKeys' => true,
                    'setIndexSheetByName' => true,
                    'getOnlySheet' => ExcelFileForm::NER_SHEET,
                ]);
                // Создание объекта аннотатора таблиц
                $annotator = new CanonicalTableAnnotator();
                // Идентификация типа таблицы по столбцу DATA
                $annotator->identifyTableType($data, $ner_data);
                // Если установлена стратегия аннотирования литеральных значений
                if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::LITERAL_STRATEGY) {
                    // Аннотирование столбца "DATA"
                    $data_concept_query_results = $annotator->annotateTableLiteralData($data, $ner_data);
                    // Аннотирование столбца "RowHeading"
                    $row_heading_concept_query_results = $annotator->annotateTableHeading(
                        $data, CanonicalTableAnnotator::ROW_HEADING_TITLE);
                    // Аннотирование столбца "ColumnHeading"
                    $column_heading_concept_query_results = $annotator->annotateTableHeading(
                        $data, CanonicalTableAnnotator::COLUMN_HEADING_TITLE);
                    // Генерация RDF-документа в формате RDF/XML
                    //$annotator->generateRDFXMLCode();
                }
                // Если установлена стратегия аннотирования именованных сущностей
                if ($annotator->current_annotation_strategy_type == CanonicalTableAnnotator::NAMED_ENTITY_STRATEGY) {
                    // Аннотирование столбца "DATA"
                    $data_concept_query_results = $annotator->annotateTableEntityData($data, $ner_data);
                }
                // Запоминание аннотированных сущностей
                $data_entities = $annotator->data_entities;
                $row_heading_entities = $annotator->row_heading_entities;
                $column_heading_entities = $annotator->column_heading_entities;
            }
        }

        return $this->render('annotate-table', [
            'file_form' => $file_form,
            'data' => $data,
            'data_entities' => $data_entities,
            'row_heading_entities' => $row_heading_entities,
            'column_heading_entities' => $column_heading_entities,
            'data_concept_query_results' => $data_concept_query_results,
            'row_heading_concept_query_results' => $row_heading_concept_query_results,
            'column_heading_concept_query_results' => $column_heading_concept_query_results,
        ]);
    }

    /**
     * Экспорт RDF-документа c результатами аннотирования таблицы.
     *
     * @return bool
     */
    public function actionExportRdf()
    {
        $file = 'example.rdf';
        header('Content-Disposition: attachment;filename=' . $file);
        header('Content-Type: text/xml');
        readfile($file);

        return false;
    }
}