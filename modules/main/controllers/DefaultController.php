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
                if ($annotator->annotation_strategy_type == CanonicalTableAnnotator::LITERAL_STRATEGY) {
                    // Аннотирование столбца "DATA"
                    $data_concept_query_results = $annotator->annotateTableLiteralData($data, $ner_data);
                    // Аннотирование столбца "RowHeading"
                    $row_heading_class_query_results = $annotator->annotateTableHeading(
                        $data, CanonicalTableAnnotator::ROW_HEADING_TITLE);
                    // Аннотирование столбца "ColumnHeading"
                    $column_heading_class_query_results = $annotator->annotateTableHeading(
                        $data, CanonicalTableAnnotator::COLUMN_HEADING_TITLE);
                }
                // Если установлена стратегия аннотирования именованных сущностей
                if ($annotator->annotation_strategy_type ==
                    CanonicalTableAnnotator::NAMED_ENTITY_STRATEGY) {
                    // Аннотирование столбца "DATA"
                    $data_concept_query_results = $annotator->annotateTableEntityData($data);
                }

//                // Аннотирование столбца "RowHeading"
//                list($row_heading_class_query_results, $row_heading_concept_query_results,
//                    $row_heading_property_query_results) = $annotator
//                    ->annotateTableHeading($data, CanonicalTableAnnotator::ROW_HEADING_TITLE);
//                // Аннотирование столбца "ColumnHeading"
//                list($column_heading_class_query_results, $column_heading_concept_query_results,
//                    $column_heading_property_query_results) = $annotator
//                        ->annotateTableHeading($data, CanonicalTableAnnotator::COLUMN_HEADING_TITLE);
                // Формирование массивов сущностей для аннотированных значений ячеек в таблице
                $data_entities = $annotator->data_entities;
                $row_heading_entities = $annotator->row_heading_entities;
                $column_heading_entities = $annotator->column_heading_entities;
//                // Формирование массивов кандидатов родительских классов для аннотированных сущностей
//                $parent_data_class_candidates = $annotator->parent_data_class_candidates;
//                $parent_row_heading_class_candidates = $annotator->parent_row_heading_class_candidates;
//                $parent_column_heading_class_candidates = $annotator->parent_column_heading_class_candidates;
//                // Формирование массивов определенных родительских классов для аннотированных сущностей
//                $parent_data_classes = $annotator->parent_data_classes;
//                $parent_row_heading_classes = $annotator->parent_row_heading_classes;
//                $parent_column_heading_classes = $annotator->parent_column_heading_classes;
//                // Формирование итогового времени затраченного на поиск сущностей в DBpedia
//                $all_data_concept_query_runtime = 0;
//                $all_row_heading_class_query_runtime = 0;
//                $all_row_heading_concept_query_runtime = 0;
//                $all_row_heading_property_query_runtime = 0;
//                $all_column_heading_class_query_runtime = 0;
//                $all_column_heading_concept_query_runtime = 0;
//                $all_column_heading_property_query_runtime = 0;
//                foreach ($data_concept_query_results as $concept_query_result)
//                    $all_data_concept_query_runtime += $concept_query_result['query_time'];
//                foreach ($row_heading_class_query_results as $class_query_result)
//                    $all_row_heading_class_query_runtime += $class_query_result['query_time'];
//                foreach ($row_heading_concept_query_results as $concept_query_result)
//                    $all_row_heading_concept_query_runtime += $concept_query_result['query_time'];
//                foreach ($row_heading_property_query_results as $property_query_result)
//                    $all_row_heading_property_query_runtime += $property_query_result['query_time'];
//                foreach ($column_heading_class_query_results as $class_query_result)
//                    $all_column_heading_class_query_runtime += $class_query_result['query_time'];
//                foreach ($column_heading_concept_query_results as $concept_query_result)
//                    $all_column_heading_concept_query_runtime += $concept_query_result['query_time'];
//                foreach ($column_heading_property_query_results as $property_query_result)
//                    $all_column_heading_property_query_runtime += $property_query_result['query_time'];

                // Check if the Session is Open, and Open it if it isn't Open already
                if (!Yii::$app->session->getIsActive()) {
                    Yii::$app->session->open();
                }
                Yii::$app->session['data'] = $data;
                Yii::$app->session['data_concept_query_results'] = $data_concept_query_results;
//                Yii::$app->session['row_heading_class_query_results'] = $row_heading_class_query_results;
//                Yii::$app->session['row_heading_property_query_results'] = $row_heading_property_query_results;
//                Yii::$app->session['row_heading_concept_query_results'] = $row_heading_concept_query_results;
//                Yii::$app->session['column_heading_class_query_results'] = $column_heading_class_query_results;
//                Yii::$app->session['column_heading_concept_query_results'] = $column_heading_concept_query_results;
//                Yii::$app->session['column_heading_property_query_results'] = $column_heading_property_query_results;
//                Yii::$app->session['all_data_concept_query_runtime'] = $all_data_concept_query_runtime;
//                Yii::$app->session['all_row_heading_class_query_runtime'] = $all_row_heading_class_query_runtime;
//                Yii::$app->session['all_row_heading_concept_query_runtime'] = $all_row_heading_concept_query_runtime;
//                Yii::$app->session['all_row_heading_property_query_runtime'] = $all_row_heading_property_query_runtime;
//                Yii::$app->session['all_column_heading_class_query_runtime'] = $all_column_heading_class_query_runtime;
//                Yii::$app->session['all_column_heading_concept_query_runtime'] =
//                    $all_column_heading_concept_query_runtime;
//                Yii::$app->session['all_column_heading_property_query_runtime'] =
//                    $all_column_heading_property_query_runtime;
                Yii::$app->session['data_entities'] = $data_entities;
                Yii::$app->session['row_heading_entities'] = $row_heading_entities;
                Yii::$app->session['column_heading_entities'] = $column_heading_entities;
//                Yii::$app->session['parent_data_class_candidates'] = $parent_data_class_candidates;
//                Yii::$app->session['parent_row_heading_class_candidates'] = $parent_row_heading_class_candidates;
//                Yii::$app->session['parent_column_heading_class_candidates'] = $parent_column_heading_class_candidates;
//                Yii::$app->session['parent_data_classes'] = $parent_data_classes;
//                Yii::$app->session['parent_row_heading_classes'] = $parent_row_heading_classes;
//                Yii::$app->session['parent_column_heading_classes'] = $parent_column_heading_classes;
                Yii::$app->session->close();

                return $this->redirect(['resulting-table']);
            }
        }

        return $this->render('annotate-table', [
            'file_form' => $file_form
        ]);
    }

    /**
     *
     * @return string
     */
    public function actionResultingTable()
    {
        if (isset(Yii::$app->session['data'])) {
            $data = Yii::$app->session['data'];
            $data_concept_query_results = Yii::$app->session['data_concept_query_results'];
            $row_heading_class_query_results = Yii::$app->session['row_heading_class_query_results'];
            $row_heading_property_query_results = Yii::$app->session['row_heading_property_query_results'];
            $row_heading_concept_query_results = Yii::$app->session['row_heading_concept_query_results'];
            $column_heading_class_query_results = Yii::$app->session['column_heading_class_query_results'];
            $column_heading_concept_query_results = Yii::$app->session['column_heading_concept_query_results'];
            $column_heading_property_query_results = Yii::$app->session['column_heading_property_query_results'];
            $all_data_concept_query_runtime = Yii::$app->session['all_data_concept_query_runtime'];
            $all_row_heading_class_query_runtime = Yii::$app->session['all_row_heading_class_query_runtime'];
            $all_row_heading_concept_query_runtime = Yii::$app->session['all_row_heading_concept_query_runtime'];
            $all_row_heading_property_query_runtime = Yii::$app->session['all_row_heading_property_query_runtime'];
            $all_column_heading_class_query_runtime = Yii::$app->session['all_column_heading_class_query_runtime'];
            $all_column_heading_concept_query_runtime = Yii::$app->session['all_column_heading_concept_query_runtime'];
            $all_column_heading_property_query_runtime = Yii::$app->session['all_column_heading_property_query_runtime'];
            $data_entities = Yii::$app->session['data_entities'];
            $row_heading_entities = Yii::$app->session['row_heading_entities'];
            $column_heading_entities = Yii::$app->session['column_heading_entities'];
            $parent_data_class_candidates = Yii::$app->session['parent_data_class_candidates'];
            $parent_row_heading_class_candidates = Yii::$app->session['parent_row_heading_class_candidates'];
            $parent_column_heading_class_candidates = Yii::$app->session['parent_column_heading_class_candidates'];
            $parent_data_classes = Yii::$app->session['parent_data_classes'];
            $parent_row_heading_classes = Yii::$app->session['parent_row_heading_classes'];
            $parent_column_heading_classes = Yii::$app->session['parent_column_heading_classes'];
            // Вывод сообщения об успешном аннотировании таблицы
            Yii::$app->getSession()->setFlash('success', Yii::t('app', 'TABLE_ANNOTATION_MESSAGE_ANNOTATE_TABLE'));
        } else {
            $data = null;
            $data_concept_query_results = null;
            $row_heading_class_query_results = null;
            $row_heading_property_query_results = null;
            $row_heading_concept_query_results = null;
            $column_heading_class_query_results = null;
            $column_heading_concept_query_results = null;
            $column_heading_property_query_results = null;
            $all_data_concept_query_runtime = null;
            $all_row_heading_class_query_runtime = null;
            $all_row_heading_concept_query_runtime = null;
            $all_row_heading_property_query_runtime = null;
            $all_column_heading_class_query_runtime = null;
            $all_column_heading_concept_query_runtime = null;
            $all_column_heading_property_query_runtime = null;
            $data_entities = null;
            $row_heading_entities = null;
            $column_heading_entities = null;
            $parent_data_class_candidates = null;
            $parent_row_heading_class_candidates = null;
            $parent_column_heading_class_candidates = null;
            $parent_data_classes = null;
            $parent_row_heading_classes = null;
            $parent_column_heading_classes = null;
        }

        return $this->render('resulting-table', [
            'data' => $data,
            'data_concept_query_results' => $data_concept_query_results,
            'row_heading_class_query_results' => $row_heading_class_query_results,
            'row_heading_property_query_results' => $row_heading_property_query_results,
            'row_heading_concept_query_results' => $row_heading_concept_query_results,
            'column_heading_class_query_results' => $column_heading_class_query_results,
            'column_heading_concept_query_results' => $column_heading_concept_query_results,
            'column_heading_property_query_results' => $column_heading_property_query_results,
            'all_data_concept_query_runtime' => $all_data_concept_query_runtime,
            'all_row_heading_class_query_runtime' => $all_row_heading_class_query_runtime,
            'all_row_heading_concept_query_runtime' => $all_row_heading_concept_query_runtime,
            'all_row_heading_property_query_runtime' => $all_row_heading_property_query_runtime,
            'all_column_heading_class_query_runtime' => $all_column_heading_class_query_runtime,
            'all_column_heading_concept_query_runtime' => $all_column_heading_concept_query_runtime,
            'all_column_heading_property_query_runtime' => $all_column_heading_property_query_runtime,
            'data_entities' => $data_entities,
            'row_heading_entities' => $row_heading_entities,
            'column_heading_entities' => $column_heading_entities,
            'parent_data_class_candidates' => $parent_data_class_candidates,
            'parent_row_heading_class_candidates' => $parent_row_heading_class_candidates,
            'parent_column_heading_class_candidates' => $parent_column_heading_class_candidates,
            'parent_data_classes' => $parent_data_classes,
            'parent_row_heading_classes' => $parent_row_heading_classes,
            'parent_column_heading_classes' => $parent_column_heading_classes
        ]);
    }
}