import Requests from "../components/requests.js";
import Validate from "../components/validate.js";


//Validate.SetForm('form');
const buttonLogin = document.getElementById('buttonLogin');

document.getElementById('toggleSenha').addEventListener('click', function () {
    const input = document.getElementById('senha');
    const icon = this.querySelector('i');
    const visivel = input.type === 'text';

    input.type = visivel ? 'password' : 'text';
    icon.classList.toggle('fa-eye', visivel);
    icon.classList.toggle('fa-eye-slash', !visivel);
});
async function autenticar() {
    const isValid = Validate.SetForm('form').Validate();
    if (!isValid) {
        Swal.fire({
            icon: 'error',
            title: 'Atenção',
            text: 'Preencha todos os campos corretamente.',
            timer: 3000,

            timerProgressBar: true,
        });
        return;
    }

    $('button').prop('disabled', true);

    try {
        const response = await new Requests().setForm('form').post('/login');

        if (!response?.status) {
            Swal.fire({
                icon: 'error',
                title: 'Acesso negado',
                text: response?.message ?? 'Login ou senha incorretos.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        window.location.href = '/home';

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message ?? 'Não foi possível conectar ao servidor.',
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        $('button').prop('disabled', false);
    }
}

buttonLogin.addEventListener('click', async () => { await autenticar() });