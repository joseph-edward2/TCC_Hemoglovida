const filtrosPedidos = document.getElementById('filtrosPedidos');
filtrosPedidos.addEventListener('change', () => filtrosPedidos.requestSubmit());

document.addEventListener('click', (evento) => {
  const separar = evento.target.closest('button[data-bolsas]');
  if (separar) {
    abrirSeparacao(separar.dataset.bolsas);
    return;
  }
  const abrirModal = evento.target.closest('button[data-abrir-modal]');
  if (abrirModal) {
    const modal = document.getElementById(abrirModal.getAttribute('aria-controls'));
    modal.querySelector('form')?.reset();
    modal.showModal();
    return;
  }

  const link = evento.target.closest('a[data-lista]');
  if (!link || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;

  evento.preventDefault();
  if (!link.hasAttribute('aria-busy')) carregarMais(link);
});

async function abrirSeparacao(url) {
  const modal = document.getElementById('separar-pedido');
  const opcoes = document.getElementById('opcoes-bolsas');
  const controle = new AbortController();
  modal.addEventListener('close', () => controle.abort(), { once: true });
  opcoes.textContent = 'Buscando bolsas disponíveis…';
  modal.showModal();
  try {
    const resposta = await fetch(url, { signal: controle.signal, cache: 'no-store' });
    if (!resposta.ok || resposta.redirected) throw new Error();
    const html = await resposta.text();
    if (!controle.signal.aborted) opcoes.innerHTML = html;
  } catch {
    if (!controle.signal.aborted) opcoes.textContent = 'Não foi possível consultar as bolsas. Atualize os pedidos e tente novamente.';
  }
}

async function carregarMais(link) {
  link.setAttribute('aria-busy', 'true');
  try {
    const resposta = await fetch(link.href);
    if (!resposta.ok || resposta.redirected) {
      window.location.assign(resposta.url || link.href);
      return;
    }

    const pagina = new DOMParser().parseFromString(await resposta.text(), 'text/html');
    const lista = document.getElementById(link.dataset.lista);
    const novos = pagina.getElementById(lista.id);
    if (!novos) {
      window.location.assign(link.href);
      return;
    }

    for (const pedido of novos.querySelectorAll('article')) {
      if (!document.getElementById(pedido.id)) lista.append(pedido);
    }

    const proximo = pagina.querySelector('a[data-lista="' + lista.id + '"]');
    if (proximo) {
      link.href = proximo.href;
    } else {
      link.remove();
    }
  } catch {
    window.location.assign(link.href);
  } finally {
    link.removeAttribute('aria-busy');
  }
}

const aviso = document.getElementById('toast');
document.addEventListener('change', (evento) => {
  const form = evento.target.closest('form[data-reserva]');
  if (!form) return;
  const quantidade = form.querySelectorAll('input:checked').length;
  const confirmar = form.querySelector('button[type="submit"]');
  if (!confirmar) return;
  form.querySelector('output').value = quantidade;
  confirmar.disabled = quantidade !== Number(form.dataset.reserva);
});

document.addEventListener('formdata', (evento) => {
  if (!evento.target.matches('form[data-reserva]')) return;
  const dados = evento.formData;
  for (const isbt of dados.getAll('unidades[]')) {
    const campo = 'bolsas[' + isbt + ']';
    dados.set(campo, Number(dados.get(campo)) + 1);
  }
  dados.delete('unidades[]');
});
if (aviso) setTimeout(() => aviso.classList.remove('show'), 3000);
