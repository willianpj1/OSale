<?php

declare(strict_types=1);

namespace App\Controller;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Company extends Base
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? \App\Database\DB::connection();
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-company'), [
                'titulo' => 'Lista de empresas',    
            ])  
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id      = $args['id'] ?? null;
        $action  = ($id === null) ? 'c' : 'e';
        $company = [];

        if (!is_null($id)) {
            $qb = \App\Database\DB::select('*')->from('company');

            $company = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('company'), [
                'titulo'  => 'Detalhes da empresa',
                'id'      => $id,
                'action'  => $action,
                'company' => $company,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function insert(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = $request->getParsedBody();
        $nome = trim($form['nome'] ?? '');

        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        $now = (new DateTime())->format('Y-m-d H:i:s');

        // Garantindo tipos booleanos reais para o Postgres
        $ativo    = isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true;
        $excluido = false;

        $fieldsAndValues = [
            'nome'          => $nome,
            'cnpj'          => $form['cnpj']     ?? null,
            'email'         => $form['email']    ?? null,
            'telefone'      => $form['telefone'] ?? null,
            'ativo'         => $ativo,
            'excluido'      => $excluido,
            'criado_em'     => $now,
            'atualizado_em' => $now,
        ];

        try {
            // No Postgres, passamos os tipos explicitamente para evitar o erro de string vazia ""
            $this->db->insert('company', $fieldsAndValues, [
                'nome'          => ParameterType::STRING,
                'cnpj'          => ParameterType::STRING,
                'email'         => ParameterType::STRING,
                'telefone'      => ParameterType::STRING,
                'ativo'         => ParameterType::BOOLEAN,
                'excluido'      => ParameterType::BOOLEAN,
                'criado_em'     => ParameterType::STRING,
                'atualizado_em' => ParameterType::STRING,
            ]);

            $id = $this->db->lastInsertId();

            return $this->json($response, ['status' => true, 'msg' => 'Empresa salva com sucesso!', 'id' => (int) $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }

        $nome = trim($form['nome'] ?? '');

        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        $fieldsAndValues = [
            'nome'          => $nome,
            'cnpj'          => $form['cnpj']     ?? null,
            'email'         => $form['email']    ?? null,
            'telefone'      => $form['telefone'] ?? null,
            'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        try {
            $isUpdated = $this->db->update(
                'company', 
                $fieldsAndValues, 
                ['id' => $id, 'excluido' => false],
                [
                    'ativo' => ParameterType::BOOLEAN,
                    'id'    => ParameterType::INTEGER,
                    'excluido' => ParameterType::BOOLEAN
                ]
            );

            if (!$isUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Empresa não encontrada', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Empresa atualizada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da empresa', 'id' => 0], 403);
        }

        try {
            $isDeleted = $this->db->update(
                'company',
                ['excluido' => true, 'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s')],
                ['id' => $id],
                ['excluido' => ParameterType::BOOLEAN, 'id' => ParameterType::INTEGER]
            );

            if (!$isDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Empresa não encontrada', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Empresa excluída com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function listingdata(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'id',
            1 => 'nome',
            2 => 'cnpj',
            3 => 'email',
            4 => 'telefone',
            5 => 'ativo',
            6 => 'criado_em',
            7 => 'atualizado_em',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('company')
                ->where('excluido = false')
                ->fetchOne();

            $query = \App\Database\DB::select('*')->from('company')->where('excluido = false');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->andWhere(
                    $query->expr()->or(
                        'CAST(id AS TEXT) ILIKE :term',
                        'nome ILIKE :term',
                        'cnpj ILIKE :term',
                        'email ILIKE :term',
                        'telefone ILIKE :term'
                    )
                );
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $companies = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];
            foreach ($companies as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['cnpj']     ?? '',
                    $value['email']    ?? '',
                    $value['telefone'] ?? '',
                    ($value['ativo'] == true) ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/empresa/detalhes/" . $value['id'] . "'><i class='fa-solid fa-pen-to-square'></i> Editar</a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'><i class='fa-solid fa-trash'></i> Excluir</button>
                    </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}