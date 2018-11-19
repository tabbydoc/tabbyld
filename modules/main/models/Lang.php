<?php

namespace app\modules\main\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%lang}}".
 *
 * @property integer $id
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $url
 * @property string $local
 * @property string $name
 * @property integer $default
 */
class Lang extends ActiveRecord
{
    public static $current = null; // Переменная, для хранения текущего объекта языка

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return [
            [['url', 'local', 'name'], 'required'],
            [['default'], 'integer'],
            [['url', 'local', 'name'], 'string', 'max' => 255]
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'LANG_MODEL_ID'),
            'created_at' => Yii::t('app', 'LANG_MODEL_CREATED_AT'),
            'updated_at' => Yii::t('app', 'LANG_MODEL_UPDATED_AT'),
            'url' => Yii::t('app', 'LANG_MODEL_URL'),
            'local' => Yii::t('app', 'LANG_MODEL_LOCAL'),
            'name' => Yii::t('app', 'LANG_MODEL_NAME'),
            'default' => Yii::t('app', 'LANG_MODEL_DEFAULT'),
        ];
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * Получение текущего объекта языка.
     * @return array|null|ActiveRecord
     */
    public static function getCurrent()
    {
        if( self::$current === null ){
            self::$current = self::getDefaultLang();
        }
        return self::$current;
    }

    /**
     * Установка текущего объекта языка и локаль пользователя.
     * @param null $url
     */
    public static function setCurrent($url = null)
    {
        $language = self::getLangByUrl($url);
        self::$current = ($language === null) ? self::getDefaultLang() : $language;
        Yii::$app->language = self::$current->local;
    }

    /**
     * Получения объекта языка по умолчанию.
     * @return array|null|ActiveRecord
     */
    public static function getDefaultLang()
    {
        return Lang::find()->where(['default' => 1])->one();
    }

    /**
     * Получения объекта языка по буквенному идентификатору.
     * @param null $url
     * @return array|null|ActiveRecord
     */
    public static function getLangByUrl($url = null)
    {
        if ($url === null) {
            return null;
        } else {
            $language = Lang::find()->where(['url' => $url])->one();
            if ( $language === null ) {
                return null;
            }else{
                return $language;
            }
        }
    }
}