<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Services extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('services');

        $table
            ->addColumn('nome',           'string',    ['limit' => 200, 'null' => false])
            ->addColumn('descricao',      'text',      ['null' => true,  'default' => null])
            ->addColumn('preco',          'decimal',   ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => '0.00'])
            ->addColumn('tempo_estimado', 'string',    ['limit' => 50, 'null' => true, 'default' => null])
            // ex: "30 minutos", "1-2 dias" — texto livre
            ->addColumn('ativo',          'boolean',   ['null' => false, 'default' => true])
            ->addColumn('excluido',       'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',      'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em',  'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['nome'],     ['name' => 'services_nome_idx'])
            ->addIndex(['excluido'], ['name' => 'services_excluido_idx'])

            ->create();
    }
}
