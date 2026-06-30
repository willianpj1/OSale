<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ServiceOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('service_orders');

        $table
            ->addColumn('customer_id',         'integer',   ['null' => false])
            ->addColumn('user_id',             'integer',   ['null' => true,  'default' => null])
            // técnico responsável — pode ser atribuído depois
            ->addColumn('criado_por',          'integer',   ['null' => false])
            // usuário que abriu a OS
            ->addColumn('numero',              'string',    ['limit' => 20, 'null' => false])
            // número amigável gerado pelo sistema: OS-2026-00001
            ->addColumn('status',              'string',    ['limit' => 20, 'null' => false, 'default' => 'aberta'])
            // 'aberta' | 'em_andamento' | 'aguardando_peca' | 'concluida' | 'cancelada'
            ->addColumn('prioridade',          'string',    ['limit' => 10, 'null' => false, 'default' => 'normal'])
            // 'baixa' | 'normal' | 'alta' | 'urgente'
            ->addColumn('equipamento',         'string',    ['limit' => 200, 'null' => true, 'default' => null])
            // ex: "Notebook Dell Inspiron 15"
            ->addColumn('marca',               'string',    ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('modelo',              'string',    ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('numero_serie',        'string',    ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('defeito_relatado',    'text',      ['null' => true, 'default' => null])
            ->addColumn('defeito_constatado',  'text',      ['null' => true, 'default' => null])
            ->addColumn('observacoes',         'text',      ['null' => true, 'default' => null])
            ->addColumn('valor_total',         'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // snapshot calculado ao concluir a OS
            ->addColumn('aberto_em',           'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('concluido_em',        'timestamp', ['null' => true,  'default' => null])
            ->addColumn('excluido',            'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',           'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em',       'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('customer_id', 'customer', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_service_orders_customer_id',
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_service_orders_user_id',
            ])
            ->addForeignKey('criado_por', 'users', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_service_orders_criado_por',
            ])

            ->addIndex(['numero'],      ['unique' => true, 'name' => 'service_orders_numero_unique'])
            ->addIndex(['customer_id'], ['name' => 'service_orders_customer_id_idx'])
            ->addIndex(['user_id'],     ['name' => 'service_orders_user_id_idx'])
            ->addIndex(['status'],      ['name' => 'service_orders_status_idx'])
            ->addIndex(['excluido'],    ['name' => 'service_orders_excluido_idx'])

            ->create();
    }
}
