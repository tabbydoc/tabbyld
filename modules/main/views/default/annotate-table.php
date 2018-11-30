<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $file_form app\modules\main\models\XLSXFileForm */
/* @var $data app\modules\main\controllers\DefaultController */
/* @var $data_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $row_heading_class_query_results app\modules\main\controllers\DefaultController */
/* @var $row_heading_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $row_heading_property_query_results app\modules\main\controllers\DefaultController */
/* @var $column_heading_class_query_results app\modules\main\controllers\DefaultController */
/* @var $column_heading_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $column_heading_property_query_results app\modules\main\controllers\DefaultController */
/* @var $all_row_heading_class_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_row_heading_concept_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_roe_heading_property_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_column_heading_class_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_column_heading_concept_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_column_heading_property_query_runtime app\modules\main\controllers\DefaultController */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use app\components\CanonicalTableAnnotator;

$this->title = Yii::t('app', 'TABLE_ANNOTATION_PAGE_TITLE');
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="main-default-annotate-table">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-lg-5">

            <?php $form = ActiveForm::begin([
                'id'=>'import-xlsx-file-form',
                'options' => ['enctype' => 'multipart/form-data']
            ]); ?>

            <?= $form->errorSummary($file_form); ?>

            <?= $form->field($file_form, 'xlsx_file')->fileInput() ?>

            <div class="form-group">
                <?= Html::submitButton(Yii::t('app', 'BUTTON_IMPORT'),
                    ['class' => 'btn btn-success', 'name'=>'import-xlsx-file-button']) ?>
            </div>

            <?php ActiveForm::end(); ?>

        </div>
    </div>

    <?php if($data): ?>

        <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_TEXT') ?></h2>

        <table class="table table-striped table-bordered">
            <tr>
                <td><b><?= CanonicalTableAnnotator::DATA_TITLE; ?></b></td>
                <td><b><?= CanonicalTableAnnotator::ROW_HEADING_TITLE; ?></b></td>
                <td><b><?= CanonicalTableAnnotator::COLUMN_HEADING_TITLE; ?></b></td>
            </tr>
            <?php foreach($data as $item): ?>
                <tr>
                    <?php foreach($item as $value): ?>
                        <td><?php echo $value; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if($data_concept_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_DATA_CONCEPT_QUERY_RESULTS') .
                ' (' . $all_data_concept_query_runtime . ')' ?></h2>
            <?= json_encode($data_concept_query_results); ?>
<!--            <table class="table table-striped table-bordered">-->
<!--                <tr>-->
<!--                    --><?php //foreach($data_concept_query_results[0]["result"]["variables"] as $variable): ?>
<!--                        <td><b>--><?php //printf("%-20.20s", $variable); ?><!--</b></td>-->
<!--                    --><?php //endforeach; ?>
<!--                </tr>-->
<!--                --><?php //foreach($data_concept_query_results as $concept_query_result): ?>
<!--                    --><?php //foreach($concept_query_result["result"]["rows"] as $row): ?>
<!--                        <tr>-->
<!--                            --><?php //foreach($concept_query_result["result"]["variables"] as $variable): ?>
<!--                                <td>--><?//= $row[$variable]; ?><!--</td>-->
<!--                            --><?php //endforeach; ?>
<!--                        </tr>-->
<!--                    --><?php //endforeach; ?>
<!--                --><?php //endforeach; ?>
<!--            </table>-->
        <?php endif; ?>

        <?php if($row_heading_class_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_ROW_HEADING_CLASS_QUERY_RESULTS') .
                ' (' . $all_row_heading_class_query_runtime . ')' ?></h2>
            <table class="table table-striped table-bordered">
                <tr>
                    <?php foreach($row_heading_class_query_results[0]["result"]["variables"] as $variable): ?>
                        <td><b><?php printf("%-20.20s", $variable); ?></b></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach($row_heading_class_query_results as $class_query_result): ?>
                    <?php foreach($class_query_result["result"]["rows"] as $row): ?>
                        <tr>
                            <?php foreach($class_query_result["result"]["variables"] as $variable): ?>
                                <td><?= $row[$variable]; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if($row_heading_concept_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_ROW_HEADING_CONCEPT_QUERY_RESULTS') .
                ' (' . $all_row_heading_concept_query_runtime . ')' ?></h2>
            <table class="table table-striped table-bordered">
                <tr>
                    <?php foreach($row_heading_concept_query_results[0]["result"]["variables"] as $variable): ?>
                        <td><b><?php printf("%-20.20s", $variable); ?></b></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach($row_heading_concept_query_results as $concept_query_result): ?>
                    <?php foreach($concept_query_result["result"]["rows"] as $row): ?>
                        <tr>
                            <?php foreach($concept_query_result["result"]["variables"] as $variable): ?>
                                <td><?= $row[$variable]; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if($row_heading_property_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_ROW_HEADING_PROPERTY_QUERY_RESULTS') .
                ' (' . $all_row_heading_property_query_runtime . ')' ?></h2>
            <table class="table table-striped table-bordered">
                <tr>
                    <?php foreach($row_heading_property_query_results[0]["result"]["variables"] as $variable): ?>
                        <td><b><?php printf("%-20.20s", $variable); ?></b></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach($row_heading_property_query_results as $property_query_result): ?>
                    <?php foreach($property_query_result["result"]["rows"] as $row): ?>
                        <tr>
                            <?php foreach($property_query_result["result"]["variables"] as $variable): ?>
                                <td><?= $row[$variable]; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if($column_heading_class_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_COLUMN_HEADING_CLASS_QUERY_RESULTS') .
                    ' (' . $all_column_heading_class_query_runtime . ')' ?></h2>
            <table class="table table-striped table-bordered">
                <tr>
                    <?php foreach($column_heading_class_query_results[0]["result"]["variables"] as $variable): ?>
                        <td><b><?php printf("%-20.20s", $variable); ?></b></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach($column_heading_class_query_results as $class_query_result): ?>
                    <?php foreach($class_query_result["result"]["rows"] as $row): ?>
                        <tr>
                            <?php foreach($class_query_result["result"]["variables"] as $variable): ?>
                                <td><?= $row[$variable]; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if($column_heading_concept_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_COLUMN_HEADING_CONCEPT_QUERY_RESULTS') .
                ' (' . $all_column_heading_concept_query_runtime . ')' ?></h2>
            <table class="table table-striped table-bordered">
                <tr>
                    <?php foreach($column_heading_concept_query_results[0]["result"]["variables"] as $variable): ?>
                        <td><b><?php printf("%-20.20s", $variable); ?></b></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach($column_heading_concept_query_results as $concept_query_result): ?>
                    <?php foreach($concept_query_result["result"]["rows"] as $row): ?>
                        <tr>
                            <?php foreach($concept_query_result["result"]["variables"] as $variable): ?>
                                <td><?= $row[$variable]; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if($column_heading_property_query_results): ?>
            <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_COLUMN_HEADING_PROPERTY_QUERY_RESULTS') .
                ' (' . $all_column_heading_property_query_runtime . ')' ?></h2>
            <table class="table table-striped table-bordered">
                <tr>
                    <?php foreach($column_heading_property_query_results[0]["result"]["variables"] as $variable): ?>
                        <td><b><?php printf("%-20.20s", $variable); ?></b></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach($column_heading_property_query_results as $property_query_result): ?>
                    <?php foreach($property_query_result["result"]["rows"] as $row): ?>
                        <tr>
                            <?php foreach($property_query_result["result"]["variables"] as $variable): ?>
                                <td><?= $row[$variable]; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    <?php endif; ?>
</div>