<?php

/* @var $parent_data_classes app\modules\main\controllers\DefaultController */
/* @var $parent_row_heading_classes app\modules\main\controllers\DefaultController */
/* @var $parent_column_heading_classes app\modules\main\controllers\DefaultController */

use yii\helpers\Html;
use yii\bootstrap\Modal;
use yii\bootstrap\Button;
use yii\widgets\ActiveForm;

?>

<?php Modal::begin([
    'id' => 'selectParentClassModalForm',
    'header' => '<h3>' . Yii::t('app', 'TABLE_ANNOTATION_PAGE_SELECT_PARENT_CLASS') . '</h3>',
]); ?>

    <div class="modal-body">
        <p class="modal-form-text"><?php echo Yii::t('app', 'TABLE_ANNOTATION_PAGE_MODAL_FORM_TEXT'); ?></p>
    </div>

    <?php $form = ActiveForm::begin([
        'id' => 'select-parent-class-form',
        'enableAjaxValidation'=>true,
        'enableClientValidation'=>true,
    ]); ?>

        <br/>
        <?= Html::Button(Yii::t('app', 'BUTTON_SAVE'), ['class' => 'btn btn-success save-parent-class-button']) ?>

        <?= Button::widget([
            'label' => Yii::t('app', 'BUTTON_CANCEL'),
            'options' => [
                'class' => 'btn-danger',
                'style' => 'margin:5px',
                'data-dismiss'=>'modal'
            ]
        ]); ?>

    <?php ActiveForm::end(); ?>

<?php Modal::end(); ?>