<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%entity_context}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $context
 * @property int $candidate_entity
 *
 * @property CandidateEntity $candidateEntity
 */
class EntityContext extends \yii\db\ActiveRecord
{
    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%entity_context}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            [['context', 'candidate_entity'], 'required'],
            [['candidate_entity'], 'integer'],
            [['context'], 'string'],
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
            'id' => Yii::t('app', 'ENTITY_CONTEXT_MODEL_ID'),
            'created_at' => Yii::t('app', 'ENTITY_CONTEXT_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'ENTITY_CONTEXT_MODEL_UPDATED_AT'),
            'context' => Yii::t('app', 'ENTITY_CONTEXT_MODEL_CONTEXT'),
            'candidate_entity' => Yii::t('app', 'ENTITY_CONTEXT_MODEL_CANDIDATE_ENTITY'),
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