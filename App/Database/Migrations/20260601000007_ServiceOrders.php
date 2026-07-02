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
            // subtotal calculado a partir da soma dos itens (service_order_items)
            ->addColumn('desconto',            'decimal',   ['precision' => 5, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // % de desconto aplicado na negociação, preenchido só ao finalizar a OS
            ->addColumn('acrescimo',           'decimal',   ['precision' => 5, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            // % de acréscimo aplicado na negociação, preenchido só ao finalizar a OS
            ->addColumn('valor_liquido',       'decimal',   ['precision' => 10, 'scale' => 2, 'null' => true, 'default' => null])
            // snapshot do valor_total já com desconto/acréscimo aplicados, calculado ao concluir a OS
            ->addColumn('id_pagamento',        'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            // vínculo com payment_terms — só é preenchido quando a OS é finalizada
            ->addColumn('parcelas',            'integer',   ['null' => true, 'default' => 1])
            // quantidade de parcelas escolhida pelo cliente ao finalizar (ex: crédito em 7x) —
            // só é relevante quando id_pagamento aponta pra uma condição parcelável
            ->addColumn('aberto_em',           'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('concluido_em',        'timestamp', ['null' => true,  'default' => null])
            ->addColumn('excluido',            'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',           'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em',       'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('customer_id', 'customers', 'id', [
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
            ->addForeignKey('id_pagamento', 'payment_terms', 'id', [
                'delete'     => 'SET NULL',
                'update'     => 'CASCADE',
                'constraint' => 'fk_service_orders_id_pagamento',
            ])

            ->addIndex(['numero'],       ['unique' => true, 'name' => 'service_orders_numero_unique'])
            ->addIndex(['customer_id'],  ['name' => 'service_orders_customer_id_idx'])
            ->addIndex(['user_id'],      ['name' => 'service_orders_user_id_idx'])
            ->addIndex(['status'],       ['name' => 'service_orders_status_idx'])
            ->addIndex(['excluido'],     ['name' => 'service_orders_excluido_idx'])
            ->addIndex(['id_pagamento'], ['name' => 'service_orders_id_pagamento_idx'])

            ->create();
    }
}
