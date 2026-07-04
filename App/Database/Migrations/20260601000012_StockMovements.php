<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StockMovements extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('stock_movements', ['id' => false, 'primary_key' => ['id']]);

        $table
            ->addColumn('id', 'biginteger', ['identity' => true, 'null' => false])
            ->addColumn('product_id',            'integer',   ['null' => false])
            ->addColumn('service_order_item_id', 'integer',   ['null' => true, 'default' => null])
            // preenchido quando origem = VENDA/CANCELAMENTO_VENDA
            ->addColumn('quantidade',            'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false])
            // sempre positiva — o sinal é dado pela coluna 'tipo'
            ->addColumn('estoque_anterior',      'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false])
            ->addColumn('estoque_posterior',     'decimal',   ['precision' => 10, 'scale' => 3, 'null' => false])
            ->addColumn('observacao',            'text',      ['null' => true, 'default' => null])
            ->addColumn('criado_por',            'integer',   ['null' => true, 'default' => null])
            ->addColumn('criado_em',             'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('product_id', 'products', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_stock_movements_product_id',
            ])
            ->addForeignKey('service_order_item_id', 'service_order_items', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_stock_movements_soi_id',
            ])
            ->addForeignKey('criado_por', 'users', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_stock_movements_criado_por',
            ])

            ->addIndex(['product_id'],            ['name' => 'stock_movements_product_id_idx'])
            ->addIndex(['service_order_item_id'], ['name' => 'stock_movements_soi_id_idx'])
            ->addIndex(['criado_em'],              ['name' => 'stock_movements_criado_em_idx'])

            ->create();

        // ENUMs criados na migration CustomTypes — adicionadas via SQL puro
        // porque Phinx não tem suporte nativo a tipo ENUM do Postgres.
        $this->execute('ALTER TABLE stock_movements ADD COLUMN tipo stock_movement_direction NOT NULL');
        $this->execute('ALTER TABLE stock_movements ADD COLUMN origem stock_movement_origin NOT NULL');
        $this->execute('CREATE INDEX stock_movements_origem_idx ON stock_movements (origem)');
    }
}
