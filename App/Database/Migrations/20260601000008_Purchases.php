<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Purchases extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('purchases');

        $table
            ->addColumn('supplier_id',      'integer',   ['null' => false])
            ->addColumn('criado_por',       'integer',   ['null' => false])
            ->addColumn('numero',           'string',    ['limit' => 20, 'null' => false])
            // ex: COMPRA-2026-00001
            ->addColumn('nota_pedido', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('status',           'string',    ['limit' => 20, 'null' => false, 'default' => 'pendente'])
            // 'pendente' | 'recebida' | 'cancelada'
            ->addColumn('valor_total',      'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // subtotal calculado a partir da soma dos itens (purchase_items)
            ->addColumn('observacoes',      'text',      ['null' => true, 'default' => null])
            ->addColumn('recebido_em',      'timestamp', ['null' => true, 'default' => null])
            ->addColumn('excluido',         'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',        'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em',    'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('supplier_id', 'suppliers', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_purchases_supplier_id',
            ])
            ->addForeignKey('criado_por', 'users', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_purchases_criado_por',
            ])

            ->addIndex(['numero'],      ['unique' => true, 'name' => 'purchases_numero_unique'])
            ->addIndex(['supplier_id'], ['name' => 'purchases_supplier_id_idx'])
            ->addIndex(['status'],      ['name' => 'purchases_status_idx'])
            ->addIndex(['excluido'],    ['name' => 'purchases_excluido_idx'])

            ->create();
    }
}
