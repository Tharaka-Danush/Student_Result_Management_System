/* Student result headings initialise independently of admin interface helpers. */
(function () {
 const tables = document.querySelectorAll('[data-student-frozen-header]');
 if (!tables.length) return;
 const style = document.createElement('style');
 style.textContent = '.student-table-header{position:fixed;z-index:40;overflow:hidden;pointer-events:none;box-shadow:0 3px 8px #172b4d18}.student-table-header[hidden]{display:none!important}.student-table-header table{border-collapse:collapse;table-layout:fixed;margin:0;min-width:0!important}@media print{.student-table-header{display:none!important}}';
 document.head.appendChild(style);
 const items = [];
 tables.forEach(table => {
  const wrap = table.closest('.table-scroll');
  const head = table.tHead;
  if (!wrap || !head || !head.rows.length) return;
  const sourceCells = Array.from(head.querySelectorAll('th'));
  sourceCells.forEach(cell => cell.style.position = 'static');
  const floating = document.createElement('div');
  floating.className = 'student-table-header';
  floating.setAttribute('aria-hidden', 'true');
  floating.hidden = true;
  const copy = document.createElement('table');
  const columns = document.createElement('colgroup');
  sourceCells.forEach(() => columns.appendChild(document.createElement('col')));
  copy.appendChild(columns);
  copy.appendChild(head.cloneNode(true));
  floating.appendChild(copy);
  document.body.appendChild(floating);
  items.push({table,wrap,head,sourceCells,floating,copy,columns,copyCells:Array.from(copy.querySelectorAll('th'))});
 });
 let pending = false;
 function update() {
  pending = false;
  items.forEach(item => {
   const {table,wrap,head,sourceCells,floating,copy,columns,copyCells} = item;
   const heading = head.getBoundingClientRect();
   const bounds = table.getBoundingClientRect();
   const viewport = wrap.getBoundingClientRect();
   floating.hidden = heading.top >= 0 || bounds.bottom <= 0 || !wrap.getClientRects().length;
   if (floating.hidden) return;
   sourceCells.forEach((cell,i) => {
    columns.children[i].style.width = cell.getBoundingClientRect().width + 'px';
    const computed = getComputedStyle(cell);
    ['fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','textTransform','textAlign','verticalAlign','paddingTop','paddingRight','paddingBottom','paddingLeft','color','borderBottom','whiteSpace','boxSizing'].forEach(key => copyCells[i].style[key] = computed[key]);
    copyCells[i].style.position = 'static';
    copyCells[i].style.backgroundColor = computed.backgroundColor;
   });
   copy.style.width = bounds.width + 'px';
   copy.style.transform = 'translateX(' + (-wrap.scrollLeft) + 'px)';
   floating.style.left = (viewport.left + wrap.clientLeft) + 'px';
   floating.style.width = wrap.clientWidth + 'px';
   floating.style.top = Math.min(0,bounds.bottom - heading.height) + 'px';
  });
 }
 function schedule() {
  if (!pending) { pending = true; requestAnimationFrame(update); }
 }
 // Capture scrolling on the page and any enclosing scroll surface.
 window.addEventListener('scroll', schedule, {capture:true,passive:true});
 window.addEventListener('resize', schedule);
 window.addEventListener('pageshow', schedule);
 window.addEventListener('afterprint', schedule);
 if (window.ResizeObserver) {
  const observer = new ResizeObserver(schedule);
  items.forEach(item => observer.observe(item.table));
 }
 if (document.fonts) document.fonts.ready.then(schedule);
 update();
})();
