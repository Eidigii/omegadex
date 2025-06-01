/* assets/js/modules/eggTable.js (V6.19m) */
// console.log("JS: eggTable.js loaded.");

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

function getCookie_eggTable(name) { const nameEQ = name + "="; const ca = document.cookie.split(';'); for (let i = 0; i < ca.length; i++) { let c = ca[i]; while (c.charAt(0) === ' ') c = c.substring(1, c.length); if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length); } return null; }
function initializeDefaultState_eggTable(table) { const cells = table.getElementsByTagName('td'); for (let cell of cells) { cell.innerText = "-"; cell.className = "cell-0"; } };
function loadTableState_eggTable(table) { const state = getCookie_eggTable('tableState'); if (!state) { initializeDefaultState_eggTable(table); return; } const cells = table.getElementsByTagName('td'); let cellValues = []; try { cellValues = JSON.parse(state); } catch (e) { console.error("JS:EggTable: Error parsing table state:", e); initializeDefaultState_eggTable(table); return; } for (let i = 0; i < cells.length; i++) { const cellValue = (cellValues && cellValues[i]) ? cellValues[i] : "-";  cells[i].innerText = cellValue; cells[i].className = cellValue === "✓" ? "cell-1" : "cell-0"; } };
function saveTableState_eggTable(table) { const cells = table.getElementsByTagName('td'); const state = Array.from(cells).map(cell => cell.innerText.trim()); document.cookie = "tableState=" + JSON.stringify(state) + "; path=/"; };
function toggleCell_eggTable(cell) { const currentValue = cell.innerText.trim(); const newValue = currentValue === "-" ? "✓" : "-"; cell.innerText = newValue; cell.className = newValue === "-" ? "cell-0" : "cell-1"; };

OMEGADEX_APP.initializeEggTable = () => { const table = document.getElementById('eggTable'); if (!table) return; if (table.dataset.initialized) return; table.dataset.initialized = true; loadTableState_eggTable(table); table.addEventListener('click', function (event) { if (event.target.tagName === 'TD') { toggleCell_eggTable(event.target); saveTableState_eggTable(table); } }); };