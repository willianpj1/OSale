<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ServiceOrderItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('service_order_items');

        $table
            ->addColumn('service_order_id', 'integer', ['null' => false])
            ->addColumn('tipo',             'string',  ['limit' => 10, 'null' => false])
            // tipo: 'servico' | 'produto'
            ->addColumn('service_id',       'integer', ['null' => true, 'default' => null])
            // preenchido quando tipo = 'servico'
            ->addColumn('product_id',       'integer', ['null' => true, 'default' => null])
            // preenchido quando tipo = 'produto'
            ->addColumn('descricao',        'string',  ['limit' => 255, 'null' => false])
            // snapshot do nome no momento do registro — imutável
            ->addColumn('quantidade',       'decimal', ['precision' => 10, 'scale' => 3, 'null' => false, 'default' => '1.000'])
            ->addColumn('preco_unitario',   'decimal', ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // snapshot do preço no momento — nunca atualizar depois
            ->addColumn('subtotal',         'decimal', ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('criado_em',        'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('service_order_id', 'service_orders', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'fk_so_items_service_order_id',
            ])
            ->addForeignKey('service_id', 'services', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_so_items_service_id',
            ])
            ->addForeignKey('product_id', 'products', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_so_items_product_id',
            ])

            ->addIndex(['service_order_id'], ['name' => 'so_items_service_order_id_idx'])
            ->addIndex(['tipo'],             ['name' => 'so_items_tipo_idx'])
            ->addIndex(['product_id'],       ['name' => 'so_items_product_id_idx'])

            ->create();
    }
}
