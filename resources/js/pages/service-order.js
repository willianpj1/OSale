import Requests from "../components/requests.js";
import Validate from "../components/validate.js";
import * as bootstrap from 'bootstrap';

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const BtnSave = document.getElementById('insert');

// ── Salvar OS ─────────────────────────────────────────────────────────────────

async function applyChanges() {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Por favor, corrija os erros no formulário antes de salvar.', timer: 3000, timerProgressBar: true });
        $('button').prop('disabled', false);
        return;
    }

    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/os/inserir')
            : await requests.setForm('form').post('/os/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Ocorreu um erro ao salvar a OS.', timer: 3000, timerProgressBar: true });
            return;
        }

        if (Action.value !== 'e') {
            const redirectUrl = `${window.location.origin}/os/detalhes/${response.id}`;
            Action.value = 'e';
            Id.value = response.id;
            window.history.pushState({}, '', redirectUrl);
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'OS aberta com sucesso!', timer: 3000, timerProgressBar: true })
                .then(() => { window.location.reload(); });
        } else {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'OS atualizada com sucesso!', timer: 3000, timerProgressBar: true });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Restrição: ${error.message}`, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
}

BtnSave.addEventListener('click', async () => {
    await applyChanges();
});

// ── Itens ─────────────────────────────────────────────────────────────────────

const BtnAddItem = document.getElementById('btn-add-item');

if (BtnAddItem) {

    let dtSearchItems = null;
    let selectedItem = null;

    // ── Subtotal ──────────────────────────────────────────────────────────────

    function calcularSubtotal() {
        const quantidade = parseFloat(document.getElementById('item-quantidade').value) || 0;
        const preco = parseFloat(document.getElementById('item-preco').value) || 0;
        const subtotal = (quantidade * preco).toFixed(2).replace('.', ',');
        document.getElementById('item-subtotal').textContent = `R$ ${subtotal}`;
    }

    document.getElementById('item-quantidade').addEventListener('input', calcularSubtotal);
    document.getElementById('item-preco').addEventListener('input', calcularSubtotal);

    // ── DataTable de busca ────────────────────────────────────────────────────

    function initDtSearch(tipo) {
        const url = tipo === 'servico' ? '/os/buscar/servicos' : '/os/buscar/produtos';

        selectedItem = null;
        document.getElementById('item-form-wrap').classList.add('d-none');
        document.getElementById('btn-save-item').classList.add('d-none');
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-quantidade').value = '1';
        document.getElementById('item-preco').value = '';
        document.getElementById('item-subtotal').textContent = 'R$ 0,00';

        if (dtSearchItems) {
            dtSearchItems.destroy();
            $('#table-search-items tbody').empty();
        }

        dtSearchItems = $('#table-search-items').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url,
                data: params => ({ q: params.search?.value ?? '' }),
                dataSrc: 'results',
            },
            columns: [
                { data: 'nome', title: 'Nome' },
                {
                    data: 'preco',
                    title: 'Preço',
                    render: val => 'R$ ' + parseFloat(val).toFixed(2).replace('.', ','),
                },
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

            selectedItem = { id: data.id, nome: data.nome, preco: data.preco, tipo };
            document.getElementById('item-descricao').value = data.nome;
            document.getElementById('item-preco').value = parseFloat(data.preco).toFixed(2);
            document.getElementById('item-quantidade').value = '1';
            calcularSubtotal();

            document.getElementById('item-form-wrap').classList.remove('d-none');
            document.getElementById('btn-save-item').classList.remove('d-none');
        });
    }

    // ── Abre modal ────────────────────────────────────────────────────────────

    BtnAddItem.addEventListener('click', () => {
        const modalEl = document.getElementById('modal-item');
        const modal = new bootstrap.Modal(modalEl);

        modalEl.addEventListener('shown.bs.modal', function handler() {
            const tipo = document.getElementById('item-tipo').value;
            initDtSearch(tipo);
            this.removeEventListener('shown.bs.modal', handler);
        });

        modal.show();
    });

    document.getElementById('item-tipo').addEventListener('change', function () {
        initDtSearch(this.value);
    });

    // ── Salvar item ───────────────────────────────────────────────────────────

    document.getElementById('btn-save-item').addEventListener('click', async () => {

        $('button').prop('disabled', true);
        const orderId = Id.value;

        const requests = new Requests();
        try {
            const response = await requests.setForm('form').post(`/os/${orderId}/item`);
            if (!response.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao adicionar item.', timer: 3000, timerProgressBar: true });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modal-item')).hide();
            window.location.reload();
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Erro', text: `Restrição: ${error.message}`, timer: 3000, timerProgressBar: true });
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
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao excluir item.', timer: 3000, timerProgressBar: true });
            return;
        }

        window.location.reload();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Restrição: ${error.message}`, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
};