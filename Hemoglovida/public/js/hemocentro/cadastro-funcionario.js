/* ======================================================
   HEMOGLOVIDA — CADASTRO DE FUNCIONÁRIO (HEMOCENTRO)
   ======================================================
   Arquivo independente: não depende de base.js.
   O envio é real: o form postinga na rota do atributo action.
   ====================================================== */

const toggleBtn = document.getElementById('toggleSenha');
const senhaInput = document.getElementById('senha_funcionario');
const cpfInput = document.getElementById('cpf_funcionario');

if (toggleBtn && senhaInput) {
  toggleBtn.addEventListener('click', function () {
    const estaOculta = senhaInput.type === 'password';

    senhaInput.type = estaOculta ? 'text' : 'password';
    toggleBtn.setAttribute(
      'aria-label',
      estaOculta ? 'Ocultar senha' : 'Mostrar senha'
    );
  });
}

if (cpfInput) {
  cpfInput.addEventListener('input', function () {
    const digitos = cpfInput.value.replace(/\D/g, '').slice(0, 11);

    cpfInput.value = digitos
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2');
  });
}
