<?php

/* @var $data app\modules\main\controllers\DefaultController */

use yii\bootstrap\Html;
use yii\bootstrap\ActiveForm;
use app\components\CanonicalTableAnnotator;

?>

<script type="text/javascript">
    // Выполнение скрипта при загрузке страницы
    $(document).ready(function () {
        // Каноническая таблица
        var resulting_canonical_table = document.getElementById("resulting_canonical-table");
        // Цикл по ячейкам канонической таблицы
        for (var i = 0, row; row = resulting_canonical_table.rows[i]; i++) {
            for (var j = 0, col; col = row.cells[j]; j++) {
                // Разбиение ячейки таблицы (формирование массива подстрок)
                var string_array = col.innerHTML.split(" | ");
                col.innerHTML = "";
                // Цикл по массиву подстрок
                $.each(string_array, function (str_key, string) {
                    col.innerHTML += string;
                    // Если ячейка принадлежит столбцу "DATA"
                    if (col.className == "data-value")
                        $.each(data_entities, function (id, value) {
                            if (string == id) {
                                col.innerHTML += " (" + value.replace("http://dbpedia.org/resource/", "db:");
                                $.each(parent_data_classes, function (index, parent_data_class) {
                                    if (index == value) {
                                        col.innerHTML += " - <a href='#' class='data-link' title='" + parent_data_class +
                                            "' data-toggle='modal' data-target='#selectParentClassModalForm' " +
                                            "annotated-entity='" + value + "'>" +
                                            parent_data_class.replace("http://dbpedia.org/ontology/", "dbo:") + "</a>";
                                    }
                                });
                                col.innerHTML += ")";
                            }
                        });
                    // Если ячейка принадлежит столбцу "RowHeading1"
                    if (col.className == "row-heading-value")
                        $.each(row_heading_entities, function (id, value) {
                            if (string == id) {
                                var abbreviated_parent_concept = value.replace("http://dbpedia.org/ontology/", "dbo:");
                                abbreviated_parent_concept =
                                    abbreviated_parent_concept.replace("http://dbpedia.org/resource/", "db:");
                                abbreviated_parent_concept =
                                    abbreviated_parent_concept.replace("http://dbpedia.org/property/", "dbp:");
                                col.innerHTML += " (" + abbreviated_parent_concept;
                                $.each(parent_row_heading_classes, function (index, parent_row_heading_class) {
                                    if (index == value)
                                        col.innerHTML += " - <a href='#' class='row-heading-link' title='" +
                                            parent_row_heading_class + "' annotated-entity='" + value +
                                            "' data-toggle='modal' data-target='#selectParentClassModalForm'>" +
                                            parent_row_heading_class.replace("http://dbpedia.org/ontology/", "dbo:") +
                                            "</a>";
                                });
                                col.innerHTML += ")";
                            }
                        });
                    // Если ячейка принадлежит столбцу "ColumnHeading"
                    if (col.className == "column-heading-value")
                        $.each(column_heading_entities, function (id, value) {
                            if (string == id) {
                                var abbreviated_parent_concept = value.replace("http://dbpedia.org/ontology/", "dbo:");
                                abbreviated_parent_concept =
                                    abbreviated_parent_concept.replace("http://dbpedia.org/resource/", "db:");
                                abbreviated_parent_concept =
                                    abbreviated_parent_concept.replace("http://dbpedia.org/property/", "dbp:");
                                col.innerHTML += " (" + abbreviated_parent_concept;
                                $.each(parent_column_heading_classes, function (index, parent_column_heading_class) {
                                    if (index == value)
                                        col.innerHTML += " - <a href='#' class='column-heading-link' title='" +
                                            parent_column_heading_class + "' annotated-entity='" + value +
                                            "' data-toggle='modal' data-target='#selectParentClassModalForm'>" +
                                            parent_column_heading_class.replace("http://dbpedia.org/ontology/", "dbo:") +
                                            "</a>";
                                });
                                col.innerHTML += ")";
                            }
                        });
                    if (string_array.length > (str_key + 1))
                        col.innerHTML += " | ";
                });
            }
        }
        // Обработка нажатия ссылки в ячейке столбца "DATA"
        $(".data-link").click(function(e) {
            // Определение текущей выбранной сущности
            current_selected_data_entity = this.getAttribute("annotated-entity");
            current_selected_row_heading_entity = null;
            current_selected_column_heading_entity = null;
            // Создание списка переключателей с родительскими классами кандидатами
            createRadioInputs(current_selected_data_entity, parent_data_class_candidates, parent_data_classes);
        });
        // Обработка нажатия ссылки в ячейке столбца "RowHeading1"
        $(".row-heading-link").click(function(e) {
            // Определение текущей выбранной сущности
            current_selected_data_entity = null;
            current_selected_row_heading_entity = this.getAttribute("annotated-entity");
            current_selected_column_heading_entity = null;
            // Создание списка переключателей с родительскими классами кандидатами
            createRadioInputs(current_selected_row_heading_entity, parent_row_heading_class_candidates,
                parent_row_heading_classes);
        });
        // Обработка нажатия ссылки в ячейке столбца "ColumnHeading"
        $(".column-heading-link").click(function(e) {
            // Определение текущей выбранной сущности
            current_selected_data_entity = null;
            current_selected_row_heading_entity = null;
            current_selected_column_heading_entity = this.getAttribute("annotated-entity");
            // Создание списка переключателей с родительскими классами кандидатами
            createRadioInputs(current_selected_column_heading_entity, parent_column_heading_class_candidates,
                parent_column_heading_classes);
        });
        // Обработка нажатия кнопки сохранения выбранного родительского класса
        $(".save-parent-class-button").click(function(e) {
            // Скрытие модельного окна
            $("#selectParentClassModalForm").modal("hide");
            // Список переключателей с родительскими классами кандидатами
            var parent_class_radio = document.getElementsByName("parent-class-radio");
            // Цикл по списку переключателей с родительскими классами кандидатами
            for (var i = 0; i < parent_class_radio.length; i++)  {
                if (parent_class_radio[i].checked) {
                    // Если выбрана сущность из столбца "DATA"
                    if (current_selected_data_entity)
                        // Обновление отображения родительских классов в канонической таблице
                        displayParentClasses(parent_data_classes, current_selected_data_entity,
                            parent_class_radio[i].value, "data-link");
                    // Если выбрана сущность из столбца "RowHeading1"
                    if (current_selected_row_heading_entity)
                        // Обновление отображения родительских классов в канонической таблице
                        displayParentClasses(parent_row_heading_classes, current_selected_row_heading_entity,
                            parent_class_radio[i].value, "row-heading-link");
                    // Если выбрана сущность из столбца "ColumnHeading"
                    if (current_selected_column_heading_entity)
                        // Обновление отображения родительских классов в канонической таблице
                        displayParentClasses(parent_column_heading_classes, current_selected_column_heading_entity,
                            parent_class_radio[i].value, "column-heading-link");
                }
            }
        });
    });
</script>

<?php $form = ActiveForm::begin([
    'id'=>'export-file-form'
]); ?>

    <?= Html::Button('<span class="glyphicon glyphicon-download-alt"></span> ' .
        Yii::t('app', 'BUTTON_EXPORT_EXCEL_FILE'), [
            'class' => 'btn btn-success save-parent-class-button'
    ]); ?>

    <?= Html::Button('<span class="glyphicon glyphicon-download-alt"></span> ' .
        Yii::t('app', 'BUTTON_EXPORT_RDF_FILE'), [
            'class' => 'btn btn-success save-parent-class-button',
            'style' => 'margin:5px'
    ]); ?>

<?php ActiveForm::end(); ?>

<h2><?= Yii::t('app', 'TABLE_ANNOTATION_PAGE_RESULTING_TABLE') ?></h2>

<table id="resulting_canonical-table" class="table table-striped table-bordered">
    <tr>
        <td class="data-title"><b><?= CanonicalTableAnnotator::DATA_TITLE; ?></b></td>
        <td class="row-heading-title"><b><?= CanonicalTableAnnotator::ROW_HEADING_TITLE; ?></b></td>
        <td class="column-heading-title"><b><?= CanonicalTableAnnotator::COLUMN_HEADING_TITLE; ?></b></td>
    </tr>
    <?php foreach($data as $item): ?>
        <tr>
            <?php foreach($item as $title => $value): ?>
                <?php if($title == CanonicalTableAnnotator::DATA_TITLE): ?>
                    <td class="data-value"><?php echo $value; ?></td>
                <?php endif; ?>
                <?php if($title == CanonicalTableAnnotator::ROW_HEADING_TITLE): ?>
                    <td class="row-heading-value"><?php echo $value; ?></td>
                <?php endif; ?>
                <?php if($title == CanonicalTableAnnotator::COLUMN_HEADING_TITLE): ?>
                    <td class="column-heading-value"><?php echo $value; ?></td>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
</table>