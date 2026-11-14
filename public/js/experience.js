(() => {
  // Native dialog supplies modal semantics, focus trapping and Escape handling.
  const dialog=document.createElement('dialog');dialog.className='ui-dialog';
  dialog.innerHTML='<h2 id="dialog-title"></h2><p id="dialog-message"></p><pre hidden></pre><div class="actions"><button type="button" class="btn outline" data-cancel>Batal</button><button type="button" class="btn primary" data-approve>Lanjutkan</button></div>';
  dialog.setAttribute('aria-labelledby','dialog-title');document.body.append(dialog);
  let approve=null,opener=null,approvedSubmit=null;
  const close=()=>{dialog.close();opener?.focus();approve=null;};
  dialog.querySelector('[data-cancel]').addEventListener('click',close);
  dialog.addEventListener('cancel',()=>{approve=null;opener?.focus();});
  dialog.querySelector('[data-approve]').addEventListener('click',()=>{const fn=approve;dialog.close();approve=null;fn?.();});
  function ask(title,message,details,fn,source){
    opener=source;if(source?.form?.dataset.review)dialog.querySelector('[data-approve]').textContent='Terbitkan Versi';dialog.querySelector('h2').textContent=title;dialog.querySelector('p').textContent=message;
    const pre=dialog.querySelector('pre');pre.textContent=details||'';pre.hidden=!details;approve=fn;dialog.showModal();dialog.querySelector('[data-cancel]').focus();
  }
  document.addEventListener('click',e=>{
    const control=e.target.closest('[data-confirm]');if(!control||control.dataset.approved)return;
    const form=control.tagName==='FORM'?control:control.form;const submitter=control.tagName==='FORM'?(e.target.closest('button,input[type=submit]')||form.querySelector('button[type=submit],button:not([type]),input[type=submit]')):control;if(form && !form.reportValidity()){e.preventDefault();return;}
    e.preventDefault();e.stopImmediatePropagation();
    dialog.querySelector('[data-approve]').textContent=control.dataset.confirmLabel||(/hapus/i.test(control.dataset.confirm)?'Hapus':(/keluar/i.test(control.textContent)?'Keluar':'Konfirmasi'));
    ask(control.dataset.confirmTitle||(/hapus/i.test(control.dataset.confirm)?'Hapus data?':'Konfirmasi perubahan'),control.dataset.confirm,'',()=>{
      if(control.tagName==='A'){
        if(control.target==='_blank')window.open(control.href,'_blank','noopener,noreferrer');else location.assign(control.href);
      }else if(form){approvedSubmit=submitter;form.requestSubmit(submitter||undefined);}
    },control);
  },true);
  document.addEventListener('submit',e=>{
    const form=e.target;
    if(!form.dataset.review || approvedSubmit===e.submitter){approvedSubmit=null;return;}
    e.preventDefault();const lines=[];
    for(const field of form.elements){if(!field.name||field.type==='hidden'||field.type==='password'||field.type==='submit')continue;
      const label=field.closest('label')?.childNodes[0]?.textContent?.trim()||field.name;lines.push(label+': '+(field.selectedOptions?.[0]?.textContent||field.value));}
    ask(form.dataset.review,'Periksa nilai dan alasan sebelum menerbitkan versi baru. Hasil historis tidak diubah.',lines.join('\n'),()=>{approvedSubmit=e.submitter;form.requestSubmit(e.submitter);},e.submitter);
  });
  document.querySelectorAll('input[type=password]').forEach(input=>{
    const wrapper=document.createElement('div');wrapper.className='password-control';input.before(wrapper);wrapper.append(input);
    const button=document.createElement('button');button.type='button';button.textContent='Tampilkan';button.setAttribute('aria-label','Tampilkan kata sandi');
    button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Sembunyikan':'Tampilkan';button.setAttribute('aria-label',button.textContent+' kata sandi');});wrapper.append(button);
  });
  document.querySelectorAll('.bulk-table tbody tr').forEach(row=>{
    const checkbox=row.querySelector('input[type=checkbox]');if(!checkbox)return;
    const update=()=>row.querySelectorAll('input:not([type=checkbox]),select').forEach(field=>field.disabled=!checkbox.checked);
    checkbox.addEventListener('change',update);update();
  });
  // Preserve originating list filters without accepting an external redirect URL.
  document.querySelectorAll('a[href]').forEach(a=>{
    const url=new URL(a.href,location.href);
    if(a.closest('main') && !a.getAttribute('href')?.startsWith('#') && !url.hash && url.origin===location.origin&&/\/(detail|form|kesenjangan|pelatihan|simulasi|hasil|kelulusan|keputusan|konfirmasi|skor)$/.test(url.pathname)&&!a.classList.contains('back-link')){
      const origin=new URL(location.href);origin.searchParams.delete('return');url.searchParams.set('return',origin.pathname+origin.search);a.href=url.href;
    }
  });
  let dirty=false,leaving=false;
  document.querySelectorAll('form[data-dirty-guard]').forEach(form=>{
    const initial=new FormData(form);form.addEventListener('change',()=>{dirty=JSON.stringify([...new FormData(form)])!==JSON.stringify([...initial]);});
    form.addEventListener('submit',()=>{dirty=false;});
  });
  addEventListener('beforeunload',e=>{if(dirty&&!leaving){e.preventDefault();e.returnValue='';}});
  document.addEventListener('click',e=>{const a=e.target.closest('a');if(!a||!dirty||a.target==='_blank'||a.getAttribute('href')?.startsWith('#'))return;
    e.preventDefault();ask('Perubahan belum disimpan','Tinggalkan halaman dan abaikan perubahan pilihan?','',()=>{leaving=true;location.assign(a.href);},a);
  });
})();

document.querySelectorAll('[data-bulk-search]').forEach(input=>input.addEventListener('input',()=>{const query=input.value.toLocaleLowerCase('id');input.closest('form').querySelectorAll('tbody tr').forEach(row=>row.hidden=!row.querySelector('td')?.textContent.toLocaleLowerCase('id').includes(query));}));

document.querySelectorAll('[data-importance]').forEach(select=>select.addEventListener('change',()=>{const form=select.closest('form'),row=select.closest('tr'),weight=row.querySelector('[data-weight]');weight.value=select.value==='required'?form.dataset.defaultRequired:form.dataset.defaultPreferred;row.querySelector('[data-weight-label]').textContent=weight.value;}));
document.querySelectorAll('[data-weight]').forEach(input=>input.addEventListener('input',()=>input.closest('tr').querySelector('[data-weight-label]').textContent=input.value));
document.querySelectorAll('details:has(iframe[data-preview-src])').forEach(details=>details.addEventListener('toggle',()=>{const frame=details.querySelector('iframe');if(details.open&&!frame.src)frame.src=frame.dataset.previewSrc;}));
