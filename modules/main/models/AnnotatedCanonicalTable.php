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
            [['name', 'annotated_dataset'], 'required'],
            ['annotated_dataset', 'integer'],
            [['name'], 'string', 'max' => 255],
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