<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Customers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('customers');

        $table
            ->addColumn('nome',          'string',    ['limit' => 200, 'null' => false])
            ->addColumn('tipo',          'string',    ['limit' => 10,  'null' => false, 'default' => 'fisica'])
            ->addColumn('cpf_cnpj',      'string',    ['limit' => 18,  'null' => true,  'default' => null])
            ->addColumn('rg_ie',         'string',    ['limit' => 30,  'null' => true,  'default' => null])
            ->addColumn('observacoes',   'text',      ['null' => true,  'default' => null])
            ->addColumn('ativo',         'boolean',   ['null' => false, 'default' => true])
            ->addColumn('excluido',      'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',     'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['cpf_cnpj'], ['name' => 'customers_cpf_cnpj_idx'])
            ->addIndex(['nome'],     ['name' => 'customers_nome_idx'])
            ->addIndex(['excluido'], ['name' => 'customers_excluido_idx'])

            ->create();
    }
}
