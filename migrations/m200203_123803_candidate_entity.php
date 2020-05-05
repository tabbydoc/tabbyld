<?php

use yii\db\Migration;

/**
 * Class m200203_123803_candidate_entity
 */
class m200203_123803_candidate_entity extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%candidate_entity}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'entity' => $this->text()->notNull(),
            'levenshtein_distance' => $this->double(),
            'aggregated_rank' => $this->double(),
            'cell_value' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_candidate_entity_entity', '{{%candidate_entity}}', 'entity');

        $this->addForeignKey("candidate_entity_cell_value_fk", "{{%candidate_entity}}",
            "cell_value", "{{%cell_value}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%candidate_entity}}');
    }
}