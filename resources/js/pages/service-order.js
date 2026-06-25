const $ = window.jQuery
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";
import Select2 from "../components/select2.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const BtnSave = document.getElementById('insert');
const ItemTipo = document.getElementById('item-tipo');
const BtnAddItem = document.getElementById('btn-add-item');

const url = (ItemTipo && ItemTipo.value === 'servico') ? '/os/buscar/servicos' : '/os/buscar/produtos';

const ItemSearch = new Select2('#item-search');
ItemSearch.init(url, {
    dropdownParent: '#modal-item',
    placeholder: 'Selecione a categoria',
});

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
        }); F
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
            const redirectUrl = `${window.location.origin}/os/detalhes/${response.id}`;
            Action.value = 'e';
            Id.value = response.id;
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

if (BtnSave) {
    BtnSave.addEventListener('click', async () => {
        await applyChanges();
    });
}

// ── Concluir OS ───────────────────────────────────────────────────────────────

const BtnFinalizar = document.getElementById('btn-finalizar');

if (BtnFinalizar) {
    BtnFinalizar.addEventListener('click', async () => {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Concluir OS?',
            text: 'A OS será marcada como concluída e não poderá mais ser editada. Deseja continuar?',
            showCancelButton: true,
            confirmButtonText: 'Sim, concluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
        });

        if (!confirm.isConfirmed) return;

        $('button').prop('disabled', true);
        const requests = new Requests();
        try {
            const response = await requests.setForm('form').post('/os/concluir');

            if (!response.status) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response.msg || 'Erro ao concluir OS.',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Concluída!',
                text: response.msg || 'OS concluída com sucesso!',
                timer: 2500,
                timerProgressBar: true,
            }).then(() => {
                window.location.reload();
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



/*if (BtnAddItem) {

    // ── Select2 — inicializado sempre APÓS shown.bs.modal ────────────────────
    function initSelect2(tipo) {
        const label = document.getElementById('item-search-label');
        label.textContent = tipo === 'servico' ? 'Serviço' : 'Produto';

        // Destroi instância anterior sem deixar estado sujo
        /* if ($.fn.select2 && $('#item-search').data('select2')) {
             $('#item-search').select2('destroy');
         }

        const url = tipo === 'servico' ? '/os/buscar/servicos' : '/os/buscar/produtos';

        /*$('#item-search').select2({
            dropdownParent: $('#modal-item'),
            placeholder: 'Digite para buscar...',
            ajax: {
                method: 'POST',
                url: url,
                dataType: 'json',
                delay: 250
            }
        });

        // Listener de seleção — reattacha após cada destroy/reinit
        /*$('#item-search').off('select2:select').on('select2:select', function (e) {
            const d = e.params.data;
            document.getElementById('item-descricao').value = d.text;
            document.getElementById('item-preco').value = d.preco.toFixed(2);
        });
    }*/

// ── Abrir modal — Select2 só inicia após shown.bs.modal ──────────────────
if (BtnAddItem) {
    BtnAddItem.addEventListener('click', () => {
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-preco').value = '0';
        document.getElementById('item-quantidade').value = '1';

        const modalEl = document.getElementById('modal-item');
        const modal = new bootstrap.Modal(modalEl);

        // shown.bs.modal garante que o modal está visível e montado no DOM
        // antes do Select2 calcular o dropdownParent
        modalEl.addEventListener('shown.bs.modal', function handler() {
            //initSelect2(document.getElementById('item-tipo').value);
            modalEl.removeEventListener('shown.bs.modal', handler);
        });

        modal.show();
    });
}

// ── Troca de tipo dentro do modal ─────────────────────────────────────────
if (ItemTipo) {
    ItemTipo.addEventListener('change', function () {
        //initSelect2(this.value);
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-preco').value = '0';
    });
}

// ── Salvar item ───────────────────────────────────────────────────────────
if (document.getElementById('btn-save-item')) {
    document.getElementById('btn-save-item').addEventListener('click', async () => {
        const tipo = document.getElementById('item-tipo').value;
        const descricao = document.getElementById('item-descricao').value.trim();
        const quantidade = parseFloat(document.getElementById('item-quantidade').value) || 1;
        const preco = parseFloat(document.getElementById('item-preco').value) || 0;
        //const selected = $('#item-search').select2('data')[0];
        const refId = selected?.id ?? null;

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
            if (tipo === 'produto' && refId) payload.product_id = refId;

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
        icon: 'warning',
        title: 'Excluir item?',
        text: 'Esta ação não pode ser desfeita.',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
    });

    if (!confirm.isConfirmed) return;

    $('button').prop('disabled', true);
    const requests = new Requests();
    try {
        const orderId = Id.value;
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