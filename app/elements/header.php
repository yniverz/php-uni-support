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

      <div id="themeCustomiser">
        <strong style="font-size: 13px; display: block; margin-bottom: 0.25rem">
          Theme&nbsp;customiser:
        </strong>

        <table id="themeTable"></table>

        <button id="resetTheme">reset</button>
      </div>


      <script>
        (() => {
          /**
           * Collect every custom property defined on :root and
           * return an object { primary: "#00eb9b", gap: "1rem", … }
           */
          function collectRootVars() {
            const out = {};

            /* ---------- 1. Fast path: CSS Typed OM ----------- */
            const root = document.documentElement;
            if (root.computedStyleMap) {                 // Chrome / Safari today
              root.computedStyleMap()                   // StylePropertyMapReadOnly
                  .forEach((val, name) => {
                    if (name.startsWith('--')) {
                      out[name.slice(2)] = val.toString().trim();
                    }
                  });
              if (Object.keys(out).length) return out;  // done
            }

            /* ---------- 2. Universal fallback --------------- */
            for (const sheet of Array.from(document.styleSheets)) {
              let rules;
              try { rules = sheet.cssRules; }           // may throw on cross-origin
              catch { continue; }

              for (const rule of rules) {
                if (rule.type !== CSSRule.STYLE_RULE) continue;
                for (const name of rule.style) {
                  if (!name.startsWith('--')) continue;

                  // resolve the *current* value with getComputedStyle so that
                  // overrides, cascade and :root inline changes are all honoured.
                  const value = getComputedStyle(root).getPropertyValue(name);
                  out[name.slice(2)] = value.trim();
                }
              }
            }
            return out;
          }

          const defaults = collectRootVars();

          // sort keys alphabetically
          const keys = Object.keys(defaults).sort((a, b) => a.localeCompare(b));

          /* ---- elements ---------------------------------------------------- */
          const table = document.getElementById("themeTable");
          const resetBtn = document.getElementById("resetTheme");

          /* ---- helpers ----------------------------------------------------- */
          function makeRow(key, value) {
            const tr = document.createElement("tr");

            const tdPicker = document.createElement("td");
            const picker = document.createElement("input");
            picker.type = "color";
            picker.id = key;
            picker.value = value;
            picker.style.width = "100%"; // makes alignment snappy
            picker.addEventListener("input", applyTheme);
            tdPicker.appendChild(picker);

            const tdLabel = document.createElement("td");
            tdLabel.textContent = key.replace(/([a-z])([A-Z])/g, "$1 $2");

            tr.appendChild(tdPicker);
            tr.appendChild(tdLabel);
            return tr;
          }

          function applyTheme() {
            const root = document.documentElement.style;
            keys.forEach((k) => {
              const input = document.getElementById(k);
              if (input) {
                root.setProperty("--" + k, input.value);
                localStorage.setItem("theme_" + k, input.value);
              }
            });
          }

          function loadTheme() {
            table.innerHTML = "";
            keys.forEach((k) => {
              const saved = localStorage.getItem("theme_" + k) || defaults[k];
              table.appendChild(makeRow(k, saved));
            });
            applyTheme();
          }

          function resetTheme() {
            keys.forEach((k) => localStorage.removeItem("theme_" + k));
            loadTheme();
          }

          /* ---- wire up events --------------------------------------------- */
          resetBtn.addEventListener("click", resetTheme);

          /* ---- first run --------------------------------------------------- */
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
  <?php elseif ($pageName == "index.php"): ?>
    <a href="index.php?mode=edit">Switch to Edit Mode</a>
  <?php else: // deactivated grey link ?>
    <a class="disabled">Switch to Edit Mode</a>
  <?php endif; ?>
  <a href="index.php" class="<?php echo $pageName == 'index.php' ? 'active' : ''; ?>">Home</a>
  <a href="requirements.php" class="<?php echo $pageName == 'requirements.php' ? 'active' : ''; ?>">Requirements by
    Date</a>
  <a href="stats.php" class="<?php echo $pageName == 'stats.php' ? 'active' : ''; ?>">Statistics</a>
  <a href="planner.php" class="<?= $pageName == 'planner.php' ? 'active' : '' ?>">Planner</a>

</div>