<?php

namespace app\commands;

use yii\helpers\Console;
use yii\console\Controller;
use app\modules\main\models\AnnotatedDataset;

/**
 * AnnotatedDatasetController реализует консольные команды для работы с наборами данных.
 */
class AnnotatedDatasetController extends Controller
{
    /**
     * Инициализация команд.
     */
    public function actionIndex()
    {
        echo 'yii annotated-dataset/remove' . PHP_EOL;
    }

    /**
     * Команда удаления всех наборов данных.
     */
    public function actionRemove()
    {
        $model = new AnnotatedDataset();
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