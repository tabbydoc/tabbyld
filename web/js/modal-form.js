<!-- Формирование списка переключателей с родительскими классами кандидатами на форме модального окна -->
function createRadioInputs(current_selected_entity, parent_class_candidates, parent_classes) {
    // Удаление слоев с переключателями на форме модального окна
    $('.form-check').remove();
    // Форма модального окна
    var select_parent_class_form = document.getElementById("select-parent-class-form");
    // Цикл по массиву родительских классов кандидатов
    $.each(parent_class_candidates, function (entity_name, parent_data_class_candidate) {
        if (entity_name == current_selected_entity) {
            var count = 1;
            $.each(parent_data_class_candidate["result"]["rows"], function (id, item) {
                // Создание слоя переключателя
                var html_div = document.createElement("div");
                html_div.className = "form-check";
                // Создание переключателя
                var radio_input = document.createElement("input");
                radio_input.className = "form-check-input";
                radio_input.id = "radio" + count;
                radio_input.setAttribute("type", "radio");
                radio_input.setAttribute("name", "parent-class-radio");
                radio_input.setAttribute("value", item["object"]);
                // Цикл по массиву определенных родительских классов
                $.each(parent_classes, function (index, parent_class) {
                    if (index == entity_name && parent_class == item["object"])
                        radio_input.checked = true; // Выбор текущего родительского класса
                });
                // Добавление переключателя на слой
                html_div.appendChild(radio_input);
                // Создание метки (названия) для переключателя
                var html_label = document.createElement("label");
                html_label.className = "form-check-label";
                html_label.setAttribute("for", "radio" + count);
                html_label.innerHTML = "&lt;" + item["object"] + "&gt;";
                // Добавление метки переключателя на слой
                html_div.appendChild(html_label);
                // Добавление слоя с переключателем на форму модального окна
                select_parent_class_form.insertBefore(html_div, select_parent_class_form.children[count]);
                count++;
            });
        }
    });
}

<!-- Обновление отображения родительских классов в канонической таблице -->
function displayParentClasses(parent_classes, current_selected_entity, selected_parent_class, link_class_name) {
    // Цикл по массиву определенных родительских классов
    $.each(parent_classes, function (entity_name, parent_class) {
        if (entity_name == current_selected_entity) {
            // Обновление родительского класса для аннотированной сущности
            parent_classes[entity_name] = selected_parent_class;
            // Список ссылок на родительские классы
            var parent_class_links = document.getElementsByClassName(link_class_name);
            // Обход всех ссылок родительских классов, если данный список ссылок определен
            $.each(parent_class_links, function (id, parent_class_link) {
                if (parent_class_link.getAttribute("annotated-entity") == entity_name &&
                    parent_class_link.title == parent_class) {
                    // Обновление ссылки на родительский класс
                    parent_class_link.title = selected_parent_class;
                    parent_class_link.text = selected_parent_class.replace("http://dbpedia.org/ontology/", "dbo:");
                }
            });
        }
    });
}