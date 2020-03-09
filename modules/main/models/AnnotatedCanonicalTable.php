<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%annotated_canonical_table}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $name
 * @property int $total_element_number
 * @property int $annotated_element_number
 * @property int $correctly_annotated_element_number
 * @property double $accuracy
 * @property double $precision
 * @property double $recall
 * @property double $f_score
 * @property double $runtime
 * @property string $description
 * @property int $annotated_dataset
 *
 * @property AnnotatedDataset $annotatedDataset
 * @property AnnotatedRow[] $annotatedRows
 */
class AnnotatedCanonicalTable extends \yii\db\ActiveRecord
{
    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%annotated_canonical_table}}';
    }

    /**
     *  @return array the validation rules
     */
    public function rules()
    {
        return [
            [['name', 'total_element_number', 'annotated_element_number', 'correctly_annotated_element_number',
                'accuracy', 'precision', 'recall', 'f_score', 'runtime', 'annotated_dataset'], 'required'],
            [['total_element_number', 'annotated_element_number', 'correctly_annotated_element_number',
                'annotated_dataset'], 'integer'],
            [['accuracy', 'precision', 'recall', 'f_score', 'runtime'], 'double'],
            [['name'], 'string', 'max' => 300],
            [['description'], 'string', 'max' => 1000],
            [['annotated_dataset'], 'exist', 'skipOnError' => true, 'targetClass' => AnnotatedDataset::className(),
                'targetAttribute' => ['annotated_dataset' => 'id']],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_ID'),
            'created_at' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_UPDATED_AT'),
            'name' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_NAME'),
            'total_element_number' => Yii::t('app',
                'ANNOTATED_CANONICAL_TABLE_MODEL_TOTAL_ELEMENT_NUMBER'),
            'annotated_element_number' => Yii::t('app',
                'ANNOTATED_CANONICAL_TABLE_MODEL_ANNOTATED_ELEMENT_NUMBER'),
            'correctly_annotated_element_number' => Yii::t('app',
                'ANNOTATED_CANONICAL_TABLE_MODEL_CORRECTLY_ANNOTATED_ELEMENT_NUMBER'),
            'accuracy' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_ACCURACY'),
            'precision' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_PRECISION'),
            'recall' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_RECALL'),
            'f_score' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_F_SCORE'),
            'runtime' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_RUNTIME'),
            'description' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_DESCRIPTION'),
            'annotated_dataset' => Yii::t('app', 'ANNOTATED_CANONICAL_TABLE_MODEL_ANNOTATED_DATASET'),
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
    public function getAnnotatedDataset()
    {
        return $this->hasOne(AnnotatedDataset::className(), ['id' => 'annotated_dataset']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAnnotatedRows()
    {
        return $this->hasMany(AnnotatedRow::className(), ['annotated_canonical_table' => 'id']);
    }
}