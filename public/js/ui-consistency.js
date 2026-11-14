(() => {
  // Give wide tables a visible cue and keyboard access without widening the page.
  document.querySelectorAll('.table-wrap').forEach(wrapper=>{
    wrapper.tabIndex=0;wrapper.setAttribute('role','region');
    wrapper.setAttribute('aria-label',wrapper.closest('section')?.querySelector('h2,h3')?.textContent?.trim()||'Tabel data');
    const hint=document.createElement('p');hint.className='table-scroll-hint';hint.textContent='Geser tabel ke samping untuk melihat kolom lainnya.';hint.hidden=true;wrapper.before(hint);
    const sync=()=>hint.hidden=wrapper.scrollWidth<=wrapper.clientWidth+1;
    sync();if(typeof ResizeObserver!=='undefined')new ResizeObserver(sync).observe(wrapper);
  });
  const outcomes=document.getElementById('submission-outcomes');
  if(outcomes){
    const sync=()=>outcomes.querySelectorAll('.outcome-editor').forEach(row=>{
      const skill=row.querySelector('select[name$="[skill_id]"]');const proposal=row.querySelector('details');if(!skill||!proposal)return;
      const existing=skill.value!=='';proposal.hidden=existing;proposal.querySelectorAll('input,textarea,select').forEach(field=>field.disabled=existing);
    });
    outcomes.addEventListener('change',sync);sync();new MutationObserver(sync).observe(outcomes,{childList:true});
  }
})();
