(function(){
  // Only activate when ?dbg=1 is present
  if (!location.search.includes('dbg=1')) return;

  function createButton(){
    const btn = document.createElement('div');
    btn.id = 'overlay-debug-btn';
    btn.style.position = 'fixed';
    btn.style.top = '8px';
    btn.style.left = '8px';
    btn.style.zIndex = '2147483647';
    btn.style.background = 'rgba(255,255,255,0.95)';
    btn.style.border = '2px solid #c00';
    btn.style.color = '#111';
    btn.style.padding = '6px 8px';
    btn.style.borderRadius = '6px';
    btn.style.boxShadow = '0 6px 18px rgba(0,0,0,0.25)';
    btn.style.fontSize = '13px';
    btn.style.cursor = 'pointer';
    btn.style.pointerEvents = 'auto';
    btn.textContent = 'Overlay Debug';
    return btn;
  }

  function findCoveringElements(){
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const els = Array.from(document.querySelectorAll('body *')).filter(function(el){
      if (!(el instanceof Element)) return false;
      if (el === document.body || el === document.documentElement) return false;
      const style = getComputedStyle(el);
      if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity||1) === 0) return false;
      const rect = el.getBoundingClientRect();
      // consider elements that cover most of viewport
      const covers = rect.width >= vw * 0.9 && rect.height >= vh * 0.9 && rect.top <= 10 && rect.left <= 10;
      if (covers) return true;
      // also include known backdrop classes
      if (el.classList.contains('modal-backdrop') || el.classList.contains('offcanvas-backdrop')) return true;
      return false;
    });
    return els;
  }

  function markElements(list){
    list.forEach(function(el, idx){
      el.__overlayDebugOutline = document.createElement('div');
      const outline = el.__overlayDebugOutline;
      const r = el.getBoundingClientRect();
      outline.style.position = 'fixed';
      outline.style.left = r.left + 'px';
      outline.style.top = r.top + 'px';
      outline.style.width = r.width + 'px';
      outline.style.height = r.height + 'px';
      outline.style.zIndex = '2147483646';
      outline.style.border = '3px dashed rgba(255,0,0,0.85)';
      outline.style.background = 'rgba(255,0,0,0.03)';
      outline.style.pointerEvents = 'none';
      outline.setAttribute('data-overlay-debug', 'true');
      document.body.appendChild(outline);

      // small label
      const lbl = document.createElement('div');
      lbl.style.position = 'fixed';
      lbl.style.left = Math.max(8, r.left + 8) + 'px';
      lbl.style.top = Math.max(8, r.top + 8) + 'px';
      lbl.style.zIndex = '2147483647';
      lbl.style.background = '#c00';
      lbl.style.color = '#fff';
      lbl.style.padding = '4px 6px';
      lbl.style.borderRadius = '4px';
      lbl.style.fontSize = '12px';
      lbl.style.pointerEvents = 'auto';
      lbl.textContent = (el.className || el.tagName) + ' (z=' + (getComputedStyle(el).zIndex||'auto') + ')';
      lbl.__targetEl = el;
      lbl.addEventListener('click', function(ev){ ev.stopPropagation(); ev.preventDefault();
        // remove the target overlay element
        try{ if (lbl.__targetEl && lbl.__targetEl.parentNode) lbl.__targetEl.parentNode.removeChild(lbl.__targetEl); }catch(e){}
        removeMarkers();
      });
      document.body.appendChild(lbl);
    });
  }

  function removeMarkers(){
    document.querySelectorAll('[data-overlay-debug]').forEach(function(n){ n.remove(); });
    // also remove any floating labels we created
    Array.from(document.body.querySelectorAll('div')).forEach(function(d){ if (d.textContent && d.textContent.indexOf(' (z=')>0 && d.style && d.style.pointerEvents === 'auto' && d.style.background === '#c00') d.remove(); });
  }

  function removeFoundOverlays(){
    const els = findCoveringElements();
    els.forEach(function(el){ try{ if (el && el.parentNode) el.parentNode.removeChild(el); }catch(e){} });
    removeMarkers();
    console.log('overlay-debug: removed', els.length, 'elements');
  }

  function listAndMark(){
    removeMarkers();
    const els = findCoveringElements();
    if (!els.length) { alert('overlay-debug: no full-viewport overlays found'); return; }
    markElements(els);
    alert('overlay-debug: found ' + els.length + ' covering element(s). Click a red label to remove it, or use "Remove All".');
  }

  // create control UI
  const ctrl = createButton();
  document.addEventListener('DOMContentLoaded', function(){
    document.body.appendChild(ctrl);
  });
  // If DOMContentLoaded already fired
  if (document.readyState === 'complete' || document.readyState === 'interactive'){
    document.body.appendChild(ctrl);
  }

  const listBtn = document.createElement('button');
  listBtn.textContent = 'List overlays';
  listBtn.style.marginLeft = '8px';
  listBtn.style.pointerEvents = 'auto';
  listBtn.addEventListener('click', function(e){ e.stopPropagation(); listAndMark(); });
  ctrl.appendChild(listBtn);

  const remBtn = document.createElement('button');
  remBtn.textContent = 'Remove All';
  remBtn.style.marginLeft = '8px';
  remBtn.style.pointerEvents = 'auto';
  remBtn.addEventListener('click', function(e){ e.stopPropagation(); if (confirm('Remove all detected overlays?')) removeFoundOverlays(); });
  ctrl.appendChild(remBtn);

  const info = document.createElement('span');
  info.style.marginLeft = '8px';
  info.style.fontSize = '12px';
  info.textContent = 'dbg=1 active';
  ctrl.appendChild(info);

})();
