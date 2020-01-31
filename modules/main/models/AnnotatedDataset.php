<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%annotated_dataset}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $name
 * @property string $author
 * @property int $status
 * @property string $description
 *
 * @property AnnotatedCanonicalTable[] $annotatedCanonicalTables
 */
class AnnotatedDataset extends \yii\db\ActiveRecord
{
    const PUBLIC_STATUS = 0;   // Открытый набор данных (статус)
    const PRIVATE_STATUS = 1;  // Частный набор данных (статус)

    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%annotated_dataset}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            ['name', 'required'],
            ['status', 'integer'],
            [['name', 'author'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 1000],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_ID'),
            'created_at' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_UPDATED_AT'),
            'name' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_NAME'),
            'author' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_AUTHOR'),
            'status' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_STATUS'),
            'description' => Yii::t('app', 'ANNOTATED_DATASET_MODEL_DESCRIPTION'),
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
    public function getAnnotatedCanonicalTables()
    {
        return $this->hasMany(AnnotatedCanonicalTable::className(), ['annotated_dataset' => 'id']);
    }
}