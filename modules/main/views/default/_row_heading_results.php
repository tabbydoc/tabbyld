<?php
/* @var $row_heading_concept_query_results app\modules\main\controllers\DefaultController */
?>

<?php if($row_heading_concept_query_results): ?>
    <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_ROW_HEADING_CONCEPT_QUERY_RESULTS') ?></h2>
    <?php foreach($row_heading_concept_query_results as $key => $row_heading_concept_query_result): ?>
        <table class="table table-striped table-bordered">
            <tr>
                <td><b><?= $key; ?></b></td>
                <td><b>Rang</b></td>
            </tr>
            <?php foreach($row_heading_concept_query_result as $value): ?>
                <tr>
                    <td><?= $value[0]; ?></td>
                    <td><?= $value[1]; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
<?php endif; ?>