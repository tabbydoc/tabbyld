<?php
/* @var $row_heading_concept_query_results app\modules\main\controllers\DefaultController */
?>

<?php if($row_heading_concept_query_results): ?>
    <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_ROW_HEADING_CONCEPT_QUERY_RESULTS') ?></h2>
    <table class="table table-striped table-bordered">
        <tr>
            <?php foreach($row_heading_concept_query_results[0]['result']['variables'] as $variable): ?>
                <td><b><?php printf('%-20.20s', $variable); ?></b></td>
            <?php endforeach; ?>
        </tr>
        <?php foreach($row_heading_concept_query_results as $concept_query_result): ?>
            <?php foreach($concept_query_result['result']['rows'] as $row): ?>
                <tr>
                    <?php foreach($concept_query_result['result']['variables'] as $variable): ?>
                        <td><?= $row[$variable]; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </table>
<?php endif; ?>