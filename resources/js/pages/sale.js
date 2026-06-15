import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action    = document.getElementById('action');
const Id        = document.getElementById('id');
const BtnSave   = document.getElementById('btn-save');

// ── Salvar cabeçalho da venda ─────────────────────────────────────────────────

async function applyChanges() {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Por favor, corrija os erros no formulário antes de salvar.',
            timer: 3000,
            timerProgressBar: true,
        });
        $('button').prop('disabled', false);
        return;
    }

    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/venda/inserir')
            : await requests.setForm('form').post('/venda/atualizar');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar a venda.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        if (Action.value !== 'e') {
            // Criação: atualiza URL e estado sem recarregar
            const redirectUrl = `${window.location.origin}/venda/detalhes/${response.id}`;
            Action.value = 'e';
            Id.value     = response.id;
            window.history.pushState({}, '', redirectUrl);
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Venda criada com sucesso!',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                // Recarrega para exibir a seção de itens
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Venda atualizada com sucesso!',
                timer: 3000,
                timerProgressBar: true,
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        $('button').prop('disabled', false);
    }
}

BtnSave.addEventListener('click', async () => {
    await applyChanges();
});

// ── Finalizar venda ───────────────────────────────────────────────────────────

const BtnFinalize = document.getElementById('btn-finalize');

if (BtnFinalize) {
    BtnFinalize.addEventListener('click', () => {
        // Pre-preenche com o valor já salvo no select do cabeçalho
        const formaPagamento = document.getElementById('forma_pagamento')?.value ?? '';
        const finalizeSelect = document.getElementById('finalize-forma-pagamento');
        if (formaPagamento) finalizeSelect.value = formaPagamento;

        new bootstrap.Modal(document.getElementById('modal-finalize')).show();
    });

    document.getElementById('btn-confirm-finalize').addEventListener('click', async () => {
        const forma = document.getElementById('finalize-forma-pagamento').value;
        if (!forma) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Selecione a forma de pagamento para finalizar.',
                timer: 2500,
                timerProgressBar: true,
            });
            return;
        }

        $('button').prop('disabled', true);
        const requests = new Requests();
        try {
            const response = await requests.post('/venda/finalizar', {
                id:              Id.value,
                forma_pagamento: forma,
            });

            bootstrap.Modal.getInstance(document.getElementById('modal-finalize')).hide();

            if (!response.status) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.msg || 'Não foi possível finalizar a venda.',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Finalizada!',
                text: response.msg || 'Venda finalizada com sucesso.',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.href = '/venda/lista';
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: `Restrição: ${error.message}`,
                timer: 3000,
                timerProgressBar: true,
            });
        } finally {
            $('button').prop('disabled', false);
        }
    });
}

// ── Itens ─────────────────────────────────────────────────────────────────────

const BtnAddItem = document.getElementById('btn-add-item');
let   Select2Item = null;

if (BtnAddItem) {

    // Select2 dinâmico para busca de serviços/produtos
    function initSelect2(tipo) {
        const label = document.getElementById('item-search-label');
        label.textContent = tipo === 'servico' ? 'Serviço' : 'Produto';

        if (Select2Item) $('#item-search').select2('destroy');

        const url = tipo === 'servico' ? '/venda/buscar/servicos' : '/venda/buscar/produtos';

        Select2Item = $('#item-search').select2({
            dropdownParent: $('#modal-item'),
            placeholder:    'Digite para buscar...',
            minimumInputLength: 1,
            ajax: {
                url,
                dataType: 'json',
                delay:    300,
                data:     params => ({ q: params.term }),
                processResults: data => ({
                    results: data.results.map(r => ({
                        id:    r.id,
                        text:  r.nome,
                        preco: r.preco ?? r.preco_venda ?? 0,
                    })),
                }),
            },
        });

        // Ao selecionar, preenche descrição e preço
        $('#item-search').off('select2:select').on('select2:select', function (e) {
            const data = e.params.data;
            document.getElementById('item-descricao').value = data.text;
            document.getElementById('item-preco').value     = parseFloat(data.preco).toFixed(2);
        });
    }

    // Inicializa com o tipo padrão ao abrir o modal
    BtnAddItem.addEventListener('click', () => {
        // Limpa campos
        document.getElementById('item-descricao').value      = '';
        document.getElementById('item-preco').value          = '0';
        document.getElementById('item-quantidade').value     = '1';
        document.getElementById('item-desconto-item').value  = '0';

        initSelect2(document.getElementById('item-tipo').value);
        new bootstrap.Modal(document.getElementById('modal-item')).show();
    });

    // Troca tipo no modal
    document.getElementById('item-tipo').addEventListener('change', function () {
        initSelect2(this.value);
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-preco').value     = '0';
    });

    // Salvar item
    document.getElementById('btn-save-item').addEventListener('click', async () => {
        const tipo       = document.getElementById('item-tipo').value;
        const descricao  = document.getElementById('item-descricao').value.trim();
        const quantidade = parseFloat(document.getElementById('item-quantidade').value) || 1;
        const preco      = parseFloat(document.getElementById('item-preco').value)      || 0;
        const desconto   = parseFloat(document.getElementById('item-desconto-item').value) || 0;
        const selected   = $('#item-search').select2('data')[0];
        const refId      = selected?.id ?? null;

        if (!descricao) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'O campo descrição é obrigatório.',
                timer: 2500,
                timerProgressBar: true,
            });
            return;
        }

        $('button').prop('disabled', true);
        const requests = new Requests();
        try {
            const saleId   = Id.value;
            const payload  = {
                tipo,
                descricao,
                quantidade,
                preco_unitario: preco,
                desconto_item:  desconto,
            };
            if (tipo === 'servico' && refId) payload.service_id = refId;
            if (tipo === 'produto'  && refId) payload.product_id = refId;

            const response = await requests.post(`/venda/${saleId}/item`, payload);

            if (!response.status) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.msg || 'Erro ao adicionar item.',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-item')).hide();
            window.location.reload();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: `Restrição: ${error.message}`,
                timer: 3000,
                timerProgressBar: true,
            });
        } finally {
            $('button').prop('disabled', false);
        }
    });
}

// ── Excluir item ──────────────────────────────────────────────────────────────

window.deleteItem = async function (itemId) {
    const confirm = await Swal.fire({
        icon:              'warning',
        title:             'Excluir item?',
        text:              'Esta ação não pode ser desfeita.',
        showCancelButton:  true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText:  'Cancelar',
        confirmButtonColor:'#dc3545',
    });

    if (!confirm.isConfirmed) return;

    $('button').prop('disabled', true);
    const requests = new Requests();
    try {
        const saleId   = Id.value;
        const response = await requests.post(`/venda/${saleId}/item/${itemId}`, { _method: 'DELETE' });

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Erro ao excluir item.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        window.location.reload();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        $('button').prop('disabled', false);
    }
};