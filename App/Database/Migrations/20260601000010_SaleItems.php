<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SaleItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('sale_items');

        $table
            ->addColumn('sale_id',        'integer',   ['null' => false])
            ->addColumn('tipo',           'string',    ['limit' => 10, 'null' => false])
            // tipo: 'servico' | 'produto'
            ->addColumn('service_id',     'integer',   ['null' => true, 'default' => null])
            ->addColumn('product_id',     'integer',   ['null' => true, 'default' => null])
            ->addColumn('descricao',      'string',    ['limit' => 255, 'null' => false])
            // snapshot do nome — imutável após criação
            ->addColumn('quantidade',     'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false, 'default' => '1.000'])
            ->addColumn('preco_unitario', 'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // snapshot do preço no momento da venda — nunca atualizar
            ->addColumn('desconto_item',  'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('subtotal',       'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('criado_em',      'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('sale_id', 'sale', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sale_items_sale_id',
            ])
            ->addForeignKey('service_id', 'services', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sale_items_service_id',
            ])
            ->addForeignKey('product_id', 'products', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sale_items_product_id',
            ])

            ->addIndex(['sale_id'],    ['name' => 'sale_items_sale_id_idx'])
            ->addIndex(['tipo'],       ['name' => 'sale_items_tipo_idx'])
            ->addIndex(['product_id'], ['name' => 'sale_items_product_id_idx'])

            ->create();
    }
}
