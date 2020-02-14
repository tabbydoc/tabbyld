<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%parent_class}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $class
 * @property int $candidate_entity
 *
 * @property CandidateEntity $candidateEntity
 */
class ParentClass extends \yii\db\ActiveRecord
{
    /**
     *  @return string table name
     */
    public static function tableName()
    {
        return '{{%parent_class}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            [['class', 'candidate_entity'], 'required'],
            [['candidate_entity'], 'integer'],
            [['class'], 'string'],
            [['candidate_entity'], 'exist', 'skipOnError' => true, 'targetClass' => CandidateEntity::className(),
                'targetAttribute' => ['candidate_entity' => 'id']],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'PARENT_CLASS_MODEL_ID'),
            'created_at' => Yii::t('app', 'PARENT_CLASS_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'PARENT_CLASS_MODEL_UPDATED_AT'),
            'class' => Yii::t('app', 'PARENT_CLASS_MODEL_CLASS'),
            'candidate_entity' => Yii::t('app', 'PARENT_CLASS_MODEL_CANDIDATE_ENTITY'),
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
    public function getCandidateEntity()
    {
        return $this->hasOne(CandidateEntity::className(), ['id' => 'candidate_entity']);
    }
}