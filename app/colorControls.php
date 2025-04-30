
<!-- ─── Theme controls ─────────────────────────────────────────────── -->
<div id="themeControls" style="position:fixed;bottom:1rem;right:1rem;
     background:#fff;border:1px solid #ccc;padding:.75rem .9rem;
     border-radius:8px;font:14px/1 sans-serif;box-shadow:0 2px 6px #0002;
     z-index:9999;">
  <strong style="font-size:13px;display:block;margin-bottom:.25rem">
    Theme&nbsp;customiser
  </strong>

  <div id="themeControls" style="margin-bottom:.5rem"></div>

  <button id="resetTheme" style="margin-top:.5rem;padding:.25rem .6rem">
    reset
  </button>
</div>

<script>
(() => {
  /* ---- keys for localStorage + their fallback defaults ------------- */
  const defaults = {
    bg1 : '#f4f4f4',
    bg2 : '#f4f4f4',
    module : '#f4f4f4',
    moduleDone : '#f4f4f4',
    moduleHighlight : '#e0f7fa',
    text1 : '#333',
    text2 : '#444',
    a1 : '#007BFF',
    a2 : '#e74c3c'
  };
  const keys = Object.keys(defaults);

  /* ---- elements ---------------------------------------------------- */
  const els = {
    reset : document.getElementById('resetTheme'),
    themeControls : document.getElementById('themeControls')
  }

    keys.forEach(k => {
        const el = document.createElement('input');
        el.type = 'color';
        el.value = defaults[k];
        el.id = k;
        el.style.verticalAlign = 'middle';
        el.style.marginLeft = '.5em';
        // when color changed, update css
        el.addEventListener('input', () => {
            const root = document.documentElement.style;
            root.setProperty('--' + k, el.value); // e.g.  --bg , --a1 , --a2
            localStorage.setItem('theme_' + k, el.value);
        });
        els[k] = el;
    
        const label = document.createElement('label');
        label.appendChild(el);
        label.appendChild(document.createTextNode(k.replace(/([a-z])([A-Z])/g, '$1 $2')));
        document.getElementById('themeControls').appendChild(document.createElement('br'));
        document.getElementById('themeControls').appendChild(label);
    });

  /* ---- read from storage OR use defaults, then apply --------------- */
  function loadTheme() {
    keys.forEach(k => {
      const val = localStorage.getItem('theme_' + k) || defaults[k];
      els[k].value = val;
    });
    applyTheme();
  }

  /* ---- write current pickers to storage, set CSS custom props ------ */
  function applyTheme() {
    const root = document.documentElement.style;
    keys.forEach(k => {
      const val = els[k].value;
      root.setProperty('--' + k, val);          // e.g.  --bg , --a1 , --a2
      localStorage.setItem('theme_' + k, val);
    });
  }

  /* ---- reset to factory ------------------------------------------- */
  function resetTheme() {
    keys.forEach(k => localStorage.removeItem('theme_' + k));
    loadTheme();
  }

  /* ---- wire events ------------------------------------------------- */
  keys.forEach(k => els[k].addEventListener('input', applyTheme));
  els.reset.addEventListener('click', resetTheme);

  /* ---- initialise on first load ----------------------------------- */
  loadTheme();
})();
</script>
<!-- ─────────────────────────────────────────────────────────────────── -->
