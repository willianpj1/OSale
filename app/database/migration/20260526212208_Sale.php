<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526212208 extends AbstractMigration
{
     public function getDescription(): string
    {
        return 'Cria tabela de fechamento/venda das ordens de serviço';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('service_order_closings');
 
        $table->addColumn('id',               'bigint',   ['autoincrement' => true]);
        $table->addColumn('ordem_servico_id', 'bigint',   ['notnull' => true]);
        // UNIQUE: uma OS só pode ter um fechamento
 
        $table->addColumn('forma_pagamento',  'text',     ['notnull' => true]);
        // Valores: 'dinheiro' | 'pix' | 'cartao_credito' | 'cartao_debito' | 'transferencia'
 
        // total_pecas e total_servicos são opcionais para breakdown.
        // Calculados a partir de service_order_items no momento do fechamento
        // e gravados aqui como snapshot histórico (assim como preco_unitario nos itens).
        $table->addColumn('total_pecas',      'decimal',  ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('total_servicos',   'decimal',  ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('total_geral',      'decimal',  ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        // Snapshot do total no momento do fechamento.
        // Não recalcule depois — este valor é o registro oficial da venda.
 
        $table->addColumn('observacoes',      'text',     ['notnull' => false, 'default' => null]);
        $table->addColumn('criado_em',        'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em',    'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['ordem_servico_id'], 'soc_ordem_servico_id_unique');
        // Garante que uma OS só pode ser fechada uma vez
 
        $table->addForeignKeyConstraint(
            'service_orders',
            ['ordem_servico_id'],
            ['id'],
            ['onDelete' => 'RESTRICT', 'onUpdate' => 'CASCADE'],
            'fk_soc_ordem_servico_id'
        );
 
        $this->addSql("ALTER TABLE service_order_closings ADD CONSTRAINT soc_forma_pagamento_check
            CHECK (forma_pagamento IN ('dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'transferencia'))");
 
        $this->addSql('ALTER TABLE service_order_closings ADD CONSTRAINT soc_total_pecas_check
            CHECK (total_pecas >= 0)');
 
        $this->addSql('ALTER TABLE service_order_closings ADD CONSTRAINT soc_total_servicos_check
            CHECK (total_servicos >= 0)');
 
        $this->addSql('ALTER TABLE service_order_closings ADD CONSTRAINT soc_total_geral_check
            CHECK (total_geral >= 0)');
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('service_order_closings');
    }
}