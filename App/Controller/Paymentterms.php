<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use Doctrine\DBAL\ParameterType;
use DateTime;
use Exception;

final class PaymentTerms extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-paymentterms'), [
                'titulo' => 'Lista de condições de pagamento',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function details($request, $response, $args)
    {
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $paymentTerm = [];
        if (!is_null($id)) {
            $paymentTerm = DB::select('*')
                ->from('payment_terms')
                ->where('id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();
        }
        return $this->getTwig()
            ->render($response, $this->setView('paymentterms'), [
                'titulo'      => 'Detalhes da condição de pagamento',
                'id'          => $id,
                'action'      => $action,
                'paymentTerm' => $paymentTerm,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        if (empty($form['codigo'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione a forma de pagamento', 'id' => 0], 400);
        }
        if (empty($form['titulo'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o título', 'id' => 0], 400);
        }
        try {
            DB::connection()->insert('payment_terms', [
                'codigo'        => $form['codigo'],
                'titulo'        => $form['titulo'],
                'atalho'        => $form['atalho'] ?? null,
                'criado_em'     => $this->now(),
                'atualizado_em' => $this->now(),
            ]);
            $id = (int) DB::connection()->lastInsertId();
            if ($id === 0) {
                $id = (int) (DB::select('id')->from('payment_terms')
                    ->orderBy('id', 'DESC')->setMaxResults(1)->fetchOne() ?? 0);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Salvo com sucesso!', 'id' => $id], 201);
        } catch (Exception $e) {
            error_log('[PaymentTerms::insert] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do registro', 'id' => 0], 403);
        }
        if (empty($form['codigo'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione a forma de pagamento', 'id' => 0], 400);
        }
        if (empty($form['titulo'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o título', 'id' => 0], 400);
        }
        try {
            DB::connection()->update('payment_terms', [
                'codigo'        => $form['codigo'],
                'titulo'        => $form['titulo'],
                'atalho'        => $form['atalho'] ?? null,
                'atualizado_em' => $this->now(),
            ], ['id' => (int) $id]);
            return $this->json($response, ['status' => true, 'msg' => 'Alterado com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[PaymentTerms::update] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da condição de pagamento.', 'id' => 0], 403);
        }
        try {
            $emUso = (int) DB::select('COUNT(*)')->from('service_orders')
                ->where('id_pagamento = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchOne();
            if ($emUso > 0) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Esta condição já foi usada em ordens de serviço e não pode ser excluída.',
                    'id'     => 0,
                ], 422);
            }
            DB::connection()->delete('payment_terms', ['id' => $id]);
            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            error_log('[PaymentTerms::delete] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);
        $columns = [
            0 => 'codigo',
            1 => 'codigo',
            2 => 'titulo',
            3 => 'atalho',
            4 => 'criado_em',
            5 => 'atualizado_em',
        ];
        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column'] : 4;
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC'], true)
            ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];
        $formas = [
            '01' => 'Dinheiro',
            '03' => 'Cartão de Crédito',
            '04' => 'Cartão de Débito',
            '20' => 'PIX - Estático',
        ];
        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('payment_terms')->fetchOne();
            $query = DB::select('*')->from('payment_terms');
            if (!empty($term)) {
                $query->setParameter('term', '%' . $term . '%')
                    ->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('codigo ILIKE :term')
                    ->orWhere('titulo ILIKE :term')
                    ->orWhere('atalho ILIKE :term')
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }
            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();
            $items = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();
            $rows = array_map(fn($v) => [
                $v['codigo'],
                $formas[$v['codigo']] ?? $v['codigo'],
                $v['titulo'],
                $v['atalho'] ?? '—',
                (new DateTime($v['criado_em']))->format('d/m/Y H:i:s'),
                (new DateTime($v['atualizado_em']))->format('d/m/Y H:i:s'),
                "<td>
                    <a class='btn btn-sm btn-warning' href='/payment/detalhes/{$v['id']}'>
                        <i class='fa-solid fa-pen-to-square'></i> Editar
                    </a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'>
                        <i class='fa-solid fa-trash'></i> Excluir
                    </button>
                </td>",
            ], $items);
            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            error_log('[PaymentTerms::listingdata] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage()], 500);
        }
    }
    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}
