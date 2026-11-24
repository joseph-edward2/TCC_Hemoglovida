/* ======================================================
   HEMOGLOVIDA — CADASTRO DE FUNCIONÁRIO (HOSPITAL)
   ======================================================
   Importe DEPOIS do base.js:
     <script src="../base.js"></script>
     <script src="cadastro-funcionario.js"></script>

   O envio é real: o form postinga na rota do atributo action.
   ====================================================== */

const toggleBtn = document.getElementById('toggleSenha');
const senhaInput = document.getElementById('senha_funcionario');
const cpfInput = document.getElementById('cpf_funcionario');

if (cpfInput) {
  cpfInput.addEventListener('input', function () {
    const digitos = cpfInput.value.replace(/\D/g, '').slice(0, 11);

    cpfInput.value = digitos
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2');
  });
}

ativarToggleSenha(toggleBtn, senhaInput);
