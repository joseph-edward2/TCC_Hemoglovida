const loginInput = document.getElementById('login');

loginInput.addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '').slice(0, 14)
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2');
});

const toggleBtn = document.getElementById('toggleSenha');
const senhaInput = document.getElementById('senha');

toggleBtn.addEventListener('click', function () {
  const isPassword = senhaInput.type === 'password';
  senhaInput.type = isPassword ? 'text' : 'password';
  this.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
});
