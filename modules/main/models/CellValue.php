<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%cell_value}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $name
 * @property int $type
 * @property double $execution_time
 * @property int $annotated_canonical_table
 *
 * @property CandidateEntity[] $candidateEntities
 * @property AnnotatedCanonicalTable $annotatedCanonicalTable
 */
class CellValue extends \yii\db\ActiveRecord
{
    const DATA = 0;           // Тип значения ячейки канонической таблицы для заголовка "DATA"
    const ROW_HEADING = 1;    // Тип значения ячейки канонической таблицы для заголовка "RowHeading1"
    const COLUMN_HEADING = 2; // Тип значения ячейки канонической таблицы для заголовка "ColumnHeading"

    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%cell_value}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            [['name', 'annotated_canonical_table'], 'required'],
            [['type', 'annotated_canonical_table'], 'integer'],
            [['execution_time'], 'number'],
            [['name'], 'string', 'max' => 1000],
            [['annotated_canonical_table'], 'exist', 'skipOnError' => true,
                'targetClass' => AnnotatedCanonicalTable::className(),
                'targetAttribute' => ['annotated_canonical_table' => 'id']],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'CELL_VALUE_MODEL_ID'),
            'created_at' => Yii::t('app', 'CELL_VALUE_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'CELL_VALUE_MODEL_UPDATED_AT'),
            'name' => Yii::t('app', 'CELL_VALUE_MODEL_NAME'),
            'type' => Yii::t('app', 'CELL_VALUE_MODEL_TYPE'),
            'execution_time' => Yii::t('app', 'CELL_VALUE_MODEL_EXECUTION_TIME'),
            'annotated_canonical_table' => Yii::t('app', 'CELL_VALUE_MODEL_ANNOTATED_CANONICAL_TABLE'),
        ];
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCandidateEntities()
    {
        return $this->hasMany(CandidateEntity::className(), ['cell_value' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAnnotatedCanonicalTable()
    {
        return $this->hasOne(AnnotatedCanonicalTable::className(), ['id' => 'annotated_canonical_table']);
    }
}