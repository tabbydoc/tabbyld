<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $file_form app\modules\main\models\ExcelFileForm */

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
</div>