<?php

use yii\db\Migration;

/**
 * Class m200214_094234_semantic_similarity
 */
class m200214_094234_semantic_similarity extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%semantic_similarity}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'rank' => $this->double()->notNull(),
            'execution_time' => $this->double()->notNull(),
            'candidate_entity' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->addForeignKey("semantic_similarity_candidate_entity_fk", "{{%semantic_similarity}}",
            "candidate_entity", "{{%candidate_entity}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%semantic_similarity}}');
    }
}