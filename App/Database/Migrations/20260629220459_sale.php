<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Sale extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('sale');

        $table
            ->addColumn('customer_id',      'integer',   ['null' => true,  'default' => null])
            // null = venda balcão sem cadastro
            ->addColumn('user_id',          'integer',   ['null' => false])
            // vendedor/operador
            ->addColumn('service_order_id', 'integer',   ['null' => true,  'default' => null])
            // venda pode ou não estar vinculada a uma OS
            ->addColumn('numero',           'string',    ['limit' => 20, 'null' => false])
            // número amigável: VND-2026-00001
            ->addColumn('status',           'string',    ['limit' => 15, 'null' => false, 'default' => 'aberta'])
            // 'aberta' | 'finalizada' | 'cancelada'
            ->addColumn('forma_pagamento',  'string',    ['limit' => 20, 'null' => true,  'default' => null])
            // 'dinheiro' | 'pix' | 'cartao_credito' | 'cartao_debito' | 'transferencia'
            ->addColumn('desconto',         'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('total_servicos',   'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('total_produtos',   'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('total_geral',      'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // snapshot definitivo ao finalizar — não recalcular depois
            ->addColumn('observacoes',      'text',      ['null' => true,  'default' => null])
            ->addColumn('excluido',         'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',        'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('customer_id', 'customer', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sales_customer_id',
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sales_user_id',
            ])
            ->addForeignKey('service_order_id', 'service_orders', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sales_service_order_id',
            ])

            ->addIndex(['numero'],           ['unique' => true, 'name' => 'sales_numero_unique'])
            ->addIndex(['customer_id'],      ['name' => 'sales_customer_id_idx'])
            ->addIndex(['user_id'],          ['name' => 'sales_user_id_idx'])
            ->addIndex(['service_order_id'], ['name' => 'sales_service_order_id_idx'])
            ->addIndex(['status'],           ['name' => 'sales_status_idx'])
            ->addIndex(['excluido'],         ['name' => 'sales_excluido_idx'])

            ->create();
    }
}
