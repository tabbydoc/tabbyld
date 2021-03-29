<?php

namespace app\components;

use Yii;
use app\modules\knowledge_base\models\DataType;
use app\modules\knowledge_base\models\OntologyClass;
use app\modules\knowledge_base\models\Property;
use app\modules\knowledge_base\models\PropertyValue;
use app\modules\knowledge_base\models\Relationship;
use app\modules\knowledge_base\models\RightHandSide;
use app\modules\knowledge_base\models\LeftHandSide;
use app\modules\knowledge_base\models\Object;
use app\modules\knowledge_base\models\ObjectRelationship;

/**
 * OWLCodeGenerator.
 * Класс OWLCodeGenerator обеспечивает генерацию кода онтологии в формате OWL1 DL на основе онтологической модели в БД.
 */
class OWLCodeGenerator
{
    /**
     * Нормализация текста под OWL.
     * @param $string - входная строка
     * @return string - выходная строка с удаленными пробелами и заменой в в верхний регистр первых букв слов
     */
    public function normalizingText($string) {
        $str = mb_convert_case($string, MB_CASE_TITLE, "UTF-8");

        return str_replace(' ', '', $str);
    }

    /**
     * Нормализация типа данных под OWL (XML Schema).
     * @param $data_type_name - текущий тип данных их модели онтологии
     * @return string - тип данных соответствующий типам из XML-схемы
     */
    public function normalizingDataType($data_type_name)
    {
        // Тип данных по умолчанию
        $xsd_data_type = 'string';
        // Массив некоторых типов данных XML-схемы
        $data_types = array('string', 'integer', 'long', 'float', 'double', 'short', 'byte', 'boolean', 'time', 'date');
        // Поиск совпадения типа данных
        foreach ($data_types as $data_type)
            if (stristr($data_type_name, $data_type))
                $xsd_data_type = $data_type;

        return $xsd_data_type;
    }

    /**
     * Проверка существования сгенерированных элементов у онтологии.
     * @param $id - идентификатор базы знаний (онтологии)
     * @return bool - результат проверки
     */
    public function existElements($id)
    {
        // Переменная результата проверки
        $is_exist = false;
        // Поиск всех типов данных принадлежащих данной онтологии
        $data_types = DataType::find()->where(array('knowledge_base' => $id))->all();
        // Поиск всех классов принадлежащих данной онтологии
        $ontology_classes = OntologyClass::find()->where(array('ontology' => $id))->all();
        // Поиск всех связей принадлежащих данной онтологии
        $relationships = Relationship::find()->where(array('ontology' => $id))->all();
        // Поиск всех объектов принадлежащих данной онтологии
        $objects = Object::find()->where(array('ontology' => $id))->all();
        // Если выборка классов или объектов или связей или типов данных не пустая,
        // то меняем переменную результата проверки
        if (!empty($data_types) || !empty($ontology_classes) || !empty($relationships) || !empty($objects))
            $is_exist = true;

        return $is_exist;
    }

    /**
     * Генерация кода классов онтологии.
     * @param $classes - классы
     * @param $relationships - отношения между классами
     * @param $content - текущий текст OWL-файла базы знаний
     * @return string - сформированный текст OWL-файла базы знаний включающий описание классов
     */
    public function generateClasses($classes, $relationships, $content)
    {
        // Обход всех классов данной модели онтологии
        foreach ($classes as $class) {
            $is_subclass = false;
            // Обход всех отношений данной модели онтологии
            foreach ($relationships as $relationship) {
                // Если отношение является наследованием или эквивалентностью
                if ($relationship->is_inheritance || $relationship->is_equivalence) {
                    // Поиск левой части отношения для данного класса и отношения наследования
                    $left_hand_side = LeftHandSide::find()
                        ->where(array('relationship' => $relationship->id, 'ontology_class' => $class->id))
                        ->one();
                    // Если левая часть отношения найдена
                    if (!empty($left_hand_side)) {
                        $is_subclass = true;
                        // Поиск правой части отношения
                        $right_hand_side = RightHandSide::find()
                            ->where(array('relationship' => $relationship->id))
                            ->one();
                        // Поиск класса из правой части отношения
                        $parent_class = OntologyClass::findOne($right_hand_side->ontology_class);
                        // Описание класса онтологии
                        $content .= "\t<owl:Class rdf:ID=\"" . self::normalizingText($class->name) . "\">\r\n";
                        // Описание класса онтологии как подкласса другого класса
                        if ($relationship->is_inheritance)
                            $content .= "\t\t<rdfs:subClassOf rdf:resource=\"" .
                                self::normalizingText($parent_class->name) . "\" />\r\n";
                        // Описание класса онтологии эквивалентного другому классу
                        if ($relationship->is_equivalence)
                            $content .= "\t\t<owl:equivalentClass rdf:resource=\"" .
                                self::normalizingText($parent_class->name) . "\" />\r\n";
                        $content .= "\t</owl:Class>\r\n";
                    }
                }
            }
            // Если данный класс не является подклассом или эквивалентом другого класса
            if ($is_subclass == false)
                $content .= "\t<owl:Class rdf:ID=\"" . self::normalizingText($class->name) . "\" />\r\n";
        }

        return $content;
    }

    /**
     * Генерация кода объектных свойств онтологии.
     * @param $associations - отношения типа ассоциация между классами
     * @param $content - текущий текст OWL-файла базы знаний
     * @return string - сформированный текст OWL-файла базы знаний включающий описание объектных свойств
     */
    public function generateObjectProperties($associations, $content)
    {
        // Обход всех отношений типа ассоциация данной модели онтологии
        foreach ($associations as $association) {
            // Поиск класса из левой части отношения
            $left_hand_side = LeftHandSide::find()->where(array('relationship' => $association->id))->one();
            $left_class = OntologyClass::findOne($left_hand_side->ontology_class);
            // Поиск класса из правой части отношения
            $right_hand_side = RightHandSide::find()->where(array('relationship' => $association->id))->one();
            $right_class = OntologyClass::findOne($right_hand_side->ontology_class);
            // Описание объектного свойства
            $content .= "\t<owl:ObjectProperty rdf:ID=\"" . self::normalizingText($association->name) . "\">\r\n";
            $content .= "\t\t<rdfs:domain rdf:resource=\"#" . self::normalizingText($left_class->name) . "\" /> \r\n";
            $content .= "\t\t<rdfs:range rdf:resource=\"#" . self::normalizingText($right_class->name) . "\" /> \r\n";
            $content .= "\t</owl:ObjectProperty>\r\n";
        }

        return $content;
    }

    /**
     * Генерация кода свойств значений онтологии.
     * @param $classes - классы
     * @param $content - текущий текст OWL-файла базы знаний
     * @return string - сформированный текст OWL-файла базы знаний включающий описание свойств значений
     */
    public function generateDataTypeProperties($classes, $content)
    {
        // Обход всех классов данной модели онтологии
        foreach ($classes as $class) {
            // Поиск всех свойств у данного класса
            $properties = Property::find()->where(array('ontology_class' => $class->id))->all();
            // Обход всех свойств данного класса
            foreach ($properties as $property) {
                // Поиск типа данных для свойства класса
                $data_type = DataType::findOne($property->data_type);
                // Описание свойства значения
                $content .= "\t<owl:DatatypeProperty rdf:ID=\"" . self::normalizingText($property->name) . "\">\r\n";
                $content .= "\t\t<rdfs:domain rdf:resource=\"#" . self::normalizingText($class->name) . "\" /> \r\n";
                $content .= "\t\t<rdfs:range rdf:resource=\"&xsd;" .
                    self::normalizingDataType($data_type->name) . "\" /> \r\n";
                $content .= "\t</owl:DatatypeProperty>\r\n";
            }
        }

        return $content;
    }

    /**
     * Генерация кода индивидов онтологии вместе со свойствами.
     * @param $objects - объекты
     * @param $content - текущий текст OWL-файла базы знаний
     * @return string - сформированный текст OWL-файла базы знаний включающий описание индивидов
     */
    public function generateIndividuals($objects, $content)
    {
        // Обход всех объектов данной модели онтологии
        foreach ($objects as $object) {
            // Поиск класса данного объекта
            $class = OntologyClass::findOne($object->ontology_class);
            // Поиск всех отношений для данного объекта
            $object_relationships = ObjectRelationship::find()->where(array('object' => $object->id))->all();
            // Поиск всех значений свойств для данного объекта
            $property_values = PropertyValue::find()->where(array('object' => $object->id))->all();
            // Если значения свойства или отношения объектов найдены
            if (!empty($property_values) || !empty($object_relationships)) {
                // Открытие тега индивида с конкретным значением свойства
                $content .= "\t<" . self::normalizingText($class->name) . " rdf:ID=\"" .
                    self::normalizingText($object->name) . "\">\r\n";
                // Обход всех найденных отношений объекта
                foreach ($object_relationships as $object_relationship) {
                    // Поиск отношения класса
                    $relationship = Relationship::findOne($object_relationship->relationship);
                    // Описание свойства объекта для индивида
                    $content .= "\t\t<" . self::normalizingText($relationship->name) . " rdf:resource=\"#" .
                        self::normalizingText($object_relationship->name) . "\" />\r\n";
                }
                // Обход всех найденных значений свойств для данного объекта
                foreach ($property_values as $property_value) {
                    // Поиск свойства класса
                    $property = Property::findOne($property_value->property);
                    // Поиск типа данных для найденного свойства класса
                    $data_type = DataType::findOne($property->data_type);
                    // Описание значения свойства для данного индивида
                    $content .= "\t\t<" . self::normalizingText($property->name) . " rdf:datatype=\"&xsd;" .
                        self::normalizingDataType($data_type->name) . "\">" . $property_value->name . "</" .
                        self::normalizingText($property->name) . ">\r\n";
                }
                // Закрытие тега индивида
                $content .= "\t</" . self::normalizingText($class->name) . ">\r\n";
            }
            else
                // Описание простого индивида онтологии
                $content .= "\t<" . self::normalizingText($class->name) . " rdf:ID=\"" .
                    self::normalizingText($object->name) . "\" />\r\n";
        }

        return $content;
    }

    /**
     * Генерация кода базы знаний в формате OWL.
     * @param $knowledge_base - база знаний
     */
    public function generateOWLCode($knowledge_base)
    {
        // Поиск всех классов принадлежащих данной базе знаний (модели онтологии)
        $classes = OntologyClass::find()->where(array('ontology' => $knowledge_base->id))->all();
        // Поиск всех объектов принадлежащих данной базе знаний (модели онтологии)
        $objects = Object::find()->where(array('ontology' => $knowledge_base->id))->all();
        // Поиск всех отношений принадлежащих данной базе знаний (модели онтологии)
        $relationships = Relationship::find()->where(array('ontology' => $knowledge_base->id))->all();

        // Определение наименования файла
        $file = 'exported_knowledge_base.owl';
        // Создание и открытие данного файла на запись, если он не существует
        if (!file_exists($file))
            fopen($file, 'w');

        // Начальное описание файла базы знаний (онтологии)
        $content = "<?xml version=\"1.0\"?>\r\n";
        $content .= "<rdf:RDF\r\n";
        $content .= "\txmlns      = 'http://example.org/" . self::normalizingText($knowledge_base->name) . "#\"\r\n";
        $content .= "\txml:base   = 'http://example.org/" . self::normalizingText($knowledge_base->name) . "#\"\r\n";
        $content .= "\txmlns:owl  = \"http://www.w3.org/2002/07/owl#\"\r\n";
        $content .= "\txmlns:owl  = \"http://www.w3.org/2002/07/owl#\"\r\n";
        $content .= "\txmlns:rdf  = \"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\r\n";
        $content .= "\txmlns:rdfs = \"http://www.w3.org/2000/01/rdf-schema#\"\r\n";
        $content .= "\txmlns:xsd  = \"http://www.w3.org/2001/XMLSchema#\">\r\n";
        $content .= "\r\n";
        // Описание онтологии
        if ($knowledge_base->description == '')
            $content .= "\t<owl:Ontology rdf:about=\"" . self::normalizingText($knowledge_base->name) . "\" />\r\n\r\n";
        else {
            $content .= "\t<owl:Ontology rdf:about=\"" . self::normalizingText($knowledge_base->name) . "\">\r\n";
            $content .= "\t\t<rdfs:comment>" . $knowledge_base->description . "</rdfs:comment>\r\n";
            $content .= "\t</owl:Ontology>\r\n";
        }

        // Вызов метода генерации классов OWL, если они существуют
        if (!empty($classes))
            $content = self::generateClasses($classes, $relationships, $content . "\r\n");

        // Поиск всех отношений типа ассоциация принадлежащих данной базе знаний (модели онтологии)
        $associations = Relationship::find()
            ->where(array('ontology' => $knowledge_base->id, 'is_association' => true))
            ->all();
        // Вызов метода генерации объектных свойств OWL, если существуют отношения типа ассоциация
        if (!empty($associations))
            $content = self::generateObjectProperties($associations, $content . "\r\n");

        // Вызов метода генерации свойств значений OWL, если существуют классы онтологии
        if (!empty($classes))
            $content = self::generateDataTypeProperties($classes, $content . "\r\n");

        // Вызов метода генерации индивидов OWL, если они существуют
        if (!empty($objects))
            $content = self::generateIndividuals($objects, $content . "\r\n");

        // Закрытие тега RDF
        $content .= "</rdf:RDF>";
        // Выдача OWL-файла пользователю для скачивания
        header("Content-type: application/octet-stream");
        header('Content-Disposition: filename="'.$file.'"');
        echo $content;
        exit;
    }
}