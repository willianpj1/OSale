<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526211246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela de produtos da loja (venda direta, independente das OS)';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('products');
 
        $table->addColumn('id',                'bigint',  ['autoincrement' => true]);
        $table->addColumn('categoria_id',      'bigint',  ['notnull' => true]);
        $table->addColumn('nome',              'text',    ['notnull' => true]);
        // Ex: 'SSD Kingston 480GB', 'Mouse Logitech MX Master 3'
        $table->addColumn('marca',             'text',    ['notnull' => false, 'default' => null]);
        $table->addColumn('modelo',            'text',    ['notnull' => false, 'default' => null]);
        $table->addColumn('descricao',         'text',    ['notnull' => false, 'default' => null]);
        $table->addColumn('sku',               'text',    ['notnull' => false, 'default' => null]);
        // Código interno de controle
 
        // DESNORMALIZAÇÃO INTENCIONAL (cache controlado — ver 3FN):
        // Nunca atualize diretamente. Sempre insira em product_movements
        // e sincronize este campo na mesma transação.
        $table->addColumn('quantidade',        'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('quantidade_minima', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
        // Alerta de estoque baixo quando quantidade <= quantidade_minima
 
        $table->addColumn('custo_unitario',    'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('preco_venda',       'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
 
        $table->addColumn('ativo',             'boolean', ['default' => true]);
        $table->addColumn('criado_em',         'datetime',['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em',     'datetime',['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['sku'],       'products_sku_unique');
        $table->addIndex(['categoria_id'],    'products_categoria_id_idx');
        $table->addIndex(['nome'],            'products_nome_idx');
        $table->addIndex(['marca'],           'products_marca_idx');
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('products');
    }
}