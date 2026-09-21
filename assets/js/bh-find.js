(function(){'use strict';
function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
function init(root){
  if(!root||root.dataset.ready==='1')return;
  root.dataset.ready='1';
  root.innerHTML='<div class="bh-find"><form class="bh-find__filters"><input name="search" placeholder="What are you looking for?"><input name="region" placeholder="Region"><input name="category" placeholder="Category"><input name="age" placeholder="Age range"><input name="day" placeholder="Day"><label><input type="checkbox" name="free"> Free</label><button type="submit">Find activities</button></form><div class="bh-find__status"></div><div class="bh-find__results"></div></div>';
  const form=root.querySelector('form'),status=root.querySelector('.bh-find__status'),results=root.querySelector('.bh-find__results');
  async function load(){
    status.textContent='Loading activities…'; results.innerHTML='';
    const params=new URLSearchParams(new FormData(form));
    const url=(window.BubbaHub&&BubbaHub.restUrl?BubbaHub.restUrl:'/wp-json/bubba-hub/v1/')+'activities?'+params.toString();
    try{
      const response=await fetch(url,{credentials:'same-origin',headers:{'X-WP-Nonce':window.BubbaHub?BubbaHub.nonce:''}});
      if(!response.ok)throw new Error('Request failed');
      const json=await response.json(),items=Array.isArray(json.data)?json.data:[];
      status.textContent=items.length+' activit'+(items.length===1?'y':'ies')+' found';
      results.innerHTML=items.map(function(item){return '<article class="bh-card"><h3><a href="'+esc(item.url)+'">'+esc(item.title)+'</a></h3><p>'+esc(item.excerpt)+'</p><p>'+esc(item.town||item.region||'')+(item.price?' · '+esc(item.price):'')+'</p></article>';}).join('')||'<p>No activities found. Try changing your filters.</p>';
    }catch(e){status.textContent='';results.innerHTML='<div class="bh-error">We could not load activities right now.</div>';}
  }
  form.addEventListener('submit',function(e){e.preventDefault();load();});
  load();
}
document.addEventListener('DOMContentLoaded',function(){var root=document.getElementById('bh-find-app');if(root)init(root);});
})();