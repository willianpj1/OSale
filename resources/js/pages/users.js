import Swal from 'sweetalert2';
import 'datatables.net-bs5';

// ── Helpers de request ───────────────────────────────────────────────────

async function postJson(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });

    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error('Resposta inválida do servidor');
    }

    if (!response.ok || result.success === false) {
        const err = new Error(result.message || 'Erro na requisição');
        err.errors = result.errors || {};
        err.status = response.status;
        throw err;
    }

    return result;
}

function showError(err) {
    let html = '';
    if (err.errors && Object.keys(err.errors).length > 0) {
        html = '<ul class="text-start mb-0">' +
            Object.values(err.errors).map(msg => `<li>${msg}</li>`).join('') +
            '</ul>';
    }

    Swal.fire({
        icon: 'error',
        title: err.message || 'Erro',
        html: html || undefined,
    });
}

function showSuccess(title) {
    return Swal.fire({
        icon: 'success',
        title,
        timer: 1500,
        showConfirmButton: false,
    });
}

function clearFieldErrors(scope = document) {
    scope.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function applyFieldErrors(errors, scope = document) {
    Object.keys(errors).forEach(field => {
        const el = scope.querySelector(`[name="${field}"], #${field}`);
        if (el) el.classList.add('is-invalid');
    });
}

function val(id) {
    return document.getElementById(id)?.value.trim() ?? '';
}

// ── Estado em memória: lista de endereços ────────────────────────────────
// cada item: { tempId, id (se já existe no banco), nome, cep, logradouro, numero, complemento, bairro, cidade, estado }

let enderecosPendentes = [];
let nextTempId = 1;

// se a tela de edição já injetou endereços do banco, popula o estado inicial
if (Array.isArray(window.enderecosExistentes)) {
    enderecosPendentes = window.enderecosExistentes.map(e => ({
        ...e,
        tempId: nextTempId++,
    }));
}

function renderAddressTable() {
    const tbody = document.getElementById('table-addresses-body');
    const emptyMsg = document.getElementById('addresses-empty');

    tbody.innerHTML = '';

    if (enderecosPendentes.length === 0) {
        emptyMsg.style.display = 'block';
        return;
    }
    emptyMsg.style.display = 'none';

    enderecosPendentes.forEach(end => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${end.nome ?? ''}</td>
            <td>${end.logradouro ?? ''}</td>
            <td>${end.numero ?? ''}</td>
            <td>${end.bairro ?? ''}</td>
            <td>${end.cidade ?? ''}${end.estado ? ' / ' + end.estado : ''}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" data-temp-id="${end.tempId}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // bind dos botões de remover (delegação simples, já que a tabela é recriada toda vez)
    tbody.querySelectorAll('button[data-temp-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const tempId = Number(btn.dataset.tempId);
            enderecosPendentes = enderecosPendentes.filter(e => e.tempId !== tempId);
            renderAddressTable();
        });
    });
}

// ── Modal de endereço ─────────────────────────────────────────────────────

const modalAddressEl = document.getElementById('modal-address');
const modalAddress = modalAddressEl ? new bootstrap.Modal(modalAddressEl) : null;

function resetAddressModal() {
    document.getElementById('a-nome').value = 'Casa';
    ['a-cep', 'a-logradouro', 'a-numero', 'a-complemento', 'a-bairro', 'a-cidade', 'a-estado']
        .forEach(id => { document.getElementById(id).value = ''; });
    clearFieldErrors(modalAddressEl);
}

document.getElementById('btn-add-address')?.addEventListener('click', () => {
    resetAddressModal();
    modalAddress.show();
});

document.getElementById('btn-save-address')?.addEventListener('click', () => {
    clearFieldErrors(modalAddressEl);

    const logradouro = val('a-logradouro');
    const cep = val('a-cep');

    if (!logradouro && !cep) {
        document.getElementById('a-logradouro').classList.add('is-invalid');
        document.getElementById('a-cep').classList.add('is-invalid');
        showError(new Error('Informe ao menos o CEP ou o logradouro'));
        return;
    }

    enderecosPendentes.push({
        tempId: nextTempId++,
        nome: val('a-nome'),
        cep,
        logradouro,
        numero: val('a-numero'),
        complemento: val('a-complemento'),
        bairro: val('a-bairro'),
        cidade: val('a-cidade'),
        estado: val('a-estado').toUpperCase(),
    });

    renderAddressTable();
    modalAddress.hide();
});

// renderiza a tabela assim que a página carrega (cobre o caso de edição com endereços já existentes)
renderAddressTable();

// ── Salvar cadastro completo ──────────────────────────────────────────────

const form = document.getElementById('form');
const actionInput = document.getElementById('action');
const idInput = document.getElementById('id');

async function saveAll() {
    clearFieldErrors(form);

    const action = actionInput.value; // 'insert' | 'update'
    const id = idInput.value;

    const payload = {
        id: id || null,
        usuario: {
            tipo: val('tipo'),
            cpf_cnpj: val('cpf_cnpj'),
            rg_ie: val('rg_ie'),
            nome: val('nome'),
            observacoes: val('observacoes'),
            ativo: document.getElementById('ativo').checked,
        },
        // manda só os campos relevantes, sem o tempId (que é só controle de UI)
        enderecos: enderecosPendentes.map(({ tempId, ...resto }) => resto),
        contatos: {
            telefone: val('cont_telefone'),
            email: val('cont_email'),
        },
    };

    const btn = document.getElementById('insert');
    btn.disabled = true;

    try {
        const url = action === 'update' ? '/usuarios/atualizar' : '/usuarios/inserir';
        const result = await postJson(url, payload);

        await showSuccess('Cadastro salvo com sucesso!');

        if (action === 'insert') {
            window.location.href = `/usuarios/detalhes/${result.id}`;
        } else {
            window.location.reload();
        }
    } catch (err) {
        if (err.errors) {
            const flatErrors = {};
            Object.entries(err.errors).forEach(([key, msg]) => {
                flatErrors[key.replace('usuario.', '')] = msg;
            });
            applyFieldErrors(flatErrors, form);
        }
        showError(err);
    } finally {
        btn.disabled = false;
    }
}

document.getElementById('insert')?.addEventListener('click', saveAll);

// ── DataTables na lista de usuários (se a tabela existir na página) ──────

const tableUsersList = document.getElementById('table-users');
if (tableUsersList) {
    $(tableUsersList).DataTable({
        ajax: {
            url: '/usuarios/listingdata',
            type: 'POST',
        },
        columns: [
            { data: 'nome' },
            { data: 'cpf_cnpj' },
            { data: 'tipo' },
            {
                data: 'ativo',
                render: (val) => val ? 'Sim' : 'Não',
            },
            {
                data: 'id',
                render: (id) => `
                    <a href="/usuarios/detalhes/${id}" class="btn btn-sm btn-success">
                        <i class="bi bi-pencil"></i>
                    </a>
                `,
                orderable: false,
            },
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
        },
    });
}