import { Modal } from 'bootstrap';
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";
import FindCompany from "../components/find-company.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const form = document.getElementById('form');
const Insert = document.getElementById('insert');
const Cnpj = document.getElementById('cpf_cnpj')
const Adresse = document.getElementById('btn-add-address');
const btnSaveAddress = document.getElementById('btn-save-address');
const cep = document.getElementById('a-cep');
const Modaladresse = document.getElementById('modal-address');
const Modalclean = Modaladresse ? new Modal(Modaladresse) : null;



// ── Salvar cliente ────────────────────────────────────────────────────────────
async function applyChanges() {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Corrija os erros antes de salvar.',
            timer: 3000,
            timerProgressBar: true
        });
        $('button').prop('disabled', false);
        return;
    }

    const requests = new Requests();
    try {
        const response = Action.value !== 'e'
            ? await requests.setForm('form').post('/cliente/inserir')
            : await requests.setForm('form').post('/cliente/atualizar');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Erro ao salvar.',
                timer: 3000,
                timerProgressBar: true
            });
            return;
        }

        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg,
                timer: 3000,
                timerProgressBar: true
            })
                .then(() => { window.location.href = '/cliente/lista'; });
            return;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', `${window.location.origin}/cliente/detalhes/${response.id}`);

        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg,
            timer: 3000,
            timerProgressBar: true
        })
            .then(() => { window.location.reload(); });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message,
            timer: 3000,
            timerProgressBar: true
        });
    } finally {
        $('button').prop('disabled', false);
    }
}

async function listAddresses() {

    const requests = new Requests();
    try {
        const response = await requests.post(`/cliente/${Id.value}/enderecoslista`);

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Erro ao listar endereços.',
                timer: 3000,
                timerProgressBar: true
            });
            return;
        }

        const addressesContainer = document.getElementById('table-addresses');

        // Verifica se a lista veio vazia
        if (!response.data || response.data.length === 0) {
            addressesContainer.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum endereço cadastrado.</td></tr>';
            return;
        }

        let HTML = '';
        response.data.forEach(item => {
            HTML += `
                <tr id="address-${item.id}">
                    <td>${item.label}</td>
                    <td>${item.logradouro}</td>
                    <td>${item.numero}</td>
                    <td>${item.bairro}</td>
                    <td>${item.cidade} / ${item.estado}</td>
                    <td>${item.principal ? '<span class="badge bg-success">Sim</span>' : 'Não'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(${item.id})">Excluir</button>
                    </td>
                </tr>           
            `;
        });

        addressesContainer.innerHTML = HTML;

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message,
            timer: 3000,
            timerProgressBar: true
        });
    }
}

Cnpj.addEventListener('blur', async () => {
    if (Cnpj.value.trim() === '' || Cnpj.value.replace(/\D/g, '').length < 14) {
        return;
    }
    const findCompany = new FindCompany({ cnpjField: 'cpf_cnpj', cnaeValue: 'cnae', cnaeSearch: 'codigoAtividadeEconomica' })
    const response = await findCompany.FindCompanyData();

    const Ie = response?.estabelecimento?.inscricoes_estaduais[0]?.inscricao_estadual;
    const Nome = response?.estabelecimento?.nome_fantasia ?? response?.razao_social;

    document.getElementById('nome').value = Nome;
    document.getElementById('rg_ie').value = Ie;

});

document.addEventListener('DOMContentLoaded', async () => {

    // 1. Máscara dinâmica para CPF/CNPJ no mesmo campo
    const cpfCnpjInput = document.getElementById('cpf_cnpj'); // Certifique-se de que o id no HTML seja este
    if (cpfCnpjInput) {
        Inputmask({
            mask: ['999.999.999-99', '99.999.999/9999-99'],
            keepStatic: true, // Evita que a máscara mude agressivamente enquanto digita
            clearIncomplete: false
        }).mask(cpfCnpjInput);
    }

    // 2. Máscara de CEP no modal de endereços
    const cepInput = document.getElementById('a-cep');
    if (cepInput) {
        Inputmask('99999-999').mask(cepInput);
    }

    // 3. Máscara dinâmica para Celular/Telefone no modal de contatos
    const telInput = document.getElementById('c-contato');
    if (telInput) {
        Inputmask({
            mask: ['(99) 9999-9999', '(99) 99999-9999'],
            keepStatic: true
        }).mask(telInput);
    }
    if (Action.value === 'e') await listAddresses();
});

Insert.addEventListener('click', applyChanges);

// ── Cadastro de Endereços ─────────────────────────────────────────────────────────────────

// Centralizador de alertas para evitar repetição de código
const toast = (icon, title, text, cb) => Swal.fire({ icon, title, text, timer: 2000, timerProgressBar: true }).then(cb);


// 1. Abrir e Limpar Modal
Adresse.addEventListener('click', () => {
    Modalclean?.show();
});

// 2. Busca Automática de CEP (ViaCEP integrada diretamente)
cep.addEventListener('blur', async (e) => {
    const cep = e.target.value.replace(/\D/g, '');
    if (cep.length !== 8) return;

    try {
        const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        if (!res.ok) return;

        const data = await res.json();
        if (data.erro) return toast('warning', 'Atenção', 'CEP não encontrado.');

        // Preenche os campos automaticamente usando os IDs existentes
        document.getElementById('a-logradouro').value = data.logradouro ?? '';
        document.getElementById('a-bairro').value = data.bairro ?? '';
        document.getElementById('a-cidade').value = data.localidade ?? '';
        document.getElementById('a-estado').value = data.uf ?? '';

        // Foca no campo número automaticamente para agilizar a digitação
        document.getElementById('a-numero')?.focus();
    } catch (err) {
        console.error("Erro CEP:", err);
    }
});

// 3. Salvar Endereço (Captura automática via FormData)
btnSaveAddress.addEventListener('click', async () => {
    const requests = new Requests();
    try {
        // O .SetForm('form') já captura todos os inputs e o estado do checkbox sozinho
        const response = await requests.setForm('form-address').post(`/cliente/${Id.value}/endereco`);

        if (!response.status) {
            throw new Error(response.msg);
        }

        modalAddress.hide();
        toast('success', 'Sucesso', response.msg);
        await listAddresses();

    } catch (e) {
        toast('error', 'Erro', e.message);
    }
});

async function deleteAddress(addressId) {
    document.getElementById('id_endereco').value = addressId;
    document.getElementById('address-' + addressId)?.remove(); // Remove a linha da tabela imediatamente para feedback visual
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post(`/cliente/endereco/${addressId}`);
        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: `Restrição: ${error}`,
                timer: 3000,
                timerProgressBar: true,
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error}`,
            timer: 3000,
            timerProgressBar: true,
        });
    }
}

/*async function ShowModal(id) {
    Id.value = id;
    Swal.fire({
        title: "Atenção!",
        text: "Deseja realmente excluir este registro?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Excluir"
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await deleteAddress();
            if (!response.status) {
                Swal.fire({
                    title: "Erro!",
                    text: response.mesg,
                    icon: "error",
                    timer: 3000,
                    timerProgressBar: true
                });
                return;
            }
            Swal.fire({
                title: "Removido!",
                text: "Registro excluído com sucesso.",
                icon: "success",
                timer: 2000,
                timerProgressBar: true
            }).then(async () => {
                table.ajax.reload();
            });
        }
    });
}*/

window.ShowModal = ShowModal;

// 4. Deletar Endereço
window.deleteAddress = deleteAddress;

// ── Contatos ──────────────────────────────────────────────────────────────────
const elModalContact = document.getElementById('modal-contact');
const modalContact = elModalContact ? new Modal(elModalContact) : null;

document.getElementById('btn-add-contact')?.addEventListener('click', () => {
    document.getElementById('c-tipo').value = 'telefone';
    document.getElementById('c-nome').value = '';
    document.getElementById('c-contato').value = '';
    document.getElementById('c-principal').checked = false;
    modalContact.show();
});

document.getElementById('btn-save-contact')?.addEventListener('click', async () => {
    const contato = document.getElementById('c-contato').value.trim();
    if (!contato) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Contato é obrigatório.', timer: 2000, timerProgressBar: true });
        return;
    }

    const body = new URLSearchParams({
        tipo: document.getElementById('c-tipo').value,
        nome: document.getElementById('c-nome').value,
        contato,
        principal: document.getElementById('c-principal').checked ? 'true' : 'false',
    });

    try {
        const res = await fetch(`/cliente/${Id.value}/contato`, { method: 'POST', body });
        const data = await res.json();

        if (!data.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: data.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        modalContact.hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: data.msg, timer: 2000, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
    }
});

async function deletecontat() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/cliente/delete');
        return response;
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error}`,
            timer: 3000,
            timerProgressBar: true,
        });
    }
}

async function ShowModal(id) {
    Id.value = id;
    Swal.fire({
        title: "Atenção!",
        text: "Deseja realmente excluir este registro?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Excluir"
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await deletecontat();
            if (!response.status) {
                Swal.fire({
                    title: "Erro!",
                    text: response.mesg,
                    icon: "error",
                    timer: 3000,
                    timerProgressBar: true
                });
                return;
            }
            Swal.fire({
                title: "Removido!",
                text: "Registro excluído com sucesso.",
                icon: "success",
                timer: 2000,
                timerProgressBar: true
            }).then(async () => {
                table.ajax.reload();
            });
        }
    });
}