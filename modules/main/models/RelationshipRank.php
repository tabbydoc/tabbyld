<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%relationship_rank}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property double $rank
 * @property double $execution_time
 * @property int $candidate_entity
 *
 * @property CandidateEntity $candidateEntity
 */
class RelationshipRank extends \yii\db\ActiveRecord
{
    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%relationship_rank}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            [['rank', 'execution_time', 'candidate_entity'], 'required'],
            [['candidate_entity'], 'integer'],
            [['rank', 'execution_time'], 'number'],
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
            'id' => Yii::t('app', 'RELATIONSHIP_RANK_MODEL_ID'),
            'created_at' => Yii::t('app', 'RELATIONSHIP_RANK_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'RELATIONSHIP_RANK_MODEL_UPDATED_AT'),
            'rank' => Yii::t('app', 'RELATIONSHIP_RANK_MODEL_RANK'),
            'execution_time' => Yii::t('app', 'RELATIONSHIP_RANK_MODEL_EXECUTION_TIME'),
            'candidate_entity' => Yii::t('app', 'RELATIONSHIP_RANK_MODEL_CANDIDATE_ENTITY'),
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