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
const Contact = document.getElementById('btn-add-contact');
const btnSaveAddress = document.getElementById('btn-save-address');
const btnSaveContact = document.getElementById('btn-save-contact');
const Cep = document.getElementById('a-cep');
const Modaladresse = document.getElementById('modal-address');
const Modalcontact = document.getElementById('modal-contact');
const Modalcleanadresses = Modaladresse ? new Modal(Modaladresse) : null;
const Modalcleancontact = Modalcontact ? new Modal(Modalcontact) : null;
const toast = (icon, title, text, cb) => Swal.fire({ icon, title, text, timer: 2000, timerProgressBar: true }).then(cb);


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

async function listContact() {
    const requests = new Requests();
    try {
        const response = await requests.post(`/cliente/${Id.value}/contatoslista`);

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Erro ao listar contatos.',
                timer: 3000,
                timerProgressBar: true
            });
            return;
        }

        const contactsContainer = document.getElementById('table-contacts');

        // Verifica se a lista veio vazia
        if (!response.data || response.data.length === 0) {
            contactsContainer.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum contato cadastrado.</td></tr>';
            return;
        }

        let HTML = '';
        response.data.forEach(item => {
            HTML += `
                <tr id="contact-${item.id}">
                    <td>${item.tipo}</td>
                    <td>${item.label}</td>
                    <td>${item.contato}</td>
                    <td>${item.principal ? '<span class="badge bg-success">Sim</span>' : 'Não'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteContact(${item.id})">Excluir</button>
                    </td>
                </tr>           
            `;
        });

        contactsContainer.innerHTML = HTML;

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

async function deleteContact(contactId) {
    document.getElementById('id_contato').value = contactId;
    document.getElementById('contact-' + contactId)?.remove(); // Remove a linha da tabela imediatamente para feedback visual
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post(`/cliente/contato/${contactId}`);
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

Cep.addEventListener('blur', async (e) => {
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
    if (Action.value === 'e') await listContact();
});

Adresse.addEventListener('click', () => {
    Modalcleanadresses?.show();
});
Contact.addEventListener('click', () => {
    Modalcleancontact?.show();
});

btnSaveAddress.addEventListener('click', async () => {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post(`/cliente/${Id.value}/endereco`);

        if (!response.status) {
            throw new Error(response.msg);
        }
        toast('success', 'Sucesso', response.msg);
        await listAddresses();
        //Limpa todos os inputs do modal de endereço
        document.querySelectorAll('#modal-address input').forEach(input => input.value = '');
        Modalcleanadresses?.hide();
    } catch (e) {
        toast('error', 'Erro', e.message);
    }
});

btnSaveContact.addEventListener('click', async () => {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post(`/cliente/${Id.value}/contato`);

        if (!response.status) {
            throw new Error(response.msg);
        }
        toast('success', 'Sucesso', response.msg);
        await listContact();
        //Limpa todos os inputs do modal de contatos
        document.querySelectorAll('#modal-contact input').forEach(input => input.value = '');
        Modalcleancontact?.hide();
    } catch (e) {
        toast('error', 'Erro', e.message);
    }
});

Insert.addEventListener('click', applyChanges);

window.deleteAddress = deleteAddress;
window.deleteContact = deleteContact;