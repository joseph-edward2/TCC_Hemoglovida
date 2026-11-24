// ---------- TOAST ----------
let toastTimer;
function showToast(msg){
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=> toast.classList.remove('show'), 2600);
}

// ---------- CAMPOS "ALTERAR" (email / telefone) ----------
// Os campos já são editáveis; o botão apenas confirma a alteração.
document.querySelectorAll('.btn-alterar').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    showToast('Informação atualizada com sucesso.');
  });
});

// ---------- EDITAR FOTO ----------
function triggerPhotoChange(){
  showToast('Selecione uma nova foto de perfil (funcionalidade de upload simulada).');
}
document.getElementById('photoEditBtn').addEventListener('click', triggerPhotoChange);
document.getElementById('editPhotoLink').addEventListener('click', (e)=>{
  e.preventDefault();
  triggerPhotoChange();
});

// ---------- MODAL ALTERAR SENHA ----------
const passwordOverlay = document.getElementById('passwordOverlay');
document.getElementById('changePasswordBtn').addEventListener('click', ()=>{
  document.getElementById('currentPass').value = '';
  document.getElementById('newPass').value = '';
  document.getElementById('confirmPass').value = '';
  passwordOverlay.classList.add('show');
});
document.getElementById('passwordClose').addEventListener('click', ()=> passwordOverlay.classList.remove('show'));
passwordOverlay.addEventListener('click', (e)=>{ if(e.target === passwordOverlay) passwordOverlay.classList.remove('show'); });

document.getElementById('savePasswordBtn').addEventListener('click', ()=>{
  const current = document.getElementById('currentPass').value;
  const newPass = document.getElementById('newPass').value;
  const confirm = document.getElementById('confirmPass').value;

  if(!current || !newPass || !confirm){
    showToast('Preencha todos os campos de senha.');
    return;
  }
  if(newPass !== confirm){
    showToast('A confirmação não coincide com a nova senha.');
    return;
  }
  if(newPass.length < 6){
    showToast('A nova senha deve ter pelo menos 6 caracteres.');
    return;
  }
  passwordOverlay.classList.remove('show');
  showToast('Senha alterada com sucesso.');
});

// ---------- SALVAR ALTERAÇÕES ----------
document.getElementById('saveBtn').addEventListener('click', ()=>{
  showToast('Alterações salvas com sucesso!');
});

