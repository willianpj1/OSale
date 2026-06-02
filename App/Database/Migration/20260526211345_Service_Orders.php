<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526211345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de ordens de serviço';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('service_orders');
 
        $table->addColumn('id',             'bigint',   ['autoincrement' => true]);
        $table->addColumn('cliente_id',     'bigint',   ['notnull' => true]);
        $table->addColumn('equipamento_id', 'bigint',   ['notnull' => false, 'default' => null]);
        // NULL permitido: OS pode ser de serviço sem equipamento vinculado
        $table->addColumn('tecnico_id',     'bigint',   ['notnull' => false, 'default' => null]);
        // NULL permitido: técnico pode ser atribuído depois
        $table->addColumn('criado_por',     'bigint',   ['notnull' => true]);
        $table->addColumn('status',         'text',     ['notnull' => true, 'default' => 'aberta']);
        $table->addColumn('prioridade',     'text',     ['notnull' => true, 'default' => 'normal']);
        $table->addColumn('titulo',         'text',     ['notnull' => true]);
        $table->addColumn('descricao',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('observacoes',    'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('aberto_em',      'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('concluido_em',   'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('criado_em',      'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em',  'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addIndex(['cliente_id'],     'so_cliente_id_idx');
        $table->addIndex(['tecnico_id'],     'so_tecnico_id_idx');
        $table->addIndex(['equipamento_id'], 'so_equipamento_id_idx');
        $table->addIndex(['criado_por'],     'so_criado_por_idx');
        $table->addIndex(['status'],         'so_status_idx');
 
        $table->addForeignKeyConstraint(
            'clients',
            ['cliente_id'],
            ['id'],
            ['onDelete' => 'RESTRICT', 'onUpdate' => 'CASCADE'],
            'fk_so_cliente_id'
        );
 
        $table->addForeignKeyConstraint(
            'equipment',
            ['equipamento_id'],
            ['id'],
            ['onDelete' => 'SET NULL', 'onUpdate' => 'CASCADE'],
            'fk_so_equipamento_id'
        );
 
        $table->addForeignKeyConstraint(
            'users',
            ['tecnico_id'],
            ['id'],
            ['onDelete' => 'SET NULL', 'onUpdate' => 'CASCADE'],
            'fk_so_tecnico_id'
        );
 
        $table->addForeignKeyConstraint(
            'users',
            ['criado_por'],
            ['id'],
            ['onDelete' => 'RESTRICT', 'onUpdate' => 'CASCADE'],
            'fk_so_criado_por'
        );
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('service_orders');
    }
}