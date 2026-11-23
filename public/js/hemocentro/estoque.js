(function () {
  "use strict";

  const TIPOS_SANGUINEOS = ["O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"];

  document.addEventListener("DOMContentLoaded", setupRegistrarEntrada);

  function setupRegistrarEntrada() {
    const btn = document.querySelector(".page-actions .btn-primary");
    if (!btn) return;

    const modal = criarModalEntrada();
    document.body.appendChild(modal);

    const form = modal.querySelector("#entradaForm");
    const inputColeta = form.querySelector("[name='coleta']");
    const inputValidade = form.querySelector("[name='validade']");

    function abrirModal() {
      form.reset();
      const hoje = new Date();
      const validade = new Date(hoje);
      validade.setDate(validade.getDate() + 35);
      inputColeta.value = paraInputDate(hoje);
      inputValidade.value = paraInputDate(validade);
      modal.hidden = false;
    }

    function fecharModal() {
      modal.hidden = true;
    }

    btn.addEventListener("click", abrirModal);
    modal.querySelector(".modal-close").addEventListener("click", fecharModal);
    modal.querySelector("#entradaCancelar").addEventListener("click", fecharModal);
    modal.addEventListener("click", (e) => {
      if (e.target === modal) fecharModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modal.hidden) fecharModal();
    });

    form.addEventListener("submit", (e) => {
      e.preventDefault();

      const dados = new FormData(form);
      const tipo = dados.get("tipo");
      const quantidade = parseInt(dados.get("quantidade"), 10) || 1;
      const coleta = dados.get("coleta");
      const validade = dados.get("validade");
      const idDoacao = dados.get("idDoacao");

      alert([
        `Prévia de ${quantidade} bolsa(s) de ${tipo} da doação ${idDoacao}.`,
        `Coleta em ${paraDataBR(coleta)} e validade até ${paraDataBR(validade)}.`,
      ].join("\n"));
    });
  }

  function criarModalEntrada() {
    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";
    overlay.hidden = true;

    const opcoesTipo = TIPOS_SANGUINEOS.map((t) => `<option value="${t}">${t}</option>`).join("");

    overlay.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <h3>Registrar Entrada</h3>
        <button type="button" class="modal-close" aria-label="Fechar"><i class="ph ph-x"></i></button>
      </div>

      <form class="modal-body" id="entradaForm">
        <label>Nome do Doador
          <input type="text" id="entradaNomeDoador" name="nomeDoador" required>
        </label>

        <label>Caderneta do Doador
          <input type="text" id="entradaCaderneta" name="caderneta" required>
        </label>

        <label>Data de coleta
        <input type="date" id="Coleta" name="coleta" required>
        </label>

        <label>Data de validade
          <input type="date" id="Validade" name="validade" required>
        </label>

        <label>Tipo sanguíneo (ABO/Rh)
          <select id="Tipo" name="tipo" required>
            <option value="">Selecione</option>
            ${opcoesTipo}
          </select>
        </label>

        <label>Número de identificação da doação (ISBT 128)
          <input type="text" id="IdDoacao" name="idDoacao" placeholder="A000 00 000000 00" required>
        </label>

        <label>Quantidade de bolsas
          <input type="number" id="Quantidade" name="quantidade" min="1" value="1" required>
        </label>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline" id="entradaCancelar">Cancelar</button>
          <button type="submit" class="btn btn-primary">Visualizar prévia</button>
        </div>
      </form>
    </div>
    `;

    return overlay;
  }

  function paraInputDate(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, "0");
    const dia = String(data.getDate()).padStart(2, "0");
    return `${ano}-${mes}-${dia}`;
  }

  function paraDataBR(isoDate) {
    const [ano, mes, dia] = isoDate.split("-");
    return `${dia}/${mes}/${ano}`;
  }
})();
