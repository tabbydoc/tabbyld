<?php
/* @var $row_heading_class_query_results app\modules\main\controllers\DefaultController */
/* @var $row_heading_concept_query_results app\modules\main\controllers\DefaultController */
/* @var $row_heading_property_query_results app\modules\main\controllers\DefaultController */
/* @var $all_row_heading_class_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_row_heading_concept_query_runtime app\modules\main\controllers\DefaultController */
/* @var $all_row_heading_property_query_runtime app\modules\main\controllers\DefaultController */
?>

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