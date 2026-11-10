(function () {
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  const post = (url, data) => { const fd = new FormData(); fd.append('csrf', csrf); for (const k in data) fd.append(k, data[k]);
    return fetch(url, { method: 'POST', body: fd }).then(r => r.json()); };

  // mobile sidebar
  const sb = document.getElementById('sidebar'), bd = document.getElementById('sbBackdrop');
  document.getElementById('sbToggle')?.addEventListener('click', () => { sb.classList.toggle('open'); bd.classList.toggle('show'); });
  bd?.addEventListener('click', () => { sb.classList.remove('open'); bd.classList.remove('show'); });

  // confirm dialogs
  document.addEventListener('click', e => { const c = e.target.closest('[data-confirm]'); if (c && !confirm(c.dataset.confirm)) e.preventDefault(); });

  // save / unsave job (AJAX)
  document.addEventListener('click', e => {
    const b = e.target.closest('.btn-save'); if (!b) return;
    e.preventDefault();
    post(BASE + '/ajax.php', { action: 'toggle_save', job_id: b.dataset.id }).then(r => {
      if (!r.ok) return;
      b.querySelector('i').className = 'bi ' + (r.saved ? 'bi-heart-fill text-danger' : 'bi-heart');
      if (b.dataset.remove && !r.saved) b.closest('.job-card')?.remove();
    });
  });

  // dynamic job search (filters submit via AJAX, results swapped in place)
  const form = document.getElementById('filterForm'), list = document.getElementById('jobList');
  if (form && list) {
    let t;
    const run = () => {
      const qs = new URLSearchParams(new FormData(form)); qs.delete('page');
      fetch(location.pathname + '?' + qs + '&ajax=1').then(r => r.text()).then(h => { list.innerHTML = h; history.replaceState(null, '', '?' + qs); });
    };
    form.addEventListener('submit', e => { e.preventDefault(); run(); });
    form.querySelectorAll('input[type=text],input[type=search]').forEach(i => i.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 350); }));
    form.querySelectorAll('select,input[type=checkbox],input[type=radio]').forEach(i => i.addEventListener('change', run));
    list.addEventListener('click', e => { const a = e.target.closest('.pagination a'); if (!a) return;
      e.preventDefault(); fetch(a.href + '&ajax=1').then(r => r.text()).then(h => { list.innerHTML = h; scrollTo({ top: 0, behavior: 'smooth' }); }); });
  }

  // real-time polling: notification badge + application status
  if (ROLE) {
    let known = null;
    const poll = () => fetch(BASE + '/ajax.php?action=poll').then(r => r.json()).then(r => {
      document.querySelectorAll('.js-notif-badge').forEach(b => { b.textContent = r.unread; b.hidden = !r.unread; });
      if (known !== null && r.unread > known) { const t = document.createElement('div'); t.className = 'alert alert-info position-fixed bottom-0 end-0 m-3 shadow'; t.style.zIndex = 2000; t.textContent = 'You have a new notification.'; document.body.appendChild(t); setTimeout(() => t.remove(), 4000); }
      known = r.unread;
      for (const id in (r.apps || {})) document.querySelectorAll('[data-app-status="' + id + '"]').forEach(el => {
        if (el.textContent.trim() !== r.apps[id]) { el.textContent = r.apps[id]; el.className = 'badge-status s-' + r.apps[id].toLowerCase().replace(' ', '-'); } });
    }).catch(() => {});
    poll(); setInterval(poll, 20000);
  }

  // password show/hide
  document.querySelectorAll('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => { const i = document.querySelector(b.dataset.togglePw); i.type = i.type === 'password' ? 'text' : 'password'; }));
})();
