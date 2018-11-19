<?php

namespace app\modules\main\models;

use Yii;
use yii\base\Model;

/**
 * Class XLSXFileForm
 */
class XLSXFileForm extends Model
{
    public $xlsx_file;

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return array(
            array(['xlsx_file'], 'required'),
            array(['xlsx_file'], 'file', 'extensions'=>'xlsx', 'checkExtensionByMimeType'=>false),
        );
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return array(
            'xlsx_file' => Yii::t('app', 'XLSX_FILE_FORM_XLSX_FILE'),
        );
    }
}