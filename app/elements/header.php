<div class="header-title">
  <h1>University Module Support System</h1>

  <input type="checkbox" id="menu-toggle" hidden>

  <label for="menu-toggle" class="menu-button">Menu</label>

  <!-- Modal overlay and content -->
  <div class="modal-overlay">
    <div class="modal-content">
      <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>

      <a href="?action=logout">Logout</a>
      <br>
      <br>

      <div id="themeControls">
        <strong style="font-size:13px;display:block;margin-bottom:.25rem">
          Theme&nbsp;customiser:
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
            bg1: '#f4f4f4',
            bg2: '#f4f4f4',
            module: '#f4f4f4',
            moduleDone: '#f4f4f4',
            moduleHighlight: '#e0f7fa',
            text1: '#333',
            text2: '#444',
            a1: '#007BFF',
            a2: '#e74c3c'
          };
          const keys = Object.keys(defaults);

          /* ---- elements ---------------------------------------------------- */
          const els = {
            reset: document.getElementById('resetTheme'),
            themeControls: document.getElementById('themeControls')
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


      <!-- Close button is just another label targeting the checkbox -->
      <label for="menu-toggle" class="close-button">Close</label>
    </div>
  </div>

</div>

<div class="top-links">
  <?php 
    $pageName = basename($_SERVER['PHP_SELF']);
  ?>

  <?php if ($_SESSION['userid'] === '0'): ?>
    <a href="admin.php">Admin Panel</a>
  <?php endif; ?>
  <?php if ($pageName == "index.php" && $isEditMode): ?>
    <a href="index.php">Switch to View Mode</a>
  <?php elseif($pageName == "index.php"): ?>
    <a href="index.php?mode=edit">Switch to Edit Mode</a>
  <?php else: // deactivated grey link?>
    <a class="disabled">Switch to Edit Mode</a>
  <?php endif; ?>
  <a href="index.php" class="<?php echo $pageName == 'index.php' ? 'active' : ''; ?>">Home</a>
  <a href="requirements.php" class="<?php echo $pageName == 'requirements.php' ? 'active' : ''; ?>">Requirements by
    Date</a>
  <a href="stats.php" class="<?php echo $pageName == 'stats.php' ? 'active' : ''; ?>">Statistics</a>

</div>