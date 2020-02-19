<?php

use yii\db\Migration;

/**
 * Class m200131_135355_annotated_dataset
 */
class m200131_135355_annotated_dataset extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%annotated_dataset}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'author' => $this->string(),
            'status' => $this->smallInteger()->notNull()->defaultValue(0),
            'precision' => $this->double()->notNull(),
            'recall' => $this->double()->notNull(),
            'f_score' => $this->double()->notNull(),
            'runtime' => $this->double()->notNull(),
            'description' => $this->string(),
        ], $tableOptions);

        $this->createIndex('idx_annotated_dataset_name', '{{%annotated_dataset}}', 'name');
    }

    public function down()
    {
        $this->dropTable('{{%annotated_dataset}}');
    }
}