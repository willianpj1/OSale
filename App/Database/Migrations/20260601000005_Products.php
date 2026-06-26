<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Products extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('products');

        $table
            ->addColumn('supplier_id',    'integer',   ['null' => true,  'default' => null])
            ->addColumn('nome',           'string',    ['limit' => 200,  'null' => false])
            ->addColumn('codigo_barra',   'string',    ['limit' => 50,   'null' => true,  'default' => null])
            ->addColumn('unidade',        'string',    ['limit' => 5,    'null' => false, 'default' => 'un'])
            // unidade: 'un' | 'kg' | 'm' | 'l'
            ->addColumn('descricao',      'text',      ['null' => true,  'default' => null])
            ->addColumn('preco_compra',   'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('margem_lucro',   'decimal',   ['precision' => 5,  'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('total_imposto',  'decimal',   ['precision' => 5,  'scale' => 2, 'null' => false, 'default' => '0.00'])
            // percentual: ex 15.00 = 15%
            ->addColumn('preco_venda',    'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // DESNORMALIZAÇÃO INTENCIONAL — atualizado via stock_movements (fase final)
            ->addColumn('estoque_atual',  'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false, 'default' => '0.000'])
            ->addColumn('estoque_minimo', 'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false, 'default' => '0.000'])
            ->addColumn('ativo',          'boolean',   ['null' => false, 'default' => true])
            ->addColumn('excluido',       'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',      'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em',  'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('supplier_id', 'suppliers', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_products_supplier_id',
            ])

            ->addIndex(['codigo_barra'], ['unique' => true, 'name' => 'products_codigo_barra_unique'])
            ->addIndex(['nome'],         ['name' => 'products_nome_idx'])
            ->addIndex(['supplier_id'],  ['name' => 'products_supplier_id_idx'])
            ->addIndex(['excluido'],     ['name' => 'products_excluido_idx'])

            ->create();
    }
}
