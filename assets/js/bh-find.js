(function(){'use strict';

function esc(value){
  return String(value==null?'':value).replace(/[&<>"']/g,function(c){
    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];
  });
}

function init(root){
  if(!root || root.dataset.ready==='1') return;
  root.dataset.ready='1';

  root.innerHTML='<div class="bh-find"><form class="bh-find__filters"><input name="search" placeholder="What are you looking for?"><input name="location" placeholder="Town or area"><input name="category" placeholder="Category"><input name="age_min" type="number" min="0" step="0.5" placeholder="Min age"><input name="age_max" type="number" min="0" step="0.5" placeholder="Max age"><input name="day" placeholder="Day"><input name="price_max" type="number" min="0" step="0.01" placeholder="Max price"><button type="submit">Find activities</button></form><div class="bh-find__status" aria-live="polite"></div><div class="bh-find__results"></div></div>';

  var form=root.querySelector('form');
  var status=root.querySelector('.bh-find__status');
  var results=root.querySelector('.bh-find__results');

  function load(){
    status.textContent='Loading activities…';
    results.innerHTML='';

    var filters={};
    new FormData(form).forEach(function(value,key){
      if(String(value).trim()!=='') filters[key]=String(value).trim();
    });

    var api=window.BubbaHubAPI;
    if(!api){
      status.textContent='';
      results.innerHTML='<div class="bh-error">The Bubba Hub API client has not been loaded.</div>';
      return;
    }

    api.activities(filters).then(function(json){
      var items=Array.isArray(json.data)?json.data:[];
      var pagination=json.pagination||{};
      status.textContent=(pagination.total!=null?pagination.total:items.length)+' activit'+((pagination.total===1||items.length===1)?'y':'ies')+' found';

      results.innerHTML=items.map(function(item){
        var title=item.name||'Activity';
        var location=[item.town,item.region].filter(Boolean).join(', ');
        var meta=[];
        if(location) meta.push(location);
        if(item.price_label) meta.push(item.price_label);
        else if(item.price!=null) meta.push('£'+Number(item.price).toFixed(2));
        if(item.age_min!=null || item.age_max!=null){
          meta.push('Ages '+(item.age_min!=null?item.age_min:'0')+'–'+(item.age_max!=null?item.age_max:'+'));
        }

        return '<article class="bh-card">'+
          (item.image_url?'<img src="'+esc(item.image_url)+'" alt="'+esc(title)+'">':'')+
          '<h3>'+esc(title)+'</h3>'+
          (item.description?'<p>'+esc(item.description)+'</p>':'')+
          '<p>'+esc(meta.join(' · '))+'</p>'+
          (item.booking_url?'<p><a href="'+esc(item.booking_url)+'">Book now</a></p>':'')+
          '</article>';
      }).join('') || '<p>No activities found. Try changing your filters.</p>';
    }).catch(function(error){
      console.error(error);
      status.textContent='';
      results.innerHTML='<div class="bh-error">We could not load activities right now.</div>';
    });
  }

  form.addEventListener('submit',function(e){e.preventDefault();load();});
  load();
}

document.addEventListener('DOMContentLoaded',function(){
  var root=document.getElementById('bh-find-app');
  if(root) init(root);
});
})();