<?php

use yii\db\Migration;

/**
 * Class m200212_144720_heading_rank
 */
class m200212_144720_heading_rank extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%heading_rank}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'rank' => $this->double()->notNull(),
            'execution_time' => $this->double()->notNull(),
            'candidate_entity' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->addForeignKey("heading_rank_candidate_entity_fk", "{{%heading_rank}}",
            "candidate_entity", "{{%candidate_entity}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%heading_rank}}');
    }
}