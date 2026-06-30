<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Users extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');

        $table
            ->addColumn('nome',          'string',    ['limit' => 200, 'null' => false])
            ->addColumn('sobrenome',     'string',    ['limit' => 200, 'null' => false])
            ->addColumn('email',         'string',    ['limit' => 200, 'null' => false])
            ->addColumn('tipo',          'string',    ['limit' => 10,  'null' => false, 'default' => 'USER'])
            ->addColumn('cpf_cnpj',      'string',    ['limit' => 18,  'null' => true,  'default' => null])
            ->addColumn('rg_ie',         'string',    ['limit' => 30,  'null' => true,  'default' => null])
            ->addColumn('senha',         'string',    ['limit' => 255, 'null' => true,  'default' => null])
            ->addColumn('observacoes',   'text',      ['null' => true,  'default' => null])
            ->addColumn('ativo',         'boolean',   ['null' => false, 'default' => true])
            ->addColumn('excluido',      'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',     'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['email'],    ['name' => 'users_email_idx', 'unique' => true])
            ->addIndex(['cpf_cnpj'], ['name' => 'users_cpf_cnpj_idx', 'unique' => true])
            ->addIndex(['nome'],     ['name' => 'users_nome_idx'])
            ->addIndex(['excluido'], ['name' => 'users_excluido_idx'])

            ->create();
    }
}