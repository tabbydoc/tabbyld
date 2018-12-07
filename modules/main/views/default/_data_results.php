<?php
/* @var $data_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $all_data_concept_query_runtime app\modules\main\controllers\DefaultController */
/* @var $parent_data_classes app\modules\main\controllers\DefaultController */
?>

<?php if($data_concept_query_results): ?>
    <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_DATA_CONCEPT_QUERY_RESULTS') .
        ' (' . round($all_data_concept_query_runtime, 3) . ')' ?></h2>
    <table class="table table-striped table-bordered">
        <tr>
            <?php foreach($data_concept_query_results[0]['result']['variables'] as $variable): ?>
                <td><b><?php printf('%-20.20s', $variable); ?></b></td>
            <?php endforeach; ?>
        </tr>
        <?php foreach($data_concept_query_results as $concept_query_result): ?>
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

<?php if($parent_data_classes): ?>
    <?php foreach($parent_data_classes as $parent_data_class): ?>
        <h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_PARENT_CLASSES_FOR_ENTITY'); ?>
            <a href="<?= $parent_data_class['result']['rows'][0]['callret-0']; ?>">
                <?= $parent_data_class['result']['rows'][0]['callret-0']; ?>
            </a>
        </h2>
        <table class="table table-striped table-bordered">
        <tr>
            <td><b>Concept</b></td>
            <td><b>Property</b></td>
            <td><b>Class</b></td>
        </tr>
        <?php foreach($parent_data_class['result']['rows'] as $row): ?>
            <tr>
                <?php foreach($parent_data_class['result']['variables'] as $variable): ?>
                    <td><?= $row[$variable]; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
<?php endif; ?>