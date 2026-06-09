<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526212144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de movimentações de estoque (entradas, saídas e ajustes)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('stockmovements');

        $table->addColumn('id',               'bigint',   ['autoincrement' => true]);
        $table->addColumn('item_estoque_id',  'bigint',   ['notnull' => true]);
        $table->addColumn('usuario_id',       'bigint',   ['notnull' => true]);
        $table->addColumn('ordem_servico_id', 'bigint',   ['notnull' => false, 'default' => null]);      
        // Preenchido quando a saída é originada de uma OS.
        $table->addColumn('tipo',             'text',     ['notnull' => true]);
        // Valores: 'entrada' | 'saida' | 'ajuste'
        $table->addColumn('quantidade',       'decimal',  ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        // Sempre positivo — o campo 'tipo' define a direção do movimento
        $table->addColumn('motivo',           'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('criado_em',        'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['item_estoque_id'],  'sm_item_estoque_id_idx');
        $table->addIndex(['usuario_id'],       'sm_usuario_id_idx');
        $table->addIndex(['ordem_servico_id'], 'sm_ordem_servico_id_idx');
        $table->addIndex(['tipo'],             'sm_tipo_idx');

        $table->addForeignKeyConstraint(
            'products',
            ['item_estoque_id'],
            ['id'],
            ['onDelete' => 'RESTRICT', 'onUpdate' => 'CASCADE'],
            'fk_sm_item_estoque_id'
        );

        $table->addForeignKeyConstraint(
            'users',
            ['usuario_id'],
            ['id'],
            ['onDelete' => 'RESTRICT', 'onUpdate' => 'CASCADE'],
            'fk_sm_usuario_id'
        );

        $table->addForeignKeyConstraint(
            'service_orders',
            ['ordem_servico_id'],
            ['id'],
            ['onDelete' => 'SET NULL', 'onUpdate' => 'CASCADE'],
            'fk_sm_ordem_servico_id'
        );

    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('stock_movements');
    }
}
