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
          /* ---- keys for localStorage + their fallback defaults ------------- */
          // const defaults = {
          //   bg1: "#f4f4f4",
          //   bg2: "#f4f4f4",
          //   module: "#f4f4f4",
          //   moduleDone: "#f4f4f4",
          //   moduleHighlight: "#e0f7fa",
          //   text1: "#333",
          //   text2: "#444",
          //   a1: "#007BFF",
          //   a2: "#e74c3c",
          // };

          // get variable names from css root dynamically (all starting with --)
          const root = getComputedStyle(document.documentElement);
          const defaults = {};
          for (const key of root) {
            if (key.startsWith("--")) {
              const value = root.getPropertyValue(key);
              defaults[key.substring(2)] = value.trim();
            }
          }
          console.log(defaults);

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