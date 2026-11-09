(() => {
  const root = document.documentElement;
  const savedTheme = localStorage.getItem('srs-theme');
  if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.dataset.theme = 'dark';

  const syncThemeButtons = () => document.querySelectorAll('.theme-toggle').forEach(btn => {
    btn.setAttribute('aria-label', root.dataset.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    const span = btn.querySelector('span'); if (span) span.textContent = root.dataset.theme === 'dark' ? '☀' : '◐'; btn.title = root.dataset.theme === 'dark' ? 'Use light theme' : 'Use dark theme';
  });
  syncThemeButtons();
  document.addEventListener('click', e => {
    const t = e.target.closest('.theme-toggle'); if (!t) return;
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('srs-theme', root.dataset.theme); syncThemeButtons();
  });


  const savedDensity = localStorage.getItem('srs-density') || 'comfortable';
  root.dataset.density = savedDensity;
  const syncDensity = () => document.querySelectorAll('.density-toggle').forEach(btn => {
    const compact = root.dataset.density === 'compact';
    btn.setAttribute('aria-label', compact ? 'Use comfortable table spacing' : 'Use compact table spacing');
    btn.title = compact ? 'Comfortable table spacing' : 'Compact table spacing';
    const span=btn.querySelector('span'); if(span) span.textContent=compact?'▤':'≡';
  });
  syncDensity();
  document.addEventListener('click', e => {
    const t=e.target.closest('.density-toggle'); if(!t) return;
    root.dataset.density=root.dataset.density==='compact'?'comfortable':'compact';
    localStorage.setItem('srs-density',root.dataset.density); syncDensity();
  });

  const mobile = document.querySelector('.mobile-nav-toggle');
  const nav = document.querySelector('.nav-buttons');
  if (mobile && nav) mobile.addEventListener('click', () => {
    const open = nav.classList.toggle('nav-open'); mobile.classList.toggle('open', open); mobile.setAttribute('aria-expanded', String(open));
  });

  const profileTrigger = document.querySelector('.profile-trigger');
  const profileMenu = document.querySelector('.profile-menu');
  if (profileTrigger && profileMenu) {
    profileTrigger.addEventListener('click', e => { e.stopPropagation(); const open = profileMenu.classList.toggle('show'); profileTrigger.setAttribute('aria-expanded', String(open)); });
    document.addEventListener('click', e => { if (!e.target.closest('.profile-menu-wrap')) { profileMenu.classList.remove('show'); profileTrigger.setAttribute('aria-expanded','false'); } });
  }

  const adminNav = document.querySelector('.navbar');
  const content = document.querySelector('main.content');
  if (adminNav && content && !location.pathname.endsWith('admin_dashboard.php')) {
    const labels = {
      'add_student.php':['Students','Add Student'],'view_students.php':['Students','Student Records'],'edit_student.php':['Students','Edit Student'],
      'add_result.php':['Results','Add Result'],'view_results.php':['Results','Result Records'],'edit_result.php':['Results','Edit Result'],
      'bulk_results.php':['Results','Bulk Upload'],'result_approvals.php':['Results','Approvals'],'bulk_publish.php':['Results','Publish'],
      'academic_settings.php':['Configuration','Academic Rules'],'subject_master.php':['Configuration','Subjects'],'pin_reset_requests.php':['Security','PIN Resets'],
      'audit_log.php':['System','Audit Log'],'admin_users.php':['System','Administrators'],'admin_settings.php':['System','Settings']
    };
    const file = location.pathname.split('/').pop(); const parts = labels[file];
    if (parts && !content.querySelector('.ui-breadcrumbs')) {
      const bc = document.createElement('nav'); bc.className='ui-breadcrumbs'; bc.setAttribute('aria-label','Breadcrumb');
      bc.innerHTML = `<a href="admin_dashboard.php">Dashboard</a><span>›</span><span>${parts[0]}</span><span>›</span><strong>${parts[1]}</strong>`;
      content.insertBefore(bc, content.firstChild);
    }
  }

  const iconMap = {'Dashboard':'⌂','Add Student':'＋','Students':'◎','Add Result':'✓','Results':'▦','Bulk Upload':'⇧','Approvals':'✓','Publish':'↗','Academic Rules':'⚙','Subjects':'≡','PIN Resets':'⌘','Audit':'↺','Admins':'♙','Settings':'⚙'};
  document.querySelectorAll('.nav-btn').forEach(a => { const label=a.textContent.trim(); if(iconMap[label] && !a.querySelector('.nav-icon')) a.insertAdjacentHTML('afterbegin',`<span class="nav-icon" aria-hidden="true">${iconMap[label]}</span>`); });

  const toastStack = document.createElement('div'); toastStack.className='toast-stack'; document.body.appendChild(toastStack);
  const makeToast = (message, type='success') => {
    const t=document.createElement('div'); t.className=`ui-toast ${type}`; t.innerHTML=`<span class="toast-icon">${type==='success'?'✓':'!'}</span><div>${message}</div><button type="button" aria-label="Dismiss">×</button>`;
    toastStack.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    const close=()=>{t.classList.remove('show');setTimeout(()=>t.remove(),250)}; t.querySelector('button').onclick=close; setTimeout(close,4500);
  };
  document.querySelectorAll('.message.success').forEach(m => { if (m.textContent.trim()) makeToast(m.innerHTML,'success'); });
  document.querySelectorAll('.message.error').forEach(m => { if (m.textContent.trim()) makeToast(m.innerHTML,'error'); });

  let pendingForm = null;
  const modal = document.createElement('div'); modal.className='ui-modal-backdrop'; modal.innerHTML=`<div class="ui-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle"><div class="modal-icon">!</div><h3 id="confirmTitle">Confirm action</h3><p id="confirmText">Are you sure you want to continue?</p><div class="modal-actions"><button type="button" class="btn-secondary" data-cancel>Cancel</button><button type="button" class="btn-danger" data-confirm>Confirm</button></div></div>`; document.body.appendChild(modal);
  document.querySelectorAll('form[onsubmit*="confirm("]').forEach(form => {
    const src=form.getAttribute('onsubmit')||''; const m=src.match(/confirm\(['\"](.+?)['\"]\)/); form.removeAttribute('onsubmit'); form.dataset.confirmMessage=m?m[1]:'Are you sure you want to continue?';
    form.addEventListener('submit', e => { if(form.dataset.confirmed==='1') return; e.preventDefault(); pendingForm=form; modal.querySelector('#confirmText').textContent=form.dataset.confirmMessage; modal.classList.add('show'); });
  });
  modal.querySelector('[data-cancel]').onclick=()=>{modal.classList.remove('show');pendingForm=null};
  modal.querySelector('[data-confirm]').onclick=()=>{if(!pendingForm)return; const f=pendingForm; pendingForm=null; modal.classList.remove('show'); f.dataset.confirmed='1'; f.requestSubmit();};
  document.querySelectorAll('a[href="logout.php"],a[href="student_logout.php"]').forEach(link=>link.addEventListener('click',e=>{e.preventDefault();const href=link.href;modal.querySelector('#confirmTitle').textContent='Sign out?';modal.querySelector('#confirmText').textContent='You will need to sign in again to continue.';modal.classList.add('show');const ok=modal.querySelector('[data-confirm]');const old=ok.onclick;ok.onclick=()=>{ok.onclick=old;location.href=href};}));
  modal.addEventListener('click',e=>{if(e.target===modal){modal.classList.remove('show');pendingForm=null}});

  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    if (!form.checkValidity()) return;
    const btn=form.querySelector('button[type="submit"],input[type="submit"]'); if(!btn || btn.classList.contains('no-loading')) return;
    btn.classList.add('is-loading'); btn.setAttribute('aria-busy','true');
    if(btn.tagName==='INPUT'){btn.dataset.oldValue=btn.value;btn.value='Processing…';} else {btn.dataset.oldText=btn.textContent;btn.textContent='Processing…';}
  }));

  document.querySelectorAll('.table-wrapper table').forEach(table => {
    const wrap=table.closest('.table-wrapper'); if(wrap && !wrap.previousElementSibling?.classList?.contains('table-caption-bar')) {
      const rows=table.querySelectorAll('tr').length-1; if(rows>=0){ const bar=document.createElement('div');bar.className='table-caption-bar';bar.innerHTML=`<span>${rows} record${rows===1?'':'s'} shown</span><span class="table-hint">Scroll horizontally on smaller screens</span>`;wrap.parentNode.insertBefore(bar,wrap); }
    }
  });
  // Final UI helpers
  if (!document.querySelector('link[rel="icon"]')) {
    const icon=document.createElement('link'); icon.rel='icon'; icon.type='image/svg+xml'; icon.href='favicon.svg'; document.head.appendChild(icon);
  }

  document.querySelectorAll('form').forEach(form => {
    const fields=form.querySelectorAll('input:not([type="hidden"]),select,textarea').length;
    if(fields >= 7) form.classList.add('ui-long-form');
  });

  document.querySelectorAll('.table-wrapper').forEach(wrap => {
    const table=wrap.querySelector('table'); if(!table) return;
    const dataRows=[...table.querySelectorAll('tr')].filter((r,i)=>i>0 && r.querySelectorAll('td').length);
    const explicitEmpty=dataRows.length===0 || (dataRows.length===1 && /no (records|results|students|subjects|data)/i.test(dataRows[0].textContent));
    if(explicitEmpty){
      const box=document.createElement('div'); box.className='ui-empty-state';
      box.innerHTML='<span class="empty-icon" aria-hidden="true">⌕</span><strong>No records to show</strong><p>Try adjusting the filters or add a new record when appropriate.</p>';
      wrap.hidden=true; wrap.insertAdjacentElement('afterend',box);
    }
  });

  document.addEventListener('keydown', e => {
    if(e.key!=='Escape') return;
    document.querySelector('.profile-menu')?.classList.remove('show');
    document.querySelector('.nav-buttons')?.classList.remove('nav-open');
    document.querySelector('.mobile-nav-toggle')?.classList.remove('open');
    modal?.classList.remove('show');
  });

  // Freeze screen table headings during both page and table scrolling.
  const freezeStyle = document.createElement('style');
  freezeStyle.textContent = '.ui-frozen-table-header{position:fixed;z-index:40;overflow:hidden;pointer-events:none;box-shadow:0 3px 8px #172b4d18}.ui-frozen-table-header[hidden]{display:none!important}.ui-frozen-table-header table{border-collapse:collapse;table-layout:fixed;margin:0;min-width:0!important}@media print{.ui-frozen-table-header{display:none!important}}';
  document.head.appendChild(freezeStyle);
  const frozenTables = [];
  document.querySelectorAll('.table-wrapper table,.table-scroll table').forEach(table => {
    if (table.hasAttribute('data-student-frozen-header')) return;
    const wrap = table.closest('.table-wrapper,.table-scroll');
    const firstRow = table.tHead?.rows[0] || table.rows[0];
    if (!firstRow || !firstRow.querySelector('th') || !table.tBodies.length) return;
    const sourceHead = table.tHead || firstRow;
    const floating = document.createElement('div');
    floating.className = 'ui-frozen-table-header';
    floating.setAttribute('aria-hidden', 'true');
    floating.hidden = true;
    const copy = document.createElement('table');
    const columns = document.createElement('colgroup');
    Array.from(firstRow.cells).forEach(cell => {
      const col = document.createElement('col'); col.span = cell.colSpan; columns.appendChild(col);
    });
    copy.appendChild(columns);
    const headCopy = table.tHead ? sourceHead.cloneNode(true) : document.createElement('thead');
    if (!table.tHead) headCopy.appendChild(firstRow.cloneNode(true));
    headCopy.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
    copy.appendChild(headCopy);
    floating.appendChild(copy); document.body.appendChild(floating);
    const sourceCells = Array.from(sourceHead.querySelectorAll('th,td'));
    const copyCells = Array.from(headCopy.querySelectorAll('th,td'));
    sourceCells.forEach(cell => cell.style.position = 'static');
    frozenTables.push({table,wrap,sourceHead,firstRow,floating,copy,columns,sourceCells,copyCells});
    wrap.addEventListener('scroll', scheduleFrozenHeaders, {passive:true});
  });
  let freezePending = false;
  function scheduleFrozenHeaders() {
    if (!freezePending) { freezePending = true; requestAnimationFrame(updateFrozenHeaders); }
  }
  function updateFrozenHeaders() {
    freezePending = false;
    frozenTables.forEach(item => {
      const {table,wrap,sourceHead,firstRow,floating,copy,columns,sourceCells,copyCells} = item;
      const viewport = wrap.getBoundingClientRect();
      const bounds = table.getBoundingClientRect();
      const heading = sourceHead.getBoundingClientRect();
      const insideScroll = wrap.scrollHeight > wrap.clientHeight + 1 && /auto|scroll/.test(getComputedStyle(wrap).overflowY);
      const top = insideScroll ? Math.max(0,viewport.top + wrap.clientTop) : 0;
      const bottom = insideScroll ? Math.min(bounds.bottom,viewport.bottom) : bounds.bottom;
      floating.hidden = !wrap.getClientRects().length || wrap.hidden || heading.top >= top || bottom <= top || viewport.right <= 0 || viewport.left >= window.innerWidth;
      if (floating.hidden) return;
      Array.from(firstRow.cells).forEach((cell,i) => columns.children[i].style.width = (cell.getBoundingClientRect().width / cell.colSpan) + 'px');
      sourceCells.forEach((cell,i) => {
        const computed = getComputedStyle(cell);
        ['fontFamily','fontSize','fontWeight','fontStyle','lineHeight','letterSpacing','textTransform','textAlign','verticalAlign','paddingTop','paddingRight','paddingBottom','paddingLeft','color','borderTop','borderRight','borderBottom','borderLeft','whiteSpace','boxSizing'].forEach(key => copyCells[i].style[key] = computed[key]);
        copyCells[i].style.position = 'static';
        copyCells[i].style.backgroundColor = computed.backgroundColor === 'rgba(0, 0, 0, 0)' ? getComputedStyle(document.body).backgroundColor : computed.backgroundColor;
      });
      copy.style.width = bounds.width + 'px';
      copy.style.transform = 'translateX(' + (-wrap.scrollLeft) + 'px)';
      floating.style.left = (viewport.left + wrap.clientLeft) + 'px';
      floating.style.width = wrap.clientWidth + 'px';
      floating.style.top = Math.min(top,bottom - heading.height) + 'px';
    });
  }
  window.addEventListener('scroll', scheduleFrozenHeaders, {passive:true});
  window.addEventListener('resize', scheduleFrozenHeaders);
  window.addEventListener('afterprint', scheduleFrozenHeaders);
  if (window.ResizeObserver) {
    const observer = new ResizeObserver(scheduleFrozenHeaders);
    frozenTables.forEach(item => observer.observe(item.table));
  }
  if (document.fonts) document.fonts.ready.then(scheduleFrozenHeaders);
  updateFrozenHeaders();

})();
