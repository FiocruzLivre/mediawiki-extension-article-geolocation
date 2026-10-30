(function () {
 'use strict';
 function init() {
  document.querySelectorAll('.geo-picker:not([data-geo-initialized])').forEach(node=>{
   const field=document.querySelector('.mapa-geo-transport[data-geo-for="'+node.id+'"]');
   window.MapaGeoPicker.init(node,{locations:[],onConfirm(value){if(field)field.value=value?JSON.stringify(value):'';}});
  });
 }
 if (typeof mw!=='undefined'&&mw.hook)mw.hook('wikipage.content').add(init);
 else if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);
 else init();
})();