/**
 * Zanjir settings page: section tabs, live budget meter, matrix helpers.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-zanjir-settings]');
	if (!root) {
		return;
	}

	var navItems = root.querySelectorAll('[data-tab]');
	var panels = root.querySelectorAll('[data-panel]');
	var budgetInputs = root.querySelectorAll('[data-budget-input]');
	var ring = root.querySelector('[data-budget-ring]');
	var card = root.querySelector('[data-budget-card]');
	var hint = root.querySelector('[data-budget-hint]');

	function setTab(slug) {
		navItems.forEach(function (btn) {
			var on = btn.getAttribute('data-tab') === slug;
			btn.classList.toggle('is-active', on);
			btn.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		panels.forEach(function (panel) {
			var on = panel.getAttribute('data-panel') === slug;
			panel.classList.toggle('is-active', on);
			if (on) {
				panel.removeAttribute('hidden');
			} else {
				panel.setAttribute('hidden', '');
			}
		});
		try {
			var url = new URL(window.location.href);
			url.searchParams.set('tab', slug);
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			/* ignore */
		}
	}

	navItems.forEach(function (btn) {
		btn.addEventListener('click', function () {
			setTab(btn.getAttribute('data-tab'));
		});
	});

	function readBudget(name) {
		var el = root.querySelector('[data-budget-input="' + name + '"]');
		if (!el) {
			return 0;
		}
		var n = parseInt(el.value, 10);
		return isNaN(n) ? 0 : Math.max(0, n);
	}

	function updateBudget() {
		var tree = readBudget('tree');
		var staff = readBudget('staff');
		var bonus = readBudget('bonus');
		var total = tree + staff + bonus;
		var pct = total / 100;
		var over = total > 10000;
		var values = {
			tree: tree,
			staff: staff,
			bonus: bonus,
			total: total,
			pct: pct.toFixed(1)
		};

		Object.keys(values).forEach(function (key) {
			var node = root.querySelector('[data-budget-' + key + ']');
			if (node) {
				node.textContent = String(values[key]);
			}
		});

		if (ring) {
			ring.setAttribute('stroke-dasharray', Math.min(100, pct) + ', 100');
		}
		if (card) {
			card.classList.toggle('is-over', over);
		}
		if (hint) {
			hint.classList.toggle('is-visible', over);
		}
	}

	budgetInputs.forEach(function (input) {
		input.addEventListener('input', updateBudget);
		input.addEventListener('change', updateBudget);
	});

	function updateMatrixRow(row) {
		var depthEl = row.querySelector('[data-matrix-depth]');
		var capEl = row.querySelector('[data-matrix-cap]');
		var rateEls = row.querySelectorAll('[data-matrix-rate]');
		var status = row.querySelector('[data-matrix-status]');
		var foot = row.querySelector('[data-matrix-foot]');
		var depth = parseInt(depthEl && depthEl.value, 10) || 1;
		depth = Math.max(1, Math.min(3, depth));
		var cap = parseInt(capEl && capEl.value, 10) || 0;
		var sum = 0;

		rateEls.forEach(function (el, idx) {
			if (idx < depth) {
				var v = parseInt(el.value, 10);
				sum += isNaN(v) ? 0 : v;
			}
		});

		var ok = sum === cap;
		if (status) {
			status.classList.toggle('is-ok', ok);
			status.classList.toggle('is-bad', !ok);
			status.textContent = ok
				? row.getAttribute('data-ok-label') || status.textContent
				: row.getAttribute('data-bad-label') || status.textContent;
		}
		if (foot) {
			var tpl = foot.getAttribute('data-tpl');
			if (tpl) {
				foot.textContent = tpl
					.replace('%1$d', String(depth))
					.replace('%2$d', String(sum));
			}
		}
	}

	root.querySelectorAll('[data-matrix-row]').forEach(function (row) {
		row.querySelectorAll('input').forEach(function (input) {
			input.addEventListener('input', function () {
				updateMatrixRow(row);
			});
			input.addEventListener('change', function () {
				updateMatrixRow(row);
			});
		});
	});
})();
