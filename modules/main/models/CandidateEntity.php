<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%candidate_entity}}".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $entity
 * @property double $levenshtein_distance
 * @property double $aggregated_rank
 * @property int $cell_value
 *
 * @property CellValue $cellValue
 * @property RelationshipRank[] $relationshipRanks
 * @property NerClassRank[] $nerClassRanks
 * @property EntityContext[] $entityContexts
 * @property ContextSimilarity[] $contextSimilarityRanks
 * @property ParentClass[] $parentClasses
 * @property SemanticSimilarity[] $semanticSimilarityRanks
 */
class CandidateEntity extends \yii\db\ActiveRecord
{
    /**
     * @return string table name
     */
    public static function tableName()
    {
        return '{{%candidate_entity}}';
    }

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            [['entity', 'cell_value'], 'required'],
            [['cell_value'], 'integer'],
            [['levenshtein_distance', 'aggregated_rank'], 'default', 'value' => null],
            [['levenshtein_distance', 'aggregated_rank'], 'number'],
            [['entity'], 'string', 'max' => 1000],
            [['cell_value'], 'exist', 'skipOnError' => true, 'targetClass' => CellValue::className(),
                'targetAttribute' => ['cell_value' => 'id']],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_ID'),
            'created_at' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_UPDATED_AT'),
            'entity' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_ENTITY'),
            'levenshtein_distance' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_LEVENSHTEIN_DISTANCE'),
            'aggregated_rank' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_AGGREGATED_RANK'),
            'cell_value' => Yii::t('app', 'CANDIDATE_ENTITY_MODEL_CELL_VALUE'),
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
    public function getCellValue()
    {
        return $this->hasOne(CellValue::className(), ['id' => 'cell_value']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRelationshipRanks()
    {
        return $this->hasMany(RelationshipRank::className(), ['candidate_entity' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNerClassRanks()
    {
        return $this->hasMany(NerClassRank::className(), ['candidate_entity' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEntityContexts()
    {
        return $this->hasMany(EntityContext::className(), ['candidate_entity' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getContextSimilarityRanks()
    {
        return $this->hasMany(ContextSimilarity::className(), ['candidate_entity' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParentClasses()
    {
        return $this->hasMany(ParentClass::className(), ['candidate_entity' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSemanticSimilarityRanks()
    {
        return $this->hasMany(SemanticSimilarity::className(), ['candidate_entity' => 'id']);
    }
}