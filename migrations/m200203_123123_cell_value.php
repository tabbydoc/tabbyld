<?php

use yii\db\Migration;

/**
 * Class m200203_123123_cell_value
 */
class m200203_123123_cell_value extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%cell_value}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'execution_time' => $this->double()->notNull(),
            'type' => $this->smallInteger()->notNull()->defaultValue(0),

            'annotated_canonical_table' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_cell_value_name', '{{%cell_value}}', 'name');

        $this->addForeignKey("cell_value_annotated_canonical_table_fk",
            "{{%cell_value}}", "annotated_canonical_table",
            "{{%annotated_canonical_table}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%cell_value}}');
    }
}