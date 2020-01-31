<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%annotated_row}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $data
 * @property string $row_heading
 * @property string $column_heading
 * @property int $annotated_canonical_table
 *
 * @property AnnotatedCanonicalTable $annotatedCanonicalTable
 */
class AnnotatedRow extends \yii\db\ActiveRecord
{
    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%annotated_row}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            ['annotated_canonical_table', 'required'],
            ['annotated_canonical_table', 'integer'],
            [['data', 'row_heading', 'column_heading'], 'string', 'max' => 1000],
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
            'id' => Yii::t('app', 'ANNOTATED_ROW_MODEL_ID'),
            'created_at' => Yii::t('app', 'ANNOTATED_ROW_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'ANNOTATED_ROW_MODEL_UPDATED_AT'),
            'data' => Yii::t('app', 'ANNOTATED_ROW_MODEL_DATA'),
            'row_heading' => Yii::t('app', 'ANNOTATED_ROW_MODEL_ROW_HEADING'),
            'column_heading' => Yii::t('app', 'ANNOTATED_ROW_MODEL_COLUMN_HEADING'),
            'annotated_canonical_table' => Yii::t('app',
                'ANNOTATED_ROW_MODEL_ANNOTATED_CANONICAL_TABLE'),
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
    public function getAnnotatedCanonicalTable()
    {
        return $this->hasOne(AnnotatedCanonicalTable::className(), ['id' => 'annotated_canonical_table']);
    }
}