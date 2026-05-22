import Swal from "sweetalert2";
import Validate from "../components/validate.js";
import Request from "../components/requests.js";

// ─── Máscaras ─────────────────────────────────────────────────────────────────
Inputmask({ mask: '999.999.999-99' }).mask('#cpf');
Inputmask({ mask: '9999999' }).mask('#rg');
Inputmask({ mask: '(99) 9 9999-9999' }).mask('#telefone');

const buttonPreRegister = document.getElementById('btnCadastrar');

buttonPreRegister.addEventListener('click', async () => {
    const validou = Validate.SetForm('form').Validate();
    if (!validou) {
        Swal.fire({
            icon: 'error',
            title: 'Ops...',
            text: 'Preencha os campos corretamente!',
            timer: 2500,
            timerProgressBar: true
        });
        return;
    }

    const requests = new Request();
    const originalText = buttonPreRegister.textContent;

    try {
        buttonPreRegister.textContent = 'Cadastrando, por favor aguarde...';
        buttonPreRegister.disabled = true;

        const response = await requests.setForm('form').post('/cadastro');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Ops...',
                text: response.msg,
                timer: 2500,
                timerProgressBar: true
            });
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: response.msg,
            timer: 2500,
            timerProgressBar: true
        }).then(() => {
            window.location.href = '/login';
        });

    } catch (error) {
        const texto = error.data?.msg
            || error.message
            || 'Ocorreu um erro ao cadastrar o usuário!';

        Swal.fire({
            icon: 'error',
            title: 'Ops...',
            text: texto,
            timer: 2500,
            timerProgressBar: true
        });
    } finally {
        buttonPreRegister.disabled = false;
        buttonPreRegister.textContent = originalText;
    }
});