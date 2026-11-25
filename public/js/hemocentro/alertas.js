const imagem = document.querySelector('.campaign-image-picker input');
imagem.addEventListener('change', () => {
  document.getElementById('imagem-arquivo').textContent = imagem.files[0]?.name ?? 'JPG, PNG ou WebP · Até 2 MB';
});

const busca = document.getElementById('buscaAlerta');
const linhas = document.querySelectorAll('#alertsBody [data-alerta]');
const buscaVazia = document.getElementById('buscaVazia');

busca.addEventListener('input', () => {
  const termo = busca.value.trim().toLocaleLowerCase('pt-BR');
  let visiveis = 0;

  for (const linha of linhas) {
    linha.hidden = !linha.textContent.toLocaleLowerCase('pt-BR').includes(termo);
    if (!linha.hidden) {
      visiveis++;
    }
  }

  buscaVazia.hidden = linhas.length === 0 || visiveis > 0;
});

document.querySelectorAll('form[data-excluir]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm('Excluir este alerta?')) {
      event.preventDefault();
    }
  });
});
