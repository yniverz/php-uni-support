<?php
// public/planner.php  – interactive term‑planner (rev 5)
// • Auto‑scroll window while dragging near viewport edges
// • Collapsed term state is stored in localStorage and restored on load
// • Keeps all previous features (sticky preview, Δ intra‑term, grey‑out, drag area width)

session_start();
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/logic.php';

$modules = $data['modules'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Plan Terms - University Module Support System</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="container">
        <header>
            <?php include __DIR__ . '/app/elements/header.php'; ?>
        </header>
        <h2>Term Planner <small style="font-weight:normal">(drag modules, then Save)</small></h2>
        <div class="planner-wrapper">
            <div class="left-pane" id="leftPane"></div>
            <div class="right-pane" id="rightPane"></div>
        </div>
        <form id="saveForm" method="post" style="margin-top:18px;">
            <input type="hidden" name="save_plan_json" id="saveData">
            <button type="button" onclick="savePlan()">💾 Save changes</button>
            <button type="button" onclick="window.location.href='index.php'" style="margin-left:8px;">✖ Cancel</button>
        </form>
    </div>

    <script>
        const modules = <?php echo json_encode($modules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        let maxTerm = Math.max(1, ...modules.map(m => Number(m.term || 1)));

        //──── helpers ───────────────────────────────────────────────────────────────
        function moduleCredits(m) {
            return (m.requirements || []).reduce((s, r) => s + Number(r.credits || 0), 0);
        }

        function creditsPerTerm(t) {
            return modules.reduce((s, m) => Number(m.term) === t ? s + moduleCredits(m) : s, 0);
        }

        function daysDiff(d) {
            if (!d) return null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const tgt = new Date(d);
            return Math.round((tgt - today) / 86400000);
        }

        function timeDiffStr(n) {
            return n === null ? '' : (n >= 0 ? '+' : '') + n + 'd';
        }

        /***** Left pane (drag & drop) **********************************************/
        function renderLeft() {
            const left = document.getElementById('leftPane');
            left.innerHTML = '';
            for (let t = 1; t <= maxTerm; t++) {
                const col = document.createElement('div');
                col.className = 'term-column';
                col.innerHTML = `<div class="term-head"><span>Term ${t}</span><span>${creditsPerTerm(t)} cr</span></div>`;
                const ul = document.createElement('ul');
                ul.className = 'module-list';
                ul.dataset.term = t;
                ul.addEventListener('dragover', e => e.preventDefault());
                ul.addEventListener('dragenter', () => ul.classList.add('drop-target'));
                ul.addEventListener('dragleave', () => ul.classList.remove('drop-target'));
                ul.addEventListener('drop', e => {
                    e.preventDefault();
                    ul.classList.remove('drop-target');
                    const idx = Number(e.dataTransfer.getData('idx'));
                    if (!isNaN(idx)) {
                        modules[idx].term = t;
                        renderAll();
                    }
                });
                modules.forEach((m, idx) => {
                    if (Number(m.term) !== t) return;
                    const li = document.createElement('li');
                    li.className = 'module-item';
                    if (m.allDone) li.classList.add('done');
                    li.draggable = true;
                    li.textContent = `${m.name} (${moduleCredits(m)} cr)`;
                    li.addEventListener('dragstart', e => {
                        e.dataTransfer.setData('idx', idx);
                        li.classList.add('dragging');
                        dragScroll.start();
                    });
                    li.addEventListener('dragend', () => {
                        li.classList.remove('dragging');
                        dragScroll.stop();
                    });
                    ul.appendChild(li);
                });
                col.appendChild(ul);
                left.appendChild(col);
            }
            const addBtn = document.createElement('button');
            addBtn.textContent = '+ Add Term';
            addBtn.onclick = () => {
                maxTerm++;
                renderAll();
            };
            left.appendChild(addBtn);
        }

        /***** Right pane (requirements preview) ***********************************/
        function renderRight() {
            const right = document.getElementById('rightPane');
            right.innerHTML = '';
            const todayStr = new Date().toISOString().slice(0, 10);
            const STORAGE_KEY = 'plannerCollapsedTerms';
            const collapsedSet = new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
            const table = document.createElement('table');
            table.className = 'req-preview';
            table.innerHTML = '<thead><tr><th style="width:70px">Until</th><th style="width:75px">Δ</th><th style="width:100px">Date</th><th>Module – Requirement</th></tr></thead>';
            const tbody = document.createElement('tbody');

            for (let t = 1; t <= maxTerm; t++) {
                const termReqs = [];
                modules.forEach(m => {
                    if (Number(m.term) !== t) return;
                    (m.requirements || []).forEach(r => termReqs.push({
                        module: m.name,
                        desc: r.description,
                        date: r.date || '',
                        done: !!r.done
                    }));
                });
                if (!termReqs.length) continue;
                termReqs.sort((a, b) => {
                    const da = a.done ? 1 : 0,
                        db = b.done ? 1 : 0;
                    if (da !== db) return da - db;
                    const dA = a.date || '9999-12-31',
                        dB = b.date || '9999-12-31';
                    return dA < dB ? -1 : dA > dB ? 1 : 0;
                });

                const headRow = document.createElement('tr');
                headRow.className = 'term-row';
                headRow.dataset.term = t;
                const isCollapsed = collapsedSet.has(t);
                headRow.classList.toggle('collapsed', isCollapsed);
                headRow.innerHTML = `<td colspan="4">${isCollapsed ? '▶' : '▼'} Term ${t}</td>`;
                tbody.appendChild(headRow);

                let lastDate = null;
                termReqs.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.classList.add(`term-${t}`);
                    const past = !r.done && r.date && r.date < todayStr;
                    if (r.done || past) tr.classList.add('grey');
                    const until = timeDiffStr(daysDiff(r.date));
                    let delta = '—';
                    if (r.date && lastDate) delta = Math.abs(Math.round((new Date(r.date) - new Date(lastDate)) / 86400000)) + 'd';
                    if (r.date) lastDate = r.date;
                    tr.innerHTML = `<td>${until}</td><td>${delta}</td><td>${r.date || '—'}</td><td>${r.module}: ${r.desc}</td>`;
                    if (isCollapsed) tr.classList.add('hidden');
                    tbody.appendChild(tr);
                });
            }
            table.appendChild(tbody);
            right.appendChild(table);

            // collapse/expand behaviour -------------------------------------------------
            tbody.querySelectorAll('.term-row').forEach(row => {
                row.addEventListener('click', () => {
                    const term = Number(row.dataset.term);
                    const nowCollapsed = row.classList.toggle('collapsed');
                    row.firstChild.textContent = (nowCollapsed ? '▶' : '▼') + ' Term ' + term;
                    tbody.querySelectorAll(`.term-${term}`).forEach(r => r.classList.toggle('hidden', nowCollapsed));
                    // persist collapsed set
                    if (nowCollapsed) collapsedSet.add(term);
                    else collapsedSet.delete(term);
                    localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsedSet]));
                });
            });
        }

        /***** auto‑scroll while dragging *******************************************/
        const dragScroll = {
            EDGE: 80,
            SPEED: 1,
            _rafId: null,
            _handler(e) {
                const y = e.clientY;
                if (y < this.EDGE) {
                    window.scrollBy(0, -this.SPEED);
                } else if (window.innerHeight - y < this.EDGE) {
                    window.scrollBy(0, this.SPEED);
                }
            },
            start() {
                if (this._rafId !== null) return;
                const move = e => {
                    this._handler(e);
                    this._rafId = requestAnimationFrame(() => { });
                };
                document.addEventListener('dragover', move);
                this._stopFn = () => {
                    document.removeEventListener('dragover', move);
                    cancelAnimationFrame(this._rafId);
                    this._rafId = null;
                };
            },
            stop() {
                if (this._stopFn) {
                    this._stopFn();
                    this._stopFn = null;
                }
            }
        };

        /***** render cycle **********************************************************/
        function renderAll() {
            renderLeft();
            renderRight();
        }
        renderAll();

        function savePlan() {
            const payload = {};
            modules.forEach((m, i) => payload[i] = Number(m.term));
            document.getElementById('saveData').value = JSON.stringify(payload);
            document.getElementById('saveForm').submit();
        }
    </script>
    <footer><?php include __DIR__ . '/app/elements/footer.php'; ?></footer>
</body>

</html>