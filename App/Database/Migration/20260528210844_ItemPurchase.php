<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528210844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de itens de compra vinculados a uma compra e a um produto';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('item_purchase');

        $table->addColumn('id',               'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_compra',        'bigint',  ['notnull' => false, 'default' => null]);
        $table->addColumn('id_produto',       'bigint',  ['notnull' => false, 'default' => null]);
        $table->addColumn('quantidade',       'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => false, 'default' => null]);
        $table->addColumn('total_bruto',      'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => false, 'default' => null]);
        $table->addColumn('total_liquido',    'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => false, 'default' => null]);
        // Valor a ser pago pelo produto (após descontos e acréscimos)
        $table->addColumn('desconto',         'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => false, 'default' => null]);
        $table->addColumn('acrescimo',        'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => false, 'default' => null]);
        $table->addColumn('nome',             'text',    ['notnull' => false, 'default' => null]);
        // Snapshot do nome do produto no momento da compra
        $table->addColumn('data_cadastro',    'datetime', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao', 'datetime', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['id_compra'],  'item_purchase_id_compra_idx');
        $table->addIndex(['id_produto'], 'item_purchase_id_produto_idx');

        $table->addForeignKeyConstraint(
            'purchase',
            ['id_compra'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION'],
            'fk_item_purchase_id_compra'
        );

        $table->addForeignKeyConstraint(
            'products',
            ['id_produto'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION'],
            'fk_item_purchase_id_produto'
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('item_purchase');
    }
}
