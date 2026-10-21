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
