<?php

use yii\db\Migration;

/**
 * Class m200214_085804_parent_class
 */
class m200214_085804_parent_class extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%parent_class}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'class' => $this->string()->notNull(),
            'candidate_entity' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->addForeignKey("parent_class_candidate_entity_fk", "{{%parent_class}}",
            "candidate_entity", "{{%candidate_entity}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%parent_class}}');
    }
}