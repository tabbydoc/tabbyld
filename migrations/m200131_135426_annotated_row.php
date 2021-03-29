<?php

use yii\db\Migration;

/**
 * Class m200131_135426_annotated_row
 */
class m200131_135426_annotated_row extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql')
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';

        $this->createTable('{{%annotated_row}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'data' => $this->text(),
            'row_heading' => $this->text(),
            'column_heading' => $this->text(),
            'annotated_canonical_table' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->addForeignKey("annotated_row_annotated_canonical_table_fk",
            "{{%annotated_row}}", "annotated_canonical_table",
            "{{%annotated_canonical_table}}", "id", 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%annotated_row}}');
    }
}