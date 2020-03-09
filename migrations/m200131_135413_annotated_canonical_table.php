<?php

use yii\db\Migration;

/**
 * Class m200131_135413_annotated_canonical_table
 */
class m200131_135413_annotated_canonical_table extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%annotated_canonical_table}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'total_element_number' => $this->integer()->notNull(),
            'annotated_element_number' => $this->integer()->notNull(),
            'correctly_annotated_element_number' => $this->integer()->notNull(),
            'accuracy' => $this->double()->notNull(),
            'precision' => $this->double()->notNull(),
            'recall' => $this->double()->notNull(),
            'f_score' => $this->double()->notNull(),
            'runtime' => $this->double()->notNull(),
            'description' => $this->string(),
            'annotated_dataset' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_annotated_canonical_table_name',
            '{{%annotated_canonical_table}}', 'name');

        $this->addForeignKey("annotated_canonical_table_annotated_dataset_fk",
            "{{%annotated_canonical_table}}", "annotated_dataset",
            "{{%annotated_dataset}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%annotated_canonical_table}}');
    }
}