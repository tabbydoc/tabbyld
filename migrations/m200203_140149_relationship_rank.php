<?php

use yii\db\Migration;

/**
 * Class m200203_140149_relationship_rank
 */
class m200203_140149_relationship_rank extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%relationship_rank}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'rank' => $this->double()->notNull(),
            'execution_time' => $this->double()->notNull(),
            'candidate_entity' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->addForeignKey("relationship_rank_candidate_entity_fk", "{{%relationship_rank}}",
            "candidate_entity", "{{%candidate_entity}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%relationship_rank}}');
    }
}