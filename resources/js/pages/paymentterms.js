import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Codigo = document.getElementById('codigo');
const Titulo = document.getElementById('titulo');
const BtnAdd = document.getElementById('btnAddParcela');
const TbBody = document.getElementById('tbInstallments');
const AvisoEl = document.getElementById('avisoSemParcela');
const NomeFormaEl = document.getElementById('nomeFormaPagamento');
const WrapParcela = document.getElementById('wrapParcela');
const WrapIntervalo = document.getElementById('wrapIntervalo');
const InputParcela = document.getElementById('parcela');
const InputInterv = document.getElementById('intervalo');

const SEM_PARCELAMENTO = ['01', '04', '20']; // Dinheiro, Cartão de Débito, PIX Estático
const MAX_INTERVALO = 40;

let installments = [];

// ─── Helper: o Requests.post() só manda o que estiver em setBody/setForm,
// então pra mandar dados soltos precisamos montar URLSearchParams manualmente.
async function postData(url, data) {
    const params = new URLSearchParams();
    Object.entries(data).forEach(([key, value]) => params.append(key, value));
    return new Requests().setBody(params).post(url);
}

function isSemParcelamento() {
    return SEM_PARCELAMENTO.includes(Codigo.value);
}

function atualizarCamposForma() {
    const bloqueado = isSemParcelamento();
    const nomeForma = Codigo.options[Codigo.selectedIndex]?.text ?? '';

    AvisoEl.classList.toggle('d-none', !bloqueado);
    if (bloqueado) NomeFormaEl.textContent = nomeForma;

    InputParcela.disabled = bloqueado;
    InputInterv.disabled = bloqueado;

    if (bloqueado) {
        InputParcela.value = '';
        InputInterv.value = '';
        WrapParcela.style.opacity = '0.4';
        WrapIntervalo.style.opacity = '0.4';
    } else {
        WrapParcela.style.opacity = '1';
        WrapIntervalo.style.opacity = '1';
    }
}

function renderInstallments() {
    TbBody.innerHTML = '';

    if (installments.length === 0) {
        TbBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Nenhuma parcela adicionada.</td></tr>';
        return;
    }

    installments
        .sort((a, b) => a.parcela - b.parcela)
        .forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.parcela}x</td>
                <td>${item.intervalo != null ? item.intervalo + ' dias' : 'À vista'}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeParcela(${index})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>`;
            TbBody.appendChild(tr);
        });
}

window.removeParcela = async function (index) {
    const item = installments[index];

    if (item.id) {
        try {
            const res = await postData('/installment/excluir', { id: item.id });
            if (!res.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: res.msg, timer: 3000, timerProgressBar: true });
                return;
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
            return;
        }
    }

    installments.splice(index, 1);
    renderInstallments();
};

async function loadInstallments() {
    if (!Id.value) return;

    try {
        const res = await postData('/installment/listar', { id_pagamento: Id.value });
        if (res.status && res.data) {
            installments = res.data.map(r => ({
                id: r.id,
                parcela: r.parcela,
                intervalo: r.intervalo,
            }));
            renderInstallments();
        }
    } catch (e) { /* silencioso */ }
}

async function savePaymentTerms() {
    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Corrija os erros antes de salvar.', timer: 3000, timerProgressBar: true });
        return false;
    }

    try {
        const response = (Action.value !== 'e')
            ? await new Requests().setForm('form').post('/payment/inserir')
            : await new Requests().setForm('form').post('/payment/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            return false;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.replaceState({}, '', `/payment/detalhes/${response.id}`);
        return true;

    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
        return false;
    }
}

Codigo.addEventListener('change', () => {
    if (!Titulo.value) {
        Titulo.value = Codigo.options[Codigo.selectedIndex].text;
    }
    atualizarCamposForma();
});

InputInterv.addEventListener('input', () => {
    const v = parseInt(InputInterv.value) || 0;
    if (v > MAX_INTERVALO) {
        InputInterv.value = MAX_INTERVALO;
        Swal.fire({
            icon: 'warning', title: 'Atenção',
            text: `O intervalo máximo permitido é de ${MAX_INTERVALO} dias.`,
            timer: 2500, timerProgressBar: true,
        });
    }
});

BtnAdd.addEventListener('click', async () => {
    const semParc = isSemParcelamento();
    const maxParcela = semParc ? 1 : (parseInt(InputParcela.value) || 0);
    const intervalo = semParc ? 0 : (parseInt(InputInterv.value) || 0);

    if (!semParc && maxParcela <= 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe a quantidade de parcelas.', timer: 3000, timerProgressBar: true });
        InputParcela.focus();
        return;
    }

    if (!semParc && (intervalo < 0 || intervalo > MAX_INTERVALO)) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: `O intervalo deve ser entre 0 e ${MAX_INTERVALO} dias.`, timer: 3000, timerProgressBar: true });
        InputInterv.focus();
        return;
    }

    if (!Id.value) {
        const ok = await savePaymentTerms();
        if (!ok) return;
    }

    const parcelasJaCadastradas = new Set(installments.map(i => i.parcela));
    let adicionados = 0;
    let erros = 0;

    for (let n = 1; n <= maxParcela; n++) {
        if (parcelasJaCadastradas.has(n)) continue;

        try {
            const res = await postData('/installment/inserir', {
                id: Id.value,
                parcela: n,
                intervalo: n === 1 ? 0 : intervalo,
            });

            if (res && res.status) {
                installments.push({ id: res.id, parcela: n, intervalo: n === 1 ? 0 : intervalo });
                adicionados++;
            } else {
                erros++;
            }
        } catch (e) {
            erros++;
        }
    }

    renderInstallments();

    InputParcela.value = '';
    InputInterv.value = '';

    if (!semParc) InputParcela.focus();

    if (erros > 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: `${adicionados} parcela(s) salva(s), ${erros} erro(s).`, timer: 3000, timerProgressBar: true });
    } else if (adicionados === 0) {
        Swal.fire({ icon: 'info', title: 'Sem alterações', text: 'Todas as parcelas até esse número já estavam cadastradas.', timer: 2500, timerProgressBar: true });
    } else {
        Swal.fire({ icon: 'success', title: 'Adicionado!', text: `${adicionados} opção(ões) de parcelamento salva(s) (1x até ${maxParcela}x).`, timer: 2500, timerProgressBar: true });
    }
});

document.getElementById('insert').addEventListener('click', async () => {
    $('button').prop('disabled', true);

    const ok = await savePaymentTerms();

    if (ok) {
        Swal.fire({
            icon: 'success', title: 'Sucesso',
            text: 'Condição de pagamento salva com sucesso!',
            timer: 2500, timerProgressBar: true,
        }).then(() => { window.location.href = '/payment/lista'; });
    }

    $('button').prop('disabled', false);
});

atualizarCamposForma();
loadInstallments();