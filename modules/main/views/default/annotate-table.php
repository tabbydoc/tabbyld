<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $file_form app\modules\main\models\XLSXFileForm */
/* @var $data app\modules\main\controllers\DefaultController */
/* @var $class_query_results app\modules\main\controllers\DefaultController */
/* @var $property_query_results app\modules\main\controllers\DefaultController */
/* @var $class_query_time app\modules\main\controllers\DefaultController */
/* @var $property_query_time app\modules\main\controllers\DefaultController */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

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
                <td><b>DATA</b></td>
                <td><b>RowHeading1</b></td>
                <td><b>ColumnHeading</b></td>
            </tr>
            <?php
            foreach($data as $item) {
                echo "<tr>";
                foreach ($item as $key => $value)
                    echo "<td>" . json_encode($value) . "</td>";
                echo "</tr>";
            }
            ?>
        </table>

        <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_CLASS_QUERY_RESULTS') . ' (' . $class_query_time . ')' ?></h2>

        <table class="table table-striped table-bordered">
            <tr>
                <?php
                    foreach ($class_query_results[0]["result"]["variables"] as $variable) {
                        echo '<td><b>';
                        printf("%-20.20s", $variable);
                        echo '</b></td>';
                    }
                ?>
            </tr>
            <?php
                foreach ($class_query_results as $class_query_result)
                    foreach ($class_query_result["result"]["rows"] as $row) {
                        echo '<tr>';
                        foreach ($class_query_result["result"]["variables"] as $variable)
                            echo '<td>' . $row[$variable] . '</td>';
                        echo '</tr>';
                    }
            ?>
        </table>

        <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_PROPERTY_QUERY_RESULTS') . ' (' . $property_query_time . ')' ?></h2>

        <table class="table table-striped table-bordered">
            <tr>
                <?php
                    foreach ($property_query_results[0]["result"]["variables"] as $variable) {
                        echo '<td><b>';
                        printf("%-20.20s", $variable);
                        echo '</b></td>';
                    }
                ?>
            </tr>
            <?php
                foreach ($property_query_results as $property_query_result)
                    foreach ($property_query_result["result"]["rows"] as $row) {
                        echo '<tr>';
                        foreach ($property_query_result["result"]["variables"] as $variable)
                            echo '<td>' . $row[$variable] . '</td>';
                        echo '</tr>';
                    }
            ?>
        </table>

    <?php endif; ?>
</div>