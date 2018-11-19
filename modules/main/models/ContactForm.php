<?php

namespace app\modules\main\models;

use Yii;
use yii\base\Model;

/**
 * ContactForm is the model behind the contact form.
 */
class ContactForm extends Model
{
    public $name;       // Имя отправителя
    public $email;      // Почта отправителя
    public $subject;    // Тема сообщения
    public $body;       // Текст сообщения
    public $verifyCode; // Код проверки

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // name, email, subject and body are required
            [['name', 'email', 'subject', 'body'], 'required'],
            // email has to be a valid email address
            ['email', 'email'],
            // verifyCode needs to be entered correctly
            ['verifyCode', 'captcha', 'captchaAction' => '/main/default/captcha'],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('app', 'CONTACT_FORM_NAME'),
            'email' => Yii::t('app', 'CONTACT_FORM_EMAIL'),
            'subject' => Yii::t('app', 'CONTACT_FORM_SUBJECT'),
            'body' => Yii::t('app', 'CONTACT_FORM_BODY'),
            'verifyCode' => Yii::t('app', 'CONTACT_FORM_VERIFICATION_CODE'),
        ];
    }

    /**
     * Sends an email to the specified email address using the information collected by this model.
     * @param  string  $email the target email address
     * @return boolean whether the model passes validation
     */
    public function contact($email)
    {
        if ($this->validate()) {
            Yii::$app->mailer->compose()
                ->setTo($email)
                ->setFrom([$this->email => $this->name])
                ->setSubject($this->subject)
                ->setTextBody($this->body)
                ->send();

            return true;
        }
        return false;
    }
}