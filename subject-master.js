/* Keep the catalog heading visible during page scrolling, including narrow screens. */
(function () {
 const root = document.querySelector('.subject-master');
 const wrap = root && root.querySelector('.subject-table-wrap');
 const table = wrap && wrap.querySelector('table');
 const head = table && table.querySelector('thead');
 if (!head) return;
 const floating = document.createElement('div');
 floating.className = 'subject-frozen-header';
 floating.setAttribute('aria-hidden', 'true');
 floating.hidden = true;
 const copy = document.createElement('table');
 copy.className = table.className;
 const columns = document.createElement('colgroup');
 const cells = Array.from(head.querySelectorAll('th'));
 cells.forEach(() => columns.appendChild(document.createElement('col')));
 copy.appendChild(columns);
 copy.appendChild(head.cloneNode(true));
 floating.appendChild(copy);
 root.appendChild(floating);
 let pending = false;
 function update() {
  pending = false;
  const bounds = table.getBoundingClientRect();
  const heading = head.getBoundingClientRect();
  const viewport = wrap.getBoundingClientRect();
  floating.hidden = wrap.hidden || heading.top >= 0 || bounds.bottom <= 0;
  if (floating.hidden) return;
  cells.forEach((cell, i) => {
   columns.children[i].style.width = cell.getBoundingClientRect().width + 'px';
  });
  copy.style.width = bounds.width + 'px';
  copy.style.transform = 'translateX(' + (-wrap.scrollLeft) + 'px)';
  floating.style.left = (viewport.left + wrap.clientLeft) + 'px';
  floating.style.width = wrap.clientWidth + 'px';
  floating.style.top = Math.min(0, bounds.bottom - heading.height) + 'px';
 }
 function schedule() {
  if (!pending) { pending = true; requestAnimationFrame(update); }
 }
 window.addEventListener('scroll', schedule, { passive: true });
 window.addEventListener('resize', schedule);
 wrap.addEventListener('scroll', schedule, { passive: true });
 if (window.ResizeObserver) new ResizeObserver(schedule).observe(table);
 if (document.fonts) document.fonts.ready.then(schedule);
 update();
})();
