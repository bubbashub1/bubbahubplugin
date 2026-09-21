document.addEventListener('click',function(e){
 const button=e.target.closest('[data-bh-hub-action],[data-bh-child-new],[data-bh-child-cancel],[data-bh-child-edit],[data-bh-child-delete]');
 if(!button||!window.BHMyHub)return;
 if(button.dataset.bhHubAction){e.preventDefault();return bhToggle(button);}
 const formWrap=document.querySelector('.bh-child-form-wrap'),form=document.querySelector('.bh-child-form');
 if(!formWrap||!form)return;
 if(button.dataset.bhChildNew){form.reset();form.child_id.value='';formWrap.hidden=false;form.name.focus();}
 if(button.dataset.bhChildCancel){formWrap.hidden=true;}
 if(button.dataset.bhChildEdit){const row=document.querySelector('[data-child-id="'+button.dataset.bhChildEdit+'"]');if(row){form.child_id.value=button.dataset.bhChildEdit;form.name.value=row.dataset.name||'';form.dob.value=row.dataset.dob||'';form.notes.value=row.dataset.notes||'';formWrap.hidden=false;form.name.focus();}}
 if(button.dataset.bhChildDelete){if(confirm('Delete this child profile?')) bhChildRequest('bh_delete_child',{child_id:button.dataset.bhChildDelete}).then(()=>location.reload()).catch(msg=>alert(msg));}
});
document.addEventListener('submit',function(e){if(!e.target.matches('.bh-child-form'))return;e.preventDefault();const form=e.target;const message=form.querySelector('.bh-form-message');bhChildRequest('bh_save_child',Object.fromEntries(new FormData(form))).then(()=>location.reload()).catch(msg=>{if(message)message.textContent=msg;});});
function bhPost(action,fields){const d=new FormData();d.append('action',action);d.append('nonce',BHMyHub.nonce);Object.keys(fields).forEach(k=>d.append(k,fields[k]??''));return fetch(BHMyHub.ajaxUrl,{method:'POST',credentials:'same-origin',body:d}).then(r=>r.json()).then(x=>x.success?x.data:Promise.reject(x.data?.message||'Something went wrong.'))}
function bhToggle(button){button.disabled=true;const action=button.dataset.bhHubAction;bhPost('bh_toggle_'+action,{activity_id:button.dataset.activityId}).then(x=>{button.classList.toggle('is-active',!!x.active);button.setAttribute('aria-pressed',x.active?'true':'false');const labels={favourite:['Save','Saved'],visited:['Mark visited','Visited'],compare:['Compare','Selected']};const pair=labels[action]||labels.favourite;button.textContent=x.active?pair[1]:pair[0];}).catch(msg=>alert(msg)).finally(()=>button.disabled=false);}
function bhChildRequest(action,fields){return bhPost(action,fields)}
