document.addEventListener('click',function(e){
 const button=e.target.closest('[data-bh-hub-action],[data-bh-child-new],[data-bh-child-cancel],[data-bh-child-edit],[data-bh-child-delete]');
 if(!button||!window.BHMyHub)return;
 if(button.dataset.bhHubAction){e.preventDefault();return bhToggle(button);}
 const formWrap=document.querySelector('.bh-child-form-wrap'),form=document.querySelector('.bh-child-form');
 if(button.dataset.bhChildNew){form.reset();form.child_id.value='';formWrap.hidden=false;form.name.focus();}
 if(button.dataset.bhChildCancel){formWrap.hidden=true;}
 if(button.dataset.bhChildEdit){const row=document.querySelector('[data-child-id="'+button.dataset.bhChildEdit+'"]');if(row){form.child_id.value=button.dataset.bhChildEdit;form.name.value=row.querySelector('strong').textContent;const meta=row.querySelector('.bh-child-meta');form.dob.value=meta?meta.textContent.replace('Born ',''):'';form.notes.value='';formWrap.hidden=false;form.name.focus();}}
 if(button.dataset.bhChildDelete){if(confirm('Delete this child profile?')) bhChildRequest('bh_delete_child',{child_id:button.dataset.bhChildDelete}).then(()=>location.reload());}
});
document.addEventListener('submit',function(e){if(!e.target.matches('.bh-child-form'))return;e.preventDefault();const data=Object.fromEntries(new FormData(e.target));bhChildRequest('bh_save_child',data).then(()=>location.reload()).catch(msg=>e.target.querySelector('.bh-form-message').textContent=msg);});
function bhPost(action,fields){const d=new FormData();d.append('action',action);d.append('nonce',BHMyHub.nonce);Object.keys(fields).forEach(k=>d.append(k,fields[k]??''));return fetch(BHMyHub.ajaxUrl,{method:'POST',credentials:'same-origin',body:d}).then(r=>r.json()).then(x=>x.success?x.data:Promise.reject(x.data?.message||'Something went wrong.'))}
function bhToggle(button){const old=button.textContent;button.disabled=true;bhPost('bh_toggle_'+button.dataset.bhHubAction,{activity_id:button.dataset.activityId}).then(x=>{button.classList.toggle('is-active',!!x.active);button.setAttribute('aria-pressed',x.active?'true':'false');button.textContent=x.active?'Saved':(button.dataset.bhHubAction==='favourite'?'Save':button.dataset.bhHubAction==='visited'?'Mark visited':'Compare');}).catch(alert).finally(()=>button.disabled=false);}
function bhChildRequest(action,fields){return bhPost(action,fields)}
