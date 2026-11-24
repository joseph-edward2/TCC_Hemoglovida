const loginInput = document.getElementById('login');
const senhaInput = document.getElementById('senha');
const toggleBtn = document.getElementById('toggleSenha');
const form = document.getElementById('loginForm');
const msgEl = document.getElementById('msg');

loginInput.addEventListener('input', function () {
  const digits = this.value.replace(/\D/g, '').slice(0, 14);

  if (digits.length <= 11) {
    this.value = digits.length <= 7
      ? digits
      : digits
        .replace(/^(\d{3})(\d{3})(\d)/, '$1.$2.$3')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

    return;
  }

  this.value = digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2');
});

toggleBtn.addEventListener('click', function () {
  const isPassword = senhaInput.type === 'password';
  senhaInput.type = isPassword ? 'text' : 'password';
  this.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
});

form.addEventListener('submit', function (e) {
  msgEl.hidden = true;
  msgEl.textContent = '';
  msgEl.className = 'msg';

  if (!validarDocumento(loginInput.value.trim())) {
    e.preventDefault();
    showError('Informe um CNPJ de 14 dígitos ou um CPF válido.');
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

function validarDocumento(value) {
  const digits = value.replace(/[.\/-]/g, '');

  if (/^\d{11}$/.test(digits)) {
    return validarCpf(digits);
  }

  if (/^\d{14}$/.test(digits)) {
    return validarCnpj(digits);
  }

  return false;
}

function validarCpf(value) {
  if (/^(\d)\1{10}$/.test(value)) {
    return false;
  }

  return calcularDigitoCpf(value.slice(0, 9)) === Number(value[9])
    && calcularDigitoCpf(value.slice(0, 10)) === Number(value[10]);
}

function calcularDigitoCpf(base) {
  const soma = [...base].reduce((total, digito, indice) =>
    total + Number(digito) * (base.length + 1 - indice), 0);
  const resto = soma % 11;

  return resto < 2 ? 0 : 11 - resto;
}

function validarCnpj(value) {
  if (/^(\d)\1{13}$/.test(value)) {
    return false;
  }

  return calcularDigitoCnpj(value.slice(0, 12)) === Number(value[12])
    && calcularDigitoCnpj(value.slice(0, 13)) === Number(value[13]);
}

function calcularDigitoCnpj(base) {
  const weights = base.length === 12
    ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
    : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  const soma = [...base].reduce((total, digito, indice) =>
    total + Number(digito) * weights[indice], 0);
  const resto = soma % 11;

  return resto < 2 ? 0 : 11 - resto;
}

function showError(text) {
  msgEl.textContent = text;
  msgEl.className = 'msg msg--error';
  msgEl.hidden = false;
}
