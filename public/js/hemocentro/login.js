const loginInput = document.getElementById('login');

loginInput.addEventListener('input', function () {
  const digits = this.value.trim().replace(/[.-]/g, '');
  if (!/^\d{0,11}$/.test(digits)) {
    return;
  }

  this.value = digits.length <= 7 ? digits : digits
    .replace(/^(\d{3})(\d{3})(\d)/, '$1.$2.$3')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
});

const toggleBtn = document.getElementById('toggleSenha');
const senhaInput = document.getElementById('senha');

toggleBtn.addEventListener('click', function () {
  const isPassword = senhaInput.type === 'password';
  senhaInput.type = isPassword ? 'text' : 'password';
  this.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
});

const form = document.getElementById('loginForm');
const msgEl = document.getElementById('msg');

form.addEventListener('submit', function (e) {
  msgEl.hidden = true;
  msgEl.textContent = '';
  msgEl.className = 'msg';

  const doc = loginInput.value.trim();
  const cnesValido = /^\d{7}$/.test(doc) && doc !== '0000000';

  if (!cnesValido && !validarCpf(doc)) {
    e.preventDefault();
    showError('Informe um CNES de 7 dígitos ou um CPF válido.');
    loginInput.focus();
    return;
  }

  if (!senhaInput.value) {
    e.preventDefault();
    showError('Informe uma senha válida.');
    senhaInput.focus();
    return;
  }

  const btn = form.querySelector('.btn');
  btn.disabled = true;
  btn.textContent = 'Entrando…';
});

function validarCpf(value) {
  if (!/^(?:\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2})$/.test(value)) {
    return false;
  }

  const cpf = value.replace(/[.-]/g, '');
  if (/^(\d)\1{10}$/.test(cpf)) {
    return false;
  }

  return calcularDigitoCpf(cpf.slice(0, 9)) === Number(cpf[9])
    && calcularDigitoCpf(cpf.slice(0, 10)) === Number(cpf[10]);
}

function calcularDigitoCpf(base) {
  const soma = [...base].reduce((total, digito, indice) =>
    total + Number(digito) * (base.length + 1 - indice), 0);
  const resto = soma % 11;

  return resto < 2 ? 0 : 11 - resto;
}

function showError(text) {
  msgEl.textContent = text;
  msgEl.className = 'msg msg--error';
  msgEl.hidden = false;
}
