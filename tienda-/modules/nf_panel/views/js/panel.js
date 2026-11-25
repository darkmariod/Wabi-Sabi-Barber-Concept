/**
 * NatuFitness "Mi Tienda" panel: confirmation dialogs and selected-photos label.
 */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-nfp-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-nfp-confirm'))) {
        event.preventDefault();
      }
    });
  });

  var input = document.getElementById('nfp-photos-input');
  var label = document.querySelector('[data-nfp-upload-label]');
  if (input && label) {
    var original = label.textContent;
    input.addEventListener('change', function () {
      var count = input.files ? input.files.length : 0;
      label.textContent = count === 0 ? original : (count === 1 ? '1 foto elegida' : count + ' fotos elegidas');
    });
  }

  // Avoid double submits on slow mobile connections.
  document.querySelectorAll('.nfp form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (event.defaultPrevented) {
        return;
      }
      if (form.dataset.nfpSubmitted) {
        event.preventDefault();
        return;
      }
      form.dataset.nfpSubmitted = '1';
    });
  });
});

// Catálogos: "Copiar enlace" copies the store link of the catalog.
document.addEventListener('click', function (event) {
  var button = event.target.closest('.js-nfp-copy');
  if (!button) { return; }
  var url = button.getAttribute('data-copy');
  var done = function () {
    var label = button.lastChild;
    var original = label.textContent;
    label.textContent = 'Enlace copiado';
    setTimeout(function () { label.textContent = original; }, 1800);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copia este enlace:', url); });
  } else {
    window.prompt('Copia este enlace:', url);
  }
});
