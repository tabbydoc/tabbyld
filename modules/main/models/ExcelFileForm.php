<?php

namespace app\modules\main\models;

use Yii;
use yii\base\Model;

/**
 * Class ExcelFileForm.
 */
class ExcelFileForm extends Model
{
    const CANONICAL_FORM = 'CANONICAL FORM';
    const NER_TAGS = 'NER TAGS';

    public $excel_file;

    /**
     * @return array the validation rules
     */
    public function rules()
    {
        return array(
            array(['excel_file'], 'required'),
            array(['excel_file'], 'file', 'extensions'=>'xls, xlsx', 'checkExtensionByMimeType'=>false),
        );
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return array(
            'excel_file' => Yii::t('app', 'EXCEL_FILE_FORM_EXCEL_FILE'),
        );
    }
}