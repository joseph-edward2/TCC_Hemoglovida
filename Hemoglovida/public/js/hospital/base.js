/* ======================================================
   HEMOGLOVIDA — JS BASE
   ======================================================
   Funções que se REPETEM em várias páginas: enviar dados
   pra uma API, mostrar mensagens de erro/sucesso, deixar
   um botão em "carregando", selecionar chips e mostrar/
   ocultar senha.

   Importe SEMPRE ANTES do JS da página específica:

   <script src="../base.js"></script>
   <script src="js/login.js"></script>

   A ordem importa! O base.js precisa existir primeiro pras
   funções abaixo já estarem disponíveis quando o script da
   página for executado.
   ====================================================== */


/**
 * Lê o token CSRF da meta da página. As rotas usam sessão do Laravel,
 * então toda requisição precisa desse cabeçalho.
 */
function tokenCsrf() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

/**
 * Converte a resposta em objeto e transforma falhas HTTP em erros
 * legíveis, incluindo mensagens de validação do Laravel.
 */
async function tratarRespostaAPI(resposta) {
  const corpo = await resposta.json().catch(function () { return null; });

  if (!resposta.ok) {
    const erros = corpo && corpo.errors
      ? Object.values(corpo.errors).flat().join(' ')
      : '';

    throw new Error(erros || (corpo && corpo.message) || 'Não foi possível concluir a solicitação.');
  }

  return corpo;
}

/**
 * Busca dados (GET) na mesma origem da aplicação.
 *
 * Uso:
 *   const dados = await buscarDaAPI('/hospital/estoque/resumo');
 */
async function buscarDaAPI(url) {
  const resposta = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': tokenCsrf()
    }
  });

  return tratarRespostaAPI(resposta);
}

/**
 * Envia dados em JSON para a aplicação, com método configurável.
 * Mantém a assinatura antiga (url, dados) como POST.
 *
 * Uso:
 *   await enviarParaAPI(url, { titulo: 'x' });
 *   await enviarParaAPI(url, { status: 'disponivel' }, 'PATCH');
 *   await enviarParaAPI(url, new FormData(form)); // envia arquivos sem JSON
 */
async function enviarParaAPI(url, dados, metodo) {
  const metodoEfetivo = metodo || 'POST';
  const ehFormData = typeof FormData !== 'undefined' && dados instanceof FormData;
  const opcoes = {
    method: metodoEfetivo,
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': tokenCsrf()
    }
  };

  if (!ehFormData) {
    opcoes.headers['Content-Type'] = 'application/json';
  }

  if (dados !== undefined && metodoEfetivo !== 'GET') {
    opcoes.body = ehFormData ? dados : JSON.stringify(dados);
  }

  const resposta = await fetch(url, opcoes);

  return tratarRespostaAPI(resposta);
}


/**
 * Coloca um botão em estado de "carregando": desabilita e
 * troca o texto (ex: "Entrar" → "Entrando…").
 *
 * Devolve uma função "restaurar" — chame ela depois que a
 * ação terminar (dentro do finally, por exemplo), pra
 * voltar o botão ao normal.
 *
 * Uso:
 *   const restaurar = iniciarCarregamento(botao, 'Enviando…');
 *   // ...faz a requisição...
 *   restaurar();
 */
function iniciarCarregamento(botao, textoCarregando) {
  const textoOriginal = botao.textContent;
  botao.disabled = true;
  botao.textContent = textoCarregando;

  return function restaurar() {
    botao.disabled = false;
    botao.textContent = textoOriginal;
  };
}


/**
 * Mostra uma mensagem de erro ou sucesso dentro de um
 * elemento com a classe .msg (os estilos .msg--error e
 * .msg--success já existem no CSS da página de login).
 *
 * Uso:
 *   mostrarMensagem(msgEl, 'Login realizado com sucesso!', 'success');
 *   mostrarMensagem(msgEl, 'Senha incorreta.', 'error');
 */
function mostrarMensagem(elemento, texto, tipo) {
  elemento.textContent = texto;
  elemento.className = 'msg msg--' + tipo; // tipo: 'error' ou 'success'
  elemento.hidden = false;
}


/**
 * Liga o clique de "selecionar/desselecionar" em chips
 * dentro de um container (ex: escolher tipos sanguíneos).
 * Cada chip clicado ganha ou perde a classe "chip--active".
 *
 * Uso:
 *   ativarSelecaoDeChips(document.getElementById('chipsAlerta'));
 */
function ativarSelecaoDeChips(container) {
  if (!container) return;
  container.addEventListener('click', function (e) {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    chip.classList.toggle('chip--active');
  });
}


/**
 * Devolve o texto de todos os chips selecionados (com a
 * classe "chip--active") dentro de um container.
 *
 * Uso:
 *   chipsSelecionados(document.getElementById('chipsAlerta'))
 *   // -> ['O-', 'AB+']
 */
function chipsSelecionados(container) {
  if (!container) return [];
  const ativos = container.querySelectorAll('.chip--active');
  return Array.from(ativos).map(function (chip) { return chip.textContent.trim(); });
}


/**
 * Liga um botão de "olho" a um campo de senha, alternando
 * entre mostrar e ocultar o texto digitado.
 *
 * Uso:
 *   ativarToggleSenha(document.getElementById('toggleSenha'), senhaInput);
 */
function ativarToggleSenha(botao, input) {

  if (!botao || !input) return;

  botao.addEventListener('click', function () {

    const estaOculta = input.type === 'password';

    input.type = estaOculta ? 'text' : 'password';

    botao.setAttribute(
      'aria-label',
      estaOculta ? 'Ocultar senha' : 'Mostrar senha'
    );

    const icone = botao.querySelector('.ph-eye, .ph-eye-slash');

    if (icone) {

      icone.classList.toggle('ph-eye', !estaOculta);
      icone.classList.toggle('ph-eye-slash', estaOculta);

    }

  });

}
