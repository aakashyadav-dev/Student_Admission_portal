/* Progressive enhancement only: every action also works without JavaScript.
   Forms marked data-confirm="..." ask for confirmation before they are submitted. */
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { ev.preventDefault(); }
});
