(function () {
  "use strict";

  const state = {
    searchTerm: "",
    statusFilter: "all",
  };

  document.addEventListener("DOMContentLoaded", init);

  function init() {
    setupSearch();
    setupFilterDropdown();
    const list = document.querySelector(".triage-list");
    list.addEventListener("change", (event) => {
      if (event.target.matches('input[name="laudo"]') && event.target.files.length) {
        event.target.form.requestSubmit();
      }
    });
    list.addEventListener("submit", (event) => {
      if (!event.target.matches(".triage-action")) return;
      const code = event.target.closest(".triage-row").querySelector(".triage-code").textContent;
      const question = event.submitter?.value === "recusada"
        ? `Recusar a bolsa ${code}?`
        : `Liberar a bolsa ${code} para o estoque?`;
      if (!window.confirm(question)) event.preventDefault();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") document.querySelector(".filter-dropdown").hidden = true;
    });
  }
  function setupSearch() {
    const input = document.querySelector(".search-box input");
    if (!input) return;

    input.addEventListener("input", () => {
      state.searchTerm = normalize(input.value.trim());
      applyFilters();
    });
  }


  function setupFilterDropdown() {
    const filterBtn = document.querySelector(".btn-filter");
    if (!filterBtn) return;

    const wrapper = document.createElement("div");
    wrapper.className = "filter-wrapper";
    filterBtn.parentNode.insertBefore(wrapper, filterBtn);
    wrapper.appendChild(filterBtn);

    const dropdown = document.createElement("div");
    dropdown.className = "filter-dropdown";
    dropdown.hidden = true;
    dropdown.innerHTML = `
      <button type="button" class="filter-option is-active" data-value="all">Todos</button>
      <button type="button" class="filter-option" data-value="waiting">Aguardando exame</button>
      <button type="button" class="filter-option" data-value="attached">Exame anexado</button>
    `;
    wrapper.appendChild(dropdown);

    filterBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      dropdown.hidden = !dropdown.hidden;
    });

    dropdown.addEventListener("click", (e) => {
      const option = e.target.closest(".filter-option");
      if (!option) return;

      dropdown
        .querySelectorAll(".filter-option")
        .forEach((o) => o.classList.remove("is-active"));
      option.classList.add("is-active");

      state.statusFilter = option.dataset.value;
      applyFilters();
      dropdown.hidden = true;
    });

    document.addEventListener("click", () => {
      dropdown.hidden = true;
    });
  }


  function applyFilters() {
    const rows = document.querySelectorAll(".triage-row");
    let anyVisible = false;

    rows.forEach((row) => {
      const type = normalize(row.querySelector(".triage-type")?.textContent || "");
      const code = normalize(row.querySelector(".triage-code")?.textContent || "");
      const matchesSearch =
        !state.searchTerm || type.includes(state.searchTerm) || code.includes(state.searchTerm);

      const isAttached = row.querySelector(".badge-pill")?.classList.contains("badge-attached");
      const matchesStatus =
        state.statusFilter === "all" ||
        (state.statusFilter === "attached" && isAttached) ||
        (state.statusFilter === "waiting" && !isAttached);

      const visible = matchesSearch && matchesStatus;
      row.style.display = visible ? "" : "none";
      if (visible) anyVisible = true;
    });

    const emptyState = document.getElementById("filter-empty");
    if (emptyState) emptyState.hidden = anyVisible;
  }

  function normalize(str) {
    return str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
  }
})();
