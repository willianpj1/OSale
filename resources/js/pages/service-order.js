import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action  = document.getElementById('action');
const Id      = document.getElementById('id');
const BtnSave = document.getElementById('insert');

// ── Salvar OS ─────────────────────────────────────────────────────────────────

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
            ? await requests.setForm('form').post('/os/inserir')
            : await requests.setForm('form').post('/os/atualizar');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar a OS.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        if (Action.value !== 'e') {
            // Criação: atualiza URL sem recarregar, depois recarrega para exibir itens
            const redirectUrl = `${window.location.origin}/os/detalhes/${response.id}`;
            Action.value = 'e';
            Id.value     = response.id;
            window.history.pushState({}, '', redirectUrl);
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'OS aberta com sucesso!',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'OS atualizada com sucesso!',
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

// ── Itens ─────────────────────────────────────────────────────────────────────

const BtnAddItem = document.getElementById('btn-add-item');
let   Select2Item = null;

if (BtnAddItem) {

    function initSelect2(tipo) {
        const label = document.getElementById('item-search-label');
        label.textContent = tipo === 'servico' ? 'Serviço' : 'Produto';

        if (Select2Item) $('#item-search').select2('destroy');

        const url = tipo === 'servico' ? '/os/buscar/servicos' : '/os/buscar/produtos';

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

        $('#item-search').off('select2:select').on('select2:select', function (e) {
            const data = e.params.data;
            document.getElementById('item-descricao').value = data.text;
            document.getElementById('item-preco').value     = parseFloat(data.preco).toFixed(2);
        });
    }

    BtnAddItem.addEventListener('click', () => {
        document.getElementById('item-descricao').value  = '';
        document.getElementById('item-preco').value      = '0';
        document.getElementById('item-quantidade').value = '1';

        initSelect2(document.getElementById('item-tipo').value);
        new bootstrap.Modal(document.getElementById('modal-item')).show();
    });

    document.getElementById('item-tipo').addEventListener('change', function () {
        initSelect2(this.value);
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-preco').value     = '0';
    });

    document.getElementById('btn-save-item').addEventListener('click', async () => {
        const tipo       = document.getElementById('item-tipo').value;
        const descricao  = document.getElementById('item-descricao').value.trim();
        const quantidade = parseFloat(document.getElementById('item-quantidade').value) || 1;
        const preco      = parseFloat(document.getElementById('item-preco').value)      || 0;
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
            const orderId = Id.value;
            const payload = {
                tipo,
                descricao,
                quantidade,
                preco_unitario: preco,
            };
            if (tipo === 'servico' && refId) payload.service_id = refId;
            if (tipo === 'produto'  && refId) payload.product_id = refId;

            const response = await requests.post(`/os/${orderId}/item`, payload);

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
        confirmButtonColor: '#dc3545',
    });

    if (!confirm.isConfirmed) return;

    $('button').prop('disabled', true);
    const requests = new Requests();
    try {
        const orderId  = Id.value;
        const response = await requests.post(`/os/${orderId}/item/${itemId}`, { _method: 'DELETE' });

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