/* The server enforces the same conditions; hidden fields never decide permissions. */
(() => {
  document.querySelectorAll('[data-issuer-form]').forEach(form => {
    const select=form.querySelector('[data-issuer-select]');
    const manual=form.querySelector('[data-manual-issuer]');
    const input=manual?.querySelector('input');
    if(!select||!manual||!input)return;
    const sync=()=>{const custom=select.value==='';manual.hidden=!custom;input.disabled=!custom;input.required=custom;};
    select.addEventListener('change',sync);sync();
  });
})();
