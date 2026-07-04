import Requests from "../components/requests.js";
import Validate from "../components/validate.js";
import * as bootstrap from 'bootstrap';

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const BtnAction = document.getElementById('btn-action');
const BtnActionLabel = document.getElementById('btn-action-label');
const itemsCount = parseInt(BtnAction?.dataset.itemsCount ?? '0', 10);

async function postData(url, data) {
    const params = new URLSearchParams();
    Object.entries(data).forEach(([key, value]) => params.append(key, value));
    return new Requests().setBody(params).post(url);
}

function setMoneyValue(input, value) {
    input.dataset.rawValue = value.toFixed(2);
    input.value = 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setItemPreco(value) {
    setMoneyValue(document.getElementById('item-preco'), value);
    document.getElementById('item-preco-hidden').value = value.toFixed(2);
}

// ── Salvar compra ─────────────────────────────────────────────────────────────

async function applyChanges({ silent = false } = {}) {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Por favor, corrija os erros no formulário antes de salvar.', timer: 3000, timerProgressBar: true });
        $('button').prop('disabled', false);
        return false;
    }

    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/compras/inserir')
            : await requests.setForm('form').post('/compras/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Ocorreu um erro ao salvar a compra.', timer: 3000, timerProgressBar: true });
            return false;
        }

        if (Action.value !== 'e') {
            const redirectUrl = `${window.location.origin}/compras/detalhes/${response.id}`;
            Action.value = 'e';
            Id.value = response.id;
            window.history.pushState({}, '', redirectUrl);
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'Compra criada com sucesso!', timer: 3000, timerProgressBar: true })
                .then(() => { window.location.reload(); });
        } else if (!silent) {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'Compra atualizada com sucesso!', timer: 3000, timerProgressBar: true });
        }

        return true;
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
        return false;
    } finally {
        $('button').prop('disabled', false);
    }
}

// ── Receber compra ────────────────────────────────────────────────────────────

async function handleReceiveClick() {
    const saved = await applyChanges({ silent: true });
    if (!saved) return;

    const confirm = await Swal.fire({
        icon: 'question',
        title: 'Receber compra?',
        text: 'Isso vai dar entrada no estoque de todos os produtos desta compra. Esta ação não pode ser desfeita.',
        showCancelButton: true,
        confirmButtonText: 'Sim, receber',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
    });

    if (!confirm.isConfirmed) return;

    $('button').prop('disabled', true);
    try {
        const response = await postData('/compras/receber', { id: Id.value });

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao receber a compra.', timer: 3000, timerProgressBar: true });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'Compra recebida com sucesso!', timer: 3000, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
}

// ── Cancelar compra ───────────────────────────────────────────────────────────

async function handleCancelClick() {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Cancelar compra?',
        text: 'A compra será marcada como cancelada e não poderá mais ser editada.',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#dc3545',
    });

    if (!confirm.isConfirmed) return;

    $('button').prop('disabled', true);
    try {
        const response = await postData('/compras/cancelar', { id: Id.value });

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao cancelar a compra.', timer: 3000, timerProgressBar: true });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Compra Cancelada', text: response.msg, timer: 2500, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
}

// ── Estado do botão de ação ───────────────────────────────────────────────────

function configureActionButton() {
    if (!BtnAction) return;

    if (Action.value === 'e' && itemsCount > 0) {
        BtnActionLabel.textContent = 'Receber Compra';
        BtnAction.classList.remove('btn-success');
        BtnAction.classList.add('btn-primary');
        BtnAction.onclick = handleReceiveClick;
    } else if (Action.value === 'e') {
        BtnActionLabel.textContent = 'Salvar alterações';
        BtnAction.onclick = () => applyChanges();
    } else {
        BtnActionLabel.textContent = 'Salvar';
        BtnAction.onclick = () => applyChanges();
    }

    const BtnCancel = document.getElementById('btn-cancel-compra');
    if (BtnCancel && Action.value === 'e') {
        BtnCancel.onclick = handleCancelClick;
    }
}

configureActionButton();

// ── Itens ─────────────────────────────────────────────────────────────────────

const BtnAddItem = document.getElementById('btn-add-item');

if (BtnAddItem) {

    let dtSearchItems = null;

    function calcularSubtotal() {
        const quantidade = parseFloat(document.getElementById('item-quantidade').value) || 0;
        const preco = parseFloat(document.getElementById('item-preco').dataset.rawValue) || 0;
        const subtotal = (quantidade * preco).toFixed(2).replace('.', ',');
        document.getElementById('item-subtotal').textContent = `R$ ${subtotal}`;
    }

    document.getElementById('item-quantidade').addEventListener('input', calcularSubtotal);

    function initDtSearch() {
        document.getElementById('item-form-wrap').classList.add('d-none');
        document.getElementById('btn-save-item').classList.add('d-none');
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-quantidade').value = '1';
        setItemPreco(0);
        document.getElementById('item-subtotal').textContent = 'R$ 0,00';
        document.getElementById('item-product-id').value = '';

        if (dtSearchItems) {
            dtSearchItems.destroy();
            $('#table-search-items tbody').empty();
        }

        dtSearchItems = $('#table-search-items').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/compras/buscar/produtos',
                data: params => ({ q: params.search?.value ?? '' }),
                dataSrc: 'results',
            },
            columns: [
                { data: 'nome', title: 'Nome' },
                { data: 'estoque_atual', title: 'Estoque atual', render: val => parseFloat(val).toLocaleString('pt-BR') },
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            pageLength: 5,
            lengthChange: false,
        });

        $('#table-search-items tbody').off('click', 'tr').on('click', 'tr', function () {
            const data = dtSearchItems.row(this).data();
            if (!data) return;

            $('#table-search-items tbody tr').removeClass('table-primary');
            $(this).addClass('table-primary');

            document.getElementById('item-descricao').value = data.nome;
            document.getElementById('item-quantidade').value = '1';
            setItemPreco(parseFloat(data.preco_compra) || 0);
            calcularSubtotal();

            document.getElementById('item-product-id').value = data.id;

            document.getElementById('item-form-wrap').classList.remove('d-none');
            document.getElementById('btn-save-item').classList.remove('d-none');
        });
    }

    BtnAddItem.addEventListener('click', () => {
        const modalEl = document.getElementById('modal-item');
        const modal = new bootstrap.Modal(modalEl);

        modalEl.addEventListener('shown.bs.modal', function handler() {
            initDtSearch();
            this.removeEventListener('shown.bs.modal', handler);
        });

        modal.show();
    });

    document.getElementById('btn-save-item').addEventListener('click', async () => {
        $('button').prop('disabled', true);
        const purchaseId = Id.value;

        const requests = new Requests();
        try {
            const response = await requests.setForm('form').post(`/compras/${purchaseId}/item`);
            if (!response.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao adicionar item.', timer: 3000, timerProgressBar: true });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modal-item')).hide();
            window.location.reload();
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
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
    try {
        const purchaseId = Id.value;
        const response = await postData(`/compras/${purchaseId}/item/${itemId}`, { _method: 'DELETE' });

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao excluir item.', timer: 3000, timerProgressBar: true });
            return;
        }

        window.location.reload();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
};