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

// ── Salvar OS ─────────────────────────────────────────────────────────────────

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
            ? await requests.setForm('form').post('/os/inserir')
            : await requests.setForm('form').post('/os/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Ocorreu um erro ao salvar a OS.', timer: 3000, timerProgressBar: true });
            return false;
        }

        if (Action.value !== 'e') {
            const redirectUrl = `${window.location.origin}/os/detalhes/${response.id}`;
            Action.value = 'e';
            Id.value = response.id;
            window.history.pushState({}, '', redirectUrl);
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'OS aberta com sucesso!', timer: 3000, timerProgressBar: true })
                .then(() => { window.location.reload(); });
        } else if (!silent) {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'OS atualizada com sucesso!', timer: 3000, timerProgressBar: true });
        }

        return true;
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
        return false;
    } finally {
        $('button').prop('disabled', false);
    }
}

// ── Cancelar OS ───────────────────────────────────────────────────────────────

async function handleCancelClick() {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Cancelar OS?',
        text: 'A OS será marcada como cancelada e não poderá mais ser editada.',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar OS',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#dc3545',
    });

    if (!confirm.isConfirmed) return;

    $('button').prop('disabled', true);
    try {
        const response = await postData('/os/cancelar', { id: Id.value });

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao cancelar a OS.', timer: 3000, timerProgressBar: true });
            return;
        }

        Swal.fire({ icon: 'success', title: 'OS Cancelada', text: response.msg, timer: 2500, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
}

// ── Modal de pagamento (Finalizar OS) — com SPLIT ─────────────────────────────

let paymentTermsData = [];
let splits = [];
let totalLiquido = 0;
let pendingTerm = null;

function fmtBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}
function fmtNumber(value) {
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}
function digitsToDecimal(digits) {
    if (!digits) digits = '0';
    return parseInt(digits, 10) / 100;
}

function applyMoneyMask(input) {
    const format = () => {
        const digits = input.value.replace(/\D/g, '');
        const value = digitsToDecimal(digits);
        input.dataset.rawValue = value.toFixed(2);
        input.value = 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    input.addEventListener('input', format);
    input._maskFormat = format;
    return format;
}

function setMoneyValue(input, value) {
    input.dataset.rawValue = value.toFixed(2);
    input.value = 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyPercentMask(input, max = 100) {
    const format = () => {
        const digits = input.value.replace(/\D/g, '');
        let value = digitsToDecimal(digits);
        if (value > max) value = max;
        input.dataset.rawValue = value.toFixed(2);
        input.value = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    };
    input.addEventListener('input', format);
    format();
}

function getNegotiationValues() {
    const desconto = parseFloat(document.getElementById('modal-desconto')?.dataset.rawValue) || 0;
    const acrescimo = parseFloat(document.getElementById('modal-acrescimo')?.dataset.rawValue) || 0;
    return { desconto, acrescimo };
}

function somaSplitsCentavos() {
    return splits.reduce((acc, s) => acc + Math.round(s.valor * 100), 0);
}

function saldoRestante() {
    const totalCentavos = Math.round(totalLiquido * 100);
    return (totalCentavos - somaSplitsCentavos()) / 100;
}

function renderSaldo() {
    const restante = saldoRestante();
    const el = document.getElementById('saldo-restante');
    const alertEl = document.getElementById('saldo-alert');

    el.textContent = fmtBRL(Math.max(restante, 0));

    alertEl.classList.remove('alert-info', 'alert-success', 'alert-danger');
    if (Math.abs(restante) < 0.005) {
        alertEl.classList.add('alert-success');
    } else if (restante < 0) {
        alertEl.classList.add('alert-danger');
        el.textContent = '- ' + fmtBRL(Math.abs(restante));
    } else {
        alertEl.classList.add('alert-info');
    }

    document.getElementById('confirm-payment-btn').disabled = !(Math.abs(restante) < 0.005 && splits.length > 0);
}

function renderSplitTable() {
    const tbody = document.getElementById('split-tbody');
    tbody.innerHTML = '';

    if (splits.length === 0) {
        tbody.innerHTML = '<tr id="split-empty-row"><td colspan="4" class="text-center text-muted py-2">Nenhuma forma adicionada ainda.</td></tr>';
        return;
    }

    splits.forEach((s, index) => {
        const parcelas = s.installments && s.installments.length ? s.installments : [{ intervalo: null, valor: s.valor }];

        parcelas.forEach((p, pIndex) => {
            const tr = document.createElement('tr');
            const isFirstRow = pIndex === 0;

            tr.innerHTML = `
                <td>${isFirstRow ? s.titulo : ''}</td>
                <td>${parcelas.length > 1 ? `${pIndex + 1}/${parcelas.length}` : 'À vista'}${p.intervalo ? ` <span class="text-muted">(${p.intervalo}d)</span>` : ''}</td>
                <td class="text-end">${fmtBRL(p.valor)}</td>
                <td class="text-center">
                    ${isFirstRow ? `<button type="button" class="btn btn-sm btn-danger btn-remove-split" data-index="${index}"><i class="fa-solid fa-trash"></i></button>` : ''}
                </td>`;
            tbody.appendChild(tr);
        });
    });

    tbody.querySelectorAll('.btn-remove-split').forEach(btn => {
        btn.addEventListener('click', () => {
            splits.splice(parseInt(btn.dataset.index, 10), 1);
            renderSplitTable();
            renderSaldo();
        });
    });
}

function renderPaymentTerms(terms) {
    const list = document.getElementById('payment-terms-list');
    list.innerHTML = '';

    if (!terms.length) {
        list.innerHTML = '<div class="">Nenhuma condição de pagamento cadastrada.</div>';
        return;
    }

    terms.forEach(term => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-secondary btn-sm';
        btn.textContent = term.titulo || term.codigo || `Condição #${term.id}`;
        btn.dataset.termId = term.id;
        btn.addEventListener('click', () => selectTermForNewSplit(term, btn));
        list.appendChild(btn);
    });
}

function selectTermForNewSplit(term, btnEl) {
    pendingTerm = term;

    document.querySelectorAll('#payment-terms-list .btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });
    btnEl.classList.remove('btn-secondary');
    btnEl.classList.add('btn-primary');

    document.getElementById('add-split-form').classList.remove('d-none');

    const valorInput = document.getElementById('split-valor');
    setMoneyValue(valorInput, Math.max(saldoRestante(), 0));

    renderParcelasSelectorForPending(term);
}

let pendingParcelas = 1;

function renderParcelasSelectorForPending(term) {
    const wrap = document.getElementById('split-parcelas-wrap');
    const selectorEl = document.getElementById('split-parcelas-selector');
    selectorEl.innerHTML = '';
    pendingParcelas = 1;

    const maxParcelas = term.max_parcelas || 1;
    const valorBadge = document.getElementById('split-valor-step-badge');

    if (maxParcelas <= 1) {
        wrap.classList.add('d-none');
        if (valorBadge) valorBadge.textContent = '2';
        return;
    }

    wrap.classList.remove('d-none');
    if (valorBadge) valorBadge.textContent = '3';

    for (let i = 1; i <= maxParcelas; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm ' + (i === 1 ? 'btn-primary' : 'btn-outline-secondary');
        btn.textContent = `${i}x`;
        btn.dataset.parcelas = i;
        btn.addEventListener('click', () => {
            pendingParcelas = i;
            selectorEl.querySelectorAll('button').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');
        });
        selectorEl.appendChild(btn);
    }
}

function resetAddSplitForm() {
    pendingTerm = null;
    pendingParcelas = 1;
    document.getElementById('add-split-form').classList.add('d-none');
    document.getElementById('split-parcelas-wrap').classList.add('d-none');
    document.querySelectorAll('#payment-terms-list .btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });
}

document.getElementById('btn-usar-restante')?.addEventListener('click', () => {
    const valorInput = document.getElementById('split-valor');
    setMoneyValue(valorInput, Math.max(saldoRestante(), 0));
});

document.getElementById('btn-add-split')?.addEventListener('click', async () => {
    if (!pendingTerm) return;

    const valorInput = document.getElementById('split-valor');
    const valor = parseFloat(valorInput.dataset.rawValue) || 0;
    const addBtn = document.getElementById('btn-add-split');

    if (valor <= 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe um valor maior que zero.', timer: 2500, timerProgressBar: true });
        return;
    }

    if (valor - saldoRestante() > 0.005) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: `Esse valor ultrapassa o saldo restante (${fmtBRL(saldoRestante())}).`, timer: 3000, timerProgressBar: true });
        return;
    }

    addBtn.disabled = true;

    let installments = [];
    try {
        const requests = new Requests();
        const url = `/os/${Id.value}/installment-preview?id_payment_terms=${pendingTerm.id}&parcelas=${pendingParcelas}&valor=${valor}`;
        const preview = await requests.get(url);
        if (preview.status) {
            installments = preview.data;
        }
    } catch (error) {
        // se o preview falhar, segue só com o valor total
    }

    splits.push({
        id_payment_terms: pendingTerm.id,
        titulo: pendingTerm.titulo || pendingTerm.codigo,
        parcelas: pendingParcelas,
        valor,
        installments,
    });

    addBtn.disabled = false;
    renderSplitTable();
    renderSaldo();
    resetAddSplitForm();
});

async function loadPaymentTerms() {
    const orderId = Id.value;
    const list = document.getElementById('payment-terms-list');
    list.innerHTML = '<div class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Carregando...</div>';

    resetAddSplitForm();

    const { desconto, acrescimo } = getNegotiationValues();

    const requests = new Requests();
    try {
        const response = await requests.get(`/os/${orderId}/payment-terms?desconto=${desconto}&acrescimo=${acrescimo}`);
        if (!response.status) {
            list.innerHTML = '<div class="text-danger">Erro ao carregar condições de pagamento.</div>';
            return;
        }
        paymentTermsData = response.data || [];
        renderPaymentTerms(paymentTermsData);

        totalLiquido = response.total_liquido;

        document.getElementById('modal-subtotal').textContent = fmtNumber(response.subtotal);
        document.getElementById('modal-total-os').textContent = fmtNumber(response.total_liquido);

        renderSaldo();
    } catch (error) {
        list.innerHTML = '<div class="text-danger">Erro ao carregar condições de pagamento.</div>';
    }
}

async function confirmPayment() {
    if (splits.length === 0 || Math.abs(saldoRestante()) >= 0.005) return;

    $('#modal-payment button').prop('disabled', true);
    const { desconto, acrescimo } = getNegotiationValues();

    const payments = splits.map(s => ({
        id_payment_terms: s.id_payment_terms,
        parcelas: s.parcelas,
        valor: s.valor,
    }));

    try {
        const response = await postData('/os/concluir', {
            id: Id.value,
            desconto,
            acrescimo,
            payments: JSON.stringify(payments),
        });

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao finalizar a OS.', timer: 3000, timerProgressBar: true });
            $('#modal-payment button').prop('disabled', false);
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('modal-payment')).hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'OS finalizada com sucesso!', timer: 3000, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
        $('#modal-payment button').prop('disabled', false);
    }
}

const modalPaymentEl = document.getElementById('modal-payment');
if (modalPaymentEl) {
    applyPercentMask(document.getElementById('modal-desconto'));
    applyPercentMask(document.getElementById('modal-acrescimo'));
    applyMoneyMask(document.getElementById('split-valor'));

    modalPaymentEl.addEventListener('show.bs.modal', () => {
        splits = [];
        renderSplitTable();
        loadPaymentTerms();
    });

    document.getElementById('confirm-payment-btn')?.addEventListener('click', confirmPayment);

    let negotiationDebounce = null;
    ['modal-desconto', 'modal-acrescimo'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            clearTimeout(negotiationDebounce);
            negotiationDebounce = setTimeout(() => {
                if (splits.length > 0) {
                    Swal.fire({ icon: 'info', title: 'Split reiniciado', text: 'A negociação mudou o total da OS — refaça a divisão das formas de pagamento.', timer: 3000, timerProgressBar: true });
                }
                splits = [];
                renderSplitTable();
                loadPaymentTerms();
            }, 400);
        });
    });
}

const inputDesconto = document.getElementById('modal-desconto');
const inputAcrescimo = document.getElementById('modal-acrescimo');

function zerarCampo(el) {
    el.value = '0,00%';
    el.dataset.rawValue = '0.00';
}

inputDesconto?.addEventListener('input', () => {
    const valor = parseFloat(inputDesconto.dataset.rawValue) || 0;
    if (valor > 0) zerarCampo(inputAcrescimo);
});

inputAcrescimo?.addEventListener('input', () => {
    const valor = parseFloat(inputAcrescimo.dataset.rawValue) || 0;
    if (valor > 0) zerarCampo(inputDesconto);
});

// ── Estado do botão de ação ───────────────────────────────────────────────────

async function handleFinalizeClick() {
    const saved = await applyChanges({ silent: true });
    if (!saved) return;

    const modal = new bootstrap.Modal(document.getElementById('modal-payment'));
    modal.show();
}

function configureActionButton() {
    if (!BtnAction) return;

    if (Action.value === 'e' && itemsCount > 0) {
        BtnActionLabel.textContent = 'Finalizar OS';
        BtnAction.classList.remove('btn-success');
        BtnAction.classList.add('btn-primary');
        BtnAction.onclick = handleFinalizeClick;
    } else if (Action.value === 'e') {
        BtnActionLabel.textContent = 'Salvar alterações';
        BtnAction.onclick = () => applyChanges();
    } else {
        BtnActionLabel.textContent = 'Salvar';
        BtnAction.onclick = () => applyChanges();
    }

    const BtnCancel = document.getElementById('btn-cancel-os');
    if (BtnCancel && Action.value === 'e') {
        BtnCancel.onclick = handleCancelClick;
    }
}

configureActionButton();

// ── Itens ─────────────────────────────────────────────────────────────────────

const BtnAddItem = document.getElementById('btn-add-item');

if (BtnAddItem) {

    let dtSearchItems = null;
    let selectedItem = null;

    function calcularSubtotal() {
        const quantidade = parseFloat(document.getElementById('item-quantidade').value) || 0;
        const preco = parseFloat(document.getElementById('item-preco').dataset.rawValue) || 0;
        const subtotal = (quantidade * preco).toFixed(2).replace('.', ',');
        document.getElementById('item-subtotal').textContent = `R$ ${subtotal}`;
    }

    document.getElementById('item-quantidade').addEventListener('input', calcularSubtotal);
    document.getElementById('item-preco').addEventListener('input', calcularSubtotal);
    applyMoneyMask(document.getElementById('item-preco'));

    function initDtSearch(tipo) {
        const url = tipo === 'servico' ? '/os/buscar/servicos' : '/os/buscar/produtos';

        selectedItem = null;
        document.getElementById('item-form-wrap').classList.add('d-none');
        document.getElementById('btn-save-item').classList.add('d-none');
        document.getElementById('item-descricao').value = '';
        document.getElementById('item-quantidade').value = '1';
        setMoneyValue(document.getElementById('item-preco'), 0);
        document.getElementById('item-subtotal').textContent = 'R$ 0,00';
        document.getElementById('item-product-id').value = '';
        document.getElementById('item-service-id').value = '';

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
            setMoneyValue(document.getElementById('item-preco'), parseFloat(data.preco));
            document.getElementById('item-quantidade').value = '1';
            calcularSubtotal();

            document.getElementById('item-product-id').value = tipo === 'produto' ? data.id : '';
            document.getElementById('item-service-id').value = tipo === 'servico' ? data.id : '';

            document.getElementById('item-form-wrap').classList.remove('d-none');
            document.getElementById('btn-save-item').classList.remove('d-none');
        });
    }

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

    document.getElementById('btn-save-item').addEventListener('click', async () => {
        $('button').prop('disabled', true);
        const orderId = Id.value;
        const precoInput = document.getElementById('item-preco');
        const precoMasked = precoInput.value;
        precoInput.value = precoInput.dataset.rawValue;

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
            Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
        } finally {
            precoInput.value = precoMasked;
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
        const orderId = Id.value;
        const response = await postData(`/os/${orderId}/item/${itemId}`, { _method: 'DELETE' });

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