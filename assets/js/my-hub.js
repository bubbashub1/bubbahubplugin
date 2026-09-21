document.addEventListener('click',function(e){
 const button=e.target.closest('[data-bh-hub-action]');
 if(!button||!window.BHMyHub)return;
 e.preventDefault();
 const data=new FormData();
 data.append('action','bh_toggle_'+button.dataset.bhHubAction);
 data.append('activity_id',button.dataset.activityId||'');
 data.append('nonce',BHMyHub.nonce);
 button.disabled=true;
 fetch(BHMyHub.ajaxUrl,{method:'POST',credentials:'same-origin',body:data})
  .then(r=>r.json()).then(result=>{
   if(result.success){button.classList.toggle('is-active',!!result.data.active);button.setAttribute('aria-pressed',result.data.active?'true':'false');}
  }).finally(()=>{button.disabled=false;});
});
