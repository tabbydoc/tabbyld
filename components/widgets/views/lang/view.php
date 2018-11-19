<?php

/* @var $current app\components\widgets\WLang */
/* @var $langs app\components\widgets\WLang */

use yii\helpers\Html;
use yii\bootstrap\ButtonDropdown;

?>
<div id="lang">
    <?php foreach ($langs as $lang):?>
        <?= ButtonDropdown::widget([
            'label' => $current->url == 'ru' ? "<figure class='icon-lang icon-ru'></figure>" :
                "<figure class='icon-lang icon-en'></figure>",
            'options' => [
                'class' => 'btn-default',
            ],
            'encodeLabel' => false,
            'dropdown' => [
                'encodeLabels' => false,
                'items' => [
                    [
                        'label' => $lang->url == 'ru' ? "<figure class='icon-lang icon-ru'></figure>" . $lang->name :
                            "<figure class='icon-lang icon-en'></figure>" . $lang->name,
                        'url' => '/' . $lang->url . Yii::$app->getRequest()->getLangUrl()
                    ],
                ]
            ]
        ]); ?>
    <?php endforeach;?>
</div>