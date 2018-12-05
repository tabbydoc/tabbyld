<?php

/* @var $data app\modules\main\controllers\DefaultController */

use app\components\CanonicalTableAnnotator;

?>

<h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_RESULTING_TABLE') ?></h2>

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