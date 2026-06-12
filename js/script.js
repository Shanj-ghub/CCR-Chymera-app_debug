// script.js - small UI helpers (accordion)
// Updated to animate shading + slide using CSS classes.

document.addEventListener('DOMContentLoaded', function(){
  // accordion heads
  var heads = document.querySelectorAll('.acc-head');

  heads.forEach(function(h){
    h.addEventListener('click', function(){
      var item = h.parentElement;
      var body = item.querySelector('.acc-body');

      // Toggle this item
      var isOpen = item.classList.contains('open');

      // Close all items first (single-open behavior)
      document.querySelectorAll('.acc-item.open').forEach(function(openItem){
        if(openItem === item) return; // keep target closed for now
        closeItem(openItem);
      });

      if(isOpen){
        closeItem(item);
      } else {
        openItem(item);
      }
    });
  });

  // Open item: add class, animate height
  function openItem(item){
    var body = item.querySelector('.acc-body');
    if(!body) return;
    item.classList.add('open');

    // measure
    body.style.display = 'block';
    var h = body.scrollHeight + 'px';
    body.style.height = '0px';

    // allow paint
    requestAnimationFrame(function(){
      body.style.height = h;
    });

    // after transition, clear height to allow responsive wrapping
    body.addEventListener('transitionend', function te(){
      if(item.classList.contains('open')){
        body.style.height = 'auto';
      }
      body.removeEventListener('transitionend', te);
    });
  }

  // Close item
  function closeItem(item){
    var body = item.querySelector('.acc-body');
    if(!body) return;
    // set explicit height to current height then animate to 0
    body.style.height = body.scrollHeight + 'px';
    // allow paint
    requestAnimationFrame(function(){
      body.style.height = '0px';
    });
    item.classList.remove('open');

    body.addEventListener('transitionend', function te(){
      if(!item.classList.contains('open')){
        body.style.display = 'none';
      }
      body.removeEventListener('transitionend', te);
    });
  }

});