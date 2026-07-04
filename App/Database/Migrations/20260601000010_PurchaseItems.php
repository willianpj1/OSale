<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PurchaseItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('purchase_items');

        $table
            ->addColumn('purchase_id',      'integer',   ['null' => false])
            ->addColumn('product_id',       'integer',   ['null' => false])
            ->addColumn('quantidade',       'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false])
            ->addColumn('preco_unitario',   'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false])
            ->addColumn('subtotal',         'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false])
            ->addColumn('criado_em',        'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('purchase_id', 'purchases', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'fk_purchase_items_purchase_id',
            ])
            ->addForeignKey('product_id', 'products', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_purchase_items_product_id',
            ])

            ->addIndex(['purchase_id'], ['name' => 'purchase_items_purchase_id_idx'])
            ->addIndex(['product_id'],  ['name' => 'purchase_items_product_id_idx'])

            ->create();
    }
}
