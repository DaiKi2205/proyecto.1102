let idAEliminar = null;

function confirmarEliminar(id, nombre) {
  idAEliminar = id;
  document.getElementById('modal-nombre').textContent = `"${nombre}" se eliminará permanentemente.`;
  document.getElementById('modal-overlay').classList.add('open');
}

function cerrarModal() {
  idAEliminar = null;
  document.getElementById('modal-overlay').classList.remove('open');
}

function ejecutarEliminar() {
  if (!idAEliminar) return;
  document.getElementById('eliminar-id').value = idAEliminar;
  document.getElementById('form-eliminar').submit();
}

// Cerrar modal haciendo click fuera de la tarjeta
document.addEventListener('click', (e) => {
  if (e.target.id === 'modal-overlay') cerrarModal();
});
