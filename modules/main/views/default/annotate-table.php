<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $file_form app\modules\main\models\ExcelFileForm */
/* @var $data app\modules\main\controllers\DefaultController */
/* @var $data_entities app\modules\main\controllers\DefaultController */
/* @var $row_heading_entities app\modules\main\controllers\DefaultController */
/* @var $column_heading_entities app\modules\main\controllers\DefaultController */
/* @var $data_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $row_heading_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $column_heading_concept_query_results app\modules\main\controllers\DefaultController */

use yii\helpers\Html;
use yii\bootstrap\Tabs;
use yii\bootstrap\ActiveForm;

$this->title = Yii::t('app', 'TABLE_ANNOTATION_PAGE_TITLE');
$this->params['breadcrumbs'][] = $this->title;
?>

<!-- Подключение скрипта для модальных форм -->
<?php $this->registerJsFile('/js/modal-form.js', ['position' => yii\web\View::POS_HEAD]) ?>

<script type="text/javascript">
    // Массивы сущностей для аннотированных значений ячеек в таблице
    var data_entities = <?php echo json_encode($data_entities); ?>;
    var row_heading_entities = <?php echo json_encode($row_heading_entities); ?>;
    var column_heading_entities = <?php echo json_encode($column_heading_entities); ?>;
    // Переменные для сохранения текущих выбранных сущностей в ячейках таблицы
    var current_selected_data_entity;
    var current_selected_row_heading_entity;
    var current_selected_column_heading_entity;
</script>

<div class="main-default-annotate-table">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if(!$data): ?>
        <div class="row">
            <div class="col-lg-5">
                <?php $form = ActiveForm::begin([
                    'id'=>'import-excel-file-form',
                    'options' => ['enctype' => 'multipart/form-data']
                ]); ?>

                    <?= $form->errorSummary($file_form); ?>

                    <?= $form->field($file_form, 'excel_file')->fileInput() ?>

                    <div class="form-group">
                        <?= Html::submitButton('<span class="glyphicon glyphicon-import"></span> ' .
                            Yii::t('app', 'BUTTON_IMPORT'),
                            ['class' => 'btn btn-success', 'name'=>'import-excel-file-button']) ?>
                    </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if($data): ?>
        <div class="row">
            <?php echo Tabs::widget([
                'items' => [
                    [
                        'label' => Yii::t('app', 'TABLE_ANNOTATION_PAGE_RESULTING_TABLE'),
                        'content' => $this->render('_resulting_table', ['data' => $data]),
                        'active' => true
                    ],
                    [
                        'label' => Yii::t('app', 'TABLE_ANNOTATION_PAGE_DATA_RESULTS'),
                        'content' => $this->render('_data_results', [
                            'data_concept_query_results' => $data_concept_query_results,
                        ]),
                    ],
                    [
                        'label' => Yii::t('app', 'TABLE_ANNOTATION_PAGE_ROW_HEADING_RESULTS'),
                        'content' => $this->render('_row_heading_results', [
                            'row_heading_concept_query_results' => $row_heading_concept_query_results,
                        ]),
                    ],
                    [
                        'label' => Yii::t('app', 'TABLE_ANNOTATION_PAGE_COLUMN_HEADING_RESULTS'),
                        'content' => $this->render('_column_heading_results', [
                            'column_heading_concept_query_results' => $column_heading_concept_query_results,
                        ]),
                    ]
                ]
            ]); ?>
        </div>
    <?php endif; ?>
</div>