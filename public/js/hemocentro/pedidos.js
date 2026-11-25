const filtrosPedidos = document.getElementById('filtrosPedidos');
filtrosPedidos.addEventListener('change', () => filtrosPedidos.requestSubmit());

document.addEventListener('click', (evento) => {
  const detalhes = evento.target.closest('button[data-detalhes]');
  if (detalhes) {
    document.getElementById(detalhes.getAttribute('aria-controls')).showModal();
    return;
  }

  const link = evento.target.closest('a[data-lista]');
  if (!link || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;

  evento.preventDefault();
  if (!link.hasAttribute('aria-busy')) carregarMais(link);
});

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
if (aviso) setTimeout(() => aviso.classList.remove('show'), 3000);
