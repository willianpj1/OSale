<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526212124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de itens do estoque (peças e produtos)';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('stock_items');
 
        $table->addColumn('id',                 'bigint',   ['autoincrement' => true]);
        $table->addColumn('nome',               'text',     ['notnull' => true]);
        $table->addColumn('sku',                'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('descricao',          'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('unidade',            'text',     ['notnull' => true,  'default' => 'unidade']);
 
        // DESNORMALIZAÇÃO INTENCIONAL (cache controlado — ver 3FN):
        // Nunca atualize diretamente. Sempre insira em stock_movements
        // e sincronize este campo na mesma transação.
        $table->addColumn('quantidade',         'decimal',  ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('quantidade_minima',  'decimal',  ['precision' => 10, 'scale' => 2, 'default' => 0]);
 
        $table->addColumn('custo_unitario',     'decimal',  ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('preco_venda',        'decimal',  ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('ativo',              'boolean',  ['default' => true]);
        $table->addColumn('criado_em',          'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em',      'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['sku'], 'stock_items_sku_unique');
        $table->addIndex(['nome'],      'stock_items_nome_idx');
 
        $this->addSql("ALTER TABLE stock_items ADD CONSTRAINT stock_items_unidade_check
            CHECK (unidade IN ('unidade', 'metro', 'kg', 'litro'))");
 
        $this->addSql('ALTER TABLE stock_items ADD CONSTRAINT stock_items_quantidade_check
            CHECK (quantidade >= 0)');
 
        $this->addSql('ALTER TABLE stock_items ADD CONSTRAINT stock_items_quantidade_minima_check
            CHECK (quantidade_minima >= 0)');
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('stock_items');
    }
}