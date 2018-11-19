<?php

namespace app\commands;

use yii\helpers\Console;
use yii\console\Controller;
use app\modules\main\models\Lang;

/**
 * LangController реализует консольные команды для работы с языками.
 */
class LangController extends Controller
{
    /**
     * Инициализация команд.
     */
    public function actionIndex()
    {
        echo 'yii lang/create' . PHP_EOL;
        echo 'yii lang/remove' . PHP_EOL;
    }

    /**
     * Команда создания языков по умолчанию.
     */
    public function actionCreate()
    {
        $model = new Lang();
        if($model->find()->count() == 0) {
            // Добавление английского языка
            $en_lang = new Lang();
            $en_lang->url = 'en';
            $en_lang->local = 'en-EN';
            $en_lang->name = 'Английский';
            $en_lang->default = 0;
            $this->log($en_lang->save());
            // Добавление русского языка
            $ru_lang = new Lang();
            $ru_lang->url = 'ru';
            $ru_lang->local = 'ru-RU';
            $ru_lang->name = 'Russian';
            $ru_lang->default = 1;
            $this->log($ru_lang->save());
        } else
            $this->stdout('Default languages created!', Console::FG_GREEN, Console::BOLD);
    }

    /**
     * Команда удаления всех языков.
     */
    public function actionRemove()
    {
        $model = new Lang();
        $this->log($model->deleteAll());
    }

    /**
     * Вывод сообщений на экран (консоль)
     * @param bool $success
     */
    private function log($success)
    {
        if ($success) {
            $this->stdout('Success!', Console::FG_GREEN, Console::BOLD);
        } else {
            $this->stderr('Error!', Console::FG_RED, Console::BOLD);
        }
        echo PHP_EOL;
    }
}