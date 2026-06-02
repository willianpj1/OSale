<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526211306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de equipamentos vinculados aos clientes';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('equipment');
 
        $table->addColumn('id',            'bigint', ['autoincrement' => true]);
        $table->addColumn('cliente_id',    'bigint', ['notnull' => true]);
        $table->addColumn('nome',          'text',   ['notnull' => true]);
        $table->addColumn('marca',         'text',   ['notnull' => false, 'default' => null]);
        $table->addColumn('modelo',        'text',   ['notnull' => false, 'default' => null]);
        $table->addColumn('numero_serie',  'text',   ['notnull' => false, 'default' => null]);
        $table->addColumn('descricao',     'text',   ['notnull' => false, 'default' => null]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addIndex(['cliente_id'],   'equipment_cliente_id_idx');
        $table->addIndex(['numero_serie'], 'equipment_numero_serie_idx');
 
        $table->addForeignKeyConstraint(
            'clients',
            ['cliente_id'],
            ['id'],
            ['onDelete' => 'RESTRICT', 'onUpdate' => 'CASCADE'],
            'fk_equipment_cliente_id'
        );
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('equipment');
    }
}