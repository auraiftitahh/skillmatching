/* Skill Matching — dropdown berpencarian universal dan konfirmasi toggle akun.
   Tanpa dependensi eksternal. Form tetap memakai nilai select asli saat dikirim. */
(() => {
  'use strict';
  let opened = null;
  let seq = 0;
  const fold = value => (value || '').toLocaleLowerCase('id').normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  function closeCurrent() {
    if (!opened) return;
    opened.panel.hidden = true;
    opened.trigger.setAttribute('aria-expanded', 'false');
    opened = null;
  }
  function enhance(select) {
    if (select.closest('.bulk-table')) return;
    if (select.dataset.searchEnhanced === '1' || select.multiple || select.disabled) return;
    select.dataset.searchEnhanced = '1';
    const host = document.createElement('div');
    host.className = 'searchable-select';
    select.parentNode.insertBefore(host, select);
    host.appendChild(select);
    select.classList.add('searchable-select-native');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'searchable-select-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.closest('label')?.childNodes[0]?.textContent?.trim() || 'Pilih opsi');
    const id = `search-select-${++seq}`;
    trigger.setAttribute('aria-controls', id);
    const caption = document.createElement('span');
    caption.className = 'searchable-caption';
    const chevron = document.createElement('span');
    chevron.className = 'select-chevron';
    chevron.textContent = '⌄';
    trigger.append(caption, chevron);
    host.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'searchable-panel';
    panel.hidden = true;
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'searchable-input';
    search.placeholder = 'Cari pilihan...';
    search.setAttribute('aria-label', 'Cari pilihan dalam daftar');
    search.setAttribute('autocomplete', 'off');
    const choices = document.createElement('div');
    choices.id = id;
    choices.className = 'searchable-choices';
    choices.setAttribute('role', 'listbox');
    panel.append(search, choices);
    host.appendChild(panel);
    let cursor = -1;

    function sync() {
      const opt = select.selectedOptions[0];
      caption.textContent = opt ? opt.textContent.trim() : 'Pilih...';
      trigger.classList.toggle('is-placeholder', !select.value);
    }
    function render() {
      const query = fold(search.value.trim());
      const options = Array.from(select.options).filter(o => !o.disabled && (!query || fold(o.textContent).includes(query)));
      choices.replaceChildren();
      cursor = options.length ? 0 : -1;
      if (!options.length) {
        const empty = document.createElement('div');
        empty.className = 'searchable-empty'; empty.textContent = 'Tidak ada pilihan yang cocok.';
        choices.appendChild(empty); return;
      }
      options.forEach((option, idx) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'searchable-option';
        b.setAttribute('role', 'option');
        b.setAttribute('aria-selected', String(option.selected));
        b.textContent = option.textContent.trim();
        if (option.selected) b.classList.add('is-selected');
        if (idx === cursor) b.classList.add('is-highlighted');
        b.addEventListener('click', () => choose(option));
        choices.appendChild(b);
      });
    }
    function choose(option) {
      select.value = option.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      sync(); closeCurrent(); trigger.focus();
    }
    function move(direction) {
      const btns = Array.from(choices.querySelectorAll('.searchable-option'));
      if (!btns.length) return;
      cursor = (cursor + direction + btns.length) % btns.length;
      btns.forEach((b, i) => b.classList.toggle('is-highlighted', i === cursor));
      btns[cursor].scrollIntoView({ block: 'nearest' });
    }
    function open() {
      if (opened?.trigger === trigger) { closeCurrent(); return; }
      closeCurrent(); panel.hidden = false; trigger.setAttribute('aria-expanded', 'true');
      opened = { panel, trigger };
      search.value = ''; render(); search.focus();
    }
    trigger.addEventListener('click', open);
    trigger.addEventListener('keydown', event => {
      if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); }
    });
    search.addEventListener('input', render);
    search.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeCurrent(); trigger.focus(); }
      else if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
      else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
      else if (event.key === 'Enter') {
        event.preventDefault();
        const btn = choices.querySelectorAll('.searchable-option')[cursor];
        if (btn) btn.click();
      }
    });
    select.addEventListener('change', sync);
    select.addEventListener('invalid', () => {
      trigger.classList.add('input-invalid');
      setTimeout(() => { if (!opened) open(); }, 0);
    });
    sync();
  }
  window.enhanceSearchableSelects = root => (root || document).querySelectorAll('select').forEach(enhance);
  window.enhanceSearchableSelects(document);
  document.addEventListener('pointerdown', event => {
    if (opened && !opened.panel.parentNode.contains(event.target)) closeCurrent();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeCurrent();
  });

  const dialog = document.createElement('dialog');
  dialog.className = 'account-status-dialog';
  dialog.innerHTML = '<form method="dialog" class="status-dialog-body"><h2>Ubah Status Akun</h2><p class="status-dialog-prompt"></p><label>Alasan perubahan <textarea minlength="5" maxlength="500" required placeholder="Tuliskan alasan yang jelas"></textarea></label><div class="actions"><button type="button" class="btn outline dialog-cancel">Batal</button><button type="submit" class="btn primary dialog-apply">Simpan Perubahan</button></div></form>';
  document.body.appendChild(dialog);
  const field = dialog.querySelector('textarea');
  let selectedForm = null;
  document.addEventListener('submit', event => {
    const form = event.target.closest('form[data-status-toggle]');
    if (!form || form.dataset.confirmed === 'true') return;
    event.preventDefault(); selectedForm = form; field.value = '';
    const verb = form.querySelector('input[name="decision"]')?.value === 'deactivate' ? 'Menonaktifkan' : 'Mengaktifkan';
    dialog.querySelector('.status-dialog-prompt').textContent = `${verb} ${form.dataset.subject || 'akun ini'}? Masukkan alasan yang akan disimpan dalam riwayat aktivitas.`;
    dialog.showModal(); field.focus();
  });
  dialog.querySelector('.dialog-cancel').addEventListener('click', () => { dialog.close(); selectedForm = null; });
  dialog.querySelector('.status-dialog-body').addEventListener('submit', event => {
    event.preventDefault();
    if (!field.reportValidity() || !selectedForm) return;
    const reason = field.value.trim();
    if (reason.length < 5) { field.setCustomValidity('Alasan minimal 5 karakter.'); field.reportValidity(); return; }
    selectedForm.querySelector('input[name="reason"]').value = reason;
    selectedForm.dataset.confirmed = 'true';
    selectedForm.requestSubmit();
    dialog.close();
  });
  field.addEventListener('input', () => field.setCustomValidity(''));
})();
