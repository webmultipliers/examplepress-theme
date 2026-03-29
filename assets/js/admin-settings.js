/**
 * ExamplePress Settings Page
 *
 * Renders the read-only configuration dashboard from data
 * localized via wp_add_inline_script (window.ExamplePressData).
 */
document.addEventListener('DOMContentLoaded', () => {
	if (!window.ExamplePressData) {
		return;
	}

	const {
		features,
		colors,
		layout,
		fonts,
		sizes,
		blocks,
		pluginsReq,
		pluginsRec,
		configFiles,
		healthChecks,
		docs,
		hooks,
	} = window.ExamplePressData;

	/* ── Tab switching ──────────────────────────────────────────────── */

	document.querySelectorAll('.ep-tab').forEach(tab => {
		tab.addEventListener('click', () => {
			document.querySelectorAll('.ep-tab').forEach(t => t.setAttribute('aria-selected', 'false'));
			document.querySelectorAll('.ep-panel').forEach(p => p.setAttribute('aria-hidden', 'true'));
			tab.setAttribute('aria-selected', 'true');
			document.getElementById(tab.getAttribute('aria-controls')).setAttribute('aria-hidden', 'false');
		});
	});

	/* ── Render helpers ─────────────────────────────────────────────── */

	function badge(on, label) {
		const cls = on ? 'badge-on' : 'badge-off';
		return `<span class="ep-badge ${cls}"><span class="ep-dot"></span>${label || (on ? 'Enabled' : 'Disabled')}</span>`;
	}

	function srcTag(src) {
		const cls = src === 'json' ? 'src-json' : src === 'php' ? 'src-php' : '';
		const label = src === 'php' ? 'filter' : src;
		return `<span class="ep-src ${cls}">${label}</span>`;
	}

	function esc(str) {
		const d = document.createElement('div');
		d.textContent = str;
		return d.innerHTML;
	}

	function featureTable(containerId, items) {
		const el = document.getElementById(containerId);
		if (!el || !items || !items.length) return;
		let html = '<div class="ep-row ep-row-head ep-cols-3"><div class="ep-th">Feature</div><div class="ep-th">Status</div><div class="ep-th">Source</div></div>';
		items.forEach(f => {
			html += `<div class="ep-row ep-cols-3">
				<div class="ep-td-label"><span class="ep-name">${esc(f.name)}</span><span class="ep-id">${esc(f.id)}</span>${f.opts ? `<span class="ep-opt"><em>${esc(f.opts)}</em></span>` : ''}</div>
				<div>${badge(f.on)}</div>
				<div>${srcTag(f.src)}</div>
			</div>`;
		});
		el.innerHTML = html;
	}

	function highlightJson(obj) {
		return JSON.stringify(obj, null, 2)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"([^"]+)":/g, '<span class="j-key">"$1"</span>:')
			.replace(/: "(.*?)"/g, ': <span class="j-str">"$1"</span>')
			.replace(/: (true|false)/g, ': <span class="j-bool">$1</span>')
			.replace(/: (\d+\.?\d*)/g, ': <span class="j-num">$1</span>')
			.replace(/: (null)/g, ': <span class="j-null">$1</span>');
	}

	function pluginTable(containerId, items, tier) {
		const el = document.getElementById(containerId);
		if (!el || !items || !items.length) return;
		const tierCls = tier === 'required' ? 'tier-req' : 'tier-rec';
		let html = '<div class="ep-row ep-row-head ep-cols-4"><div class="ep-th">Plugin</div><div class="ep-th">Tier</div><div class="ep-th">Status</div><div class="ep-th">Link</div></div>';
		items.forEach(p => {
			const sCls = p.status === 'active' ? 'badge-on' : p.status === 'installed' ? 'badge-warn' : 'badge-err';
			const sLbl = p.status === 'active' ? 'Active' : p.status === 'installed' ? 'Installed' : 'Missing';
			let domain = '';
			try { domain = new URL(p.url).hostname; } catch (e) { domain = p.url; }
			html += `<div class="ep-row ep-cols-4">
				<div class="ep-td-label"><span class="ep-name">${esc(p.name)}</span><span class="ep-id">${esc(p.slug)}</span></div>
				<div><span class="ep-tier ${tierCls}">${tier}</span></div>
				<div><span class="ep-badge ${sCls}"><span class="ep-dot"></span>${sLbl}</span></div>
				<div><a href="${esc(p.url)}" class="ep-link" target="_blank" rel="noopener">${esc(domain)} &rarr;</a></div>
			</div>`;
		});
		el.innerHTML = html;
	}

	function healthTable(containerId, items) {
		const el = document.getElementById(containerId);
		if (!el || !items || !items.length) return;
		let html = '<div class="ep-row ep-row-head ep-cols-health"><div class="ep-th">Check</div><div class="ep-th">Status</div><div class="ep-th">Requirement</div></div>';
		items.forEach(h => {
			const cls = h.status === 'pass' ? 'badge-on' : h.status === 'warn' ? 'badge-warn' : h.status === 'fail' ? 'badge-err' : 'badge-info';
			const lbl = h.status === 'pass' ? 'Pass' : h.status === 'warn' ? 'Warning' : h.status === 'fail' ? 'Fail' : 'Info';
			const noteColor = h.status === 'fail' ? 'var(--red)' : 'var(--amber)';
			html += `<div class="ep-row ep-cols-health">
				<div class="ep-td-label"><span class="ep-name">${esc(h.name)}</span><span class="ep-id">${esc(h.detail)}</span>${h.note ? `<span class="ep-desc-small" style="color:${noteColor}">${esc(h.note)}</span>` : ''}</div>
				<div><span class="ep-badge ${cls}"><span class="ep-dot"></span>${lbl}</span></div>
				<div><span class="ep-id">${esc(h.req)}</span></div>
			</div>`;
		});
		el.innerHTML = html;
	}

	/* ── Features tab ───────────────────────────────────────────────── */

	featureTable('tbl-theme-support', features['theme-support']);
	featureTable('tbl-editor', features.editor);
	featureTable('tbl-guards', features.guards);
	featureTable('tbl-admin', features.admin);
	featureTable('tbl-options', features.options);

	/* ── Design tab — Colors ────────────────────────────────────────── */

	const colorsGrid = document.getElementById('colors-grid');
	if (colorsGrid && colors.length) {
		colorsGrid.innerHTML = colors.map(c => `
			<div class="ep-color-card">
				<div class="ep-color-swatch" style="background:${c.color}"></div>
				<div class="ep-color-info">
					<div class="ep-color-name">${esc(c.name)}</div>
					<div class="ep-color-meta"><span class="ep-color-hex">${esc(c.color)}</span><span class="ep-color-slug">${esc(c.slug)}</span></div>
				</div>
			</div>`).join('');
	}

	/* ── Design tab — Layout ────────────────────────────────────────── */

	const layoutEl = document.getElementById('layout-visual');
	if (layoutEl && layout) {
		const wideNum = parseFloat(layout.wideSize) || 1200;
		const contentNum = parseFloat(layout.contentSize) || 800;
		const ratio = wideNum > 0 ? ((contentNum / wideNum) * 100).toFixed(1) : 66.7;
		layoutEl.innerHTML = `
			<div class="ep-layout-bar bar-wide"><span class="ep-layout-lbl">Wide &mdash; ${esc(layout.wideSize)}</span></div>
			<div style="display:flex;justify-content:center;">
				<div class="ep-layout-bar bar-content" style="width:${ratio}%"><span class="ep-layout-lbl">Content &mdash; ${esc(layout.contentSize)}</span></div>
			</div>`;
	}

	/* ── Design tab — Fonts ─────────────────────────────────────────── */

	const typeStack = document.getElementById('type-stack');
	if (typeStack && fonts.length) {
		typeStack.innerHTML = fonts.map(f => `
			<div class="ep-type-row">
				<div class="ep-type-meta"><span class="ep-type-name">${esc(f.name)}</span><span class="ep-type-slug">${esc(f.slug)}</span></div>
				<div class="ep-type-preview" style="font-family:${f.stack};">The quick brown fox jumps over the lazy dog &mdash; 0123456789</div>
			</div>`).join('');
	}

	/* ── Design tab — Sizes ─────────────────────────────────────────── */

	const sizeScale = document.getElementById('size-scale');
	if (sizeScale && sizes.length) {
		sizeScale.innerHTML = sizes.map(s => `
			<div class="ep-size-item">
				<span class="ep-size-sample" style="font-size:${s.size}">Aa</span>
				<span class="ep-size-label">${esc(s.name)}<br>${esc(s.slug)} &middot; ${esc(s.size)}</span>
			</div>`).join('');
	}

	/* ── Blocks tab ─────────────────────────────────────────────────── */

	const tblBlocks = document.getElementById('tbl-blocks');
	if (tblBlocks && blocks.length) {
		let html = '<div class="ep-row ep-row-head ep-cols-blocks"><div class="ep-th">Block</div><div class="ep-th">Category</div><div class="ep-th">Source</div><div class="ep-th">Type</div></div>';
		const cats = [...new Set(blocks.map(b => b.cat))];
		cats.forEach(cat => {
			html += `<div class="ep-block-cat">${esc(cat)}</div>`;
			blocks.filter(b => b.cat === cat).forEach(b => {
				const typeCls = b.type === 'template' ? 'badge-on' : b.type === 'system' ? 'badge-info' : 'badge-off';
				html += `<div class="ep-row ep-cols-blocks">
					<div class="ep-td-label"><span class="ep-name">${esc(b.title)}</span><span class="ep-id">${esc(b.name)}</span></div>
					<div><span class="ep-id">${esc(b.cat)}</span></div>
					<div><span class="ep-src">${esc(b.source)}</span></div>
					<div><span class="ep-badge ${typeCls}"><span class="ep-dot"></span>${esc(b.type)}</span></div>
				</div>`;
			});
		});
		tblBlocks.innerHTML = html;
	}

	/* ── Plugins tab ────────────────────────────────────────────────── */

	pluginTable('tbl-plugins-req', pluginsReq, 'required');
	pluginTable('tbl-plugins-rec', pluginsRec, 'recommended');

	/* ── Config tab ─────────────────────────────────────────────────── */

	const configSwitcher = document.getElementById('config-switcher');
	const configViewer = document.getElementById('config-viewer');
	if (configSwitcher && configViewer && configFiles) {
		const files = Object.keys(configFiles);
		let activeFile = files[0] || null;

		function renderConfig(name) {
			activeFile = name;
			configSwitcher.querySelectorAll('.ep-config-tab').forEach(t =>
				t.classList.toggle('active', t.dataset.file === name)
			);
			configViewer.innerHTML = `<div class="ep-json-block">
				<div class="ep-json-header"><span class="ep-json-filename">${esc(name)}</span><span class="ep-json-badge">read-only</span></div>
				<pre class="ep-json-body">${highlightJson(configFiles[name])}</pre>
			</div>`;
		}

		files.forEach(f => {
			const btn = document.createElement('button');
			btn.className = 'ep-config-tab';
			btn.textContent = f;
			btn.dataset.file = f;
			btn.addEventListener('click', () => renderConfig(f));
			configSwitcher.appendChild(btn);
		});

		if (activeFile) renderConfig(activeFile);
	}

	/* ── Health tab ─────────────────────────────────────────────────── */

	if (healthChecks) {
		const all = [
			...(healthChecks.env || []),
			...(healthChecks.theme || []),
			...(healthChecks.router || []),
			...(healthChecks.security || []),
		];
		const pass = all.filter(h => h.status === 'pass').length;
		const warn = all.filter(h => h.status === 'warn').length;
		const fail = all.filter(h => h.status === 'fail').length;
		const info = all.filter(h => h.status === 'info').length;

		const summary = document.getElementById('health-summary');
		if (summary) {
			summary.innerHTML = `
				<div class="ep-health-stat"><div class="ep-health-num val-ok">${pass}</div><div class="ep-health-lbl">Passing</div></div>
				<div class="ep-health-stat"><div class="ep-health-num" style="color:var(--amber)">${warn}</div><div class="ep-health-lbl">Warnings</div></div>
				<div class="ep-health-stat"><div class="ep-health-num" style="color:var(--red)">${fail}</div><div class="ep-health-lbl">Failing</div></div>
				<div class="ep-health-stat"><div class="ep-health-num" style="color:var(--blue)">${info}</div><div class="ep-health-lbl">Info</div></div>`;
		}

		healthTable('tbl-health-env', healthChecks.env);
		healthTable('tbl-health-theme', healthChecks.theme);
		healthTable('tbl-health-router', healthChecks.router);
		healthTable('tbl-health-security', healthChecks.security);
	}

	/* ── Docs tab ───────────────────────────────────────────────────── */

	const docsCards = document.getElementById('docs-cards');
	if (docsCards && docs.length) {
		docsCards.innerHTML = docs.map(d => `
			<a class="ep-doc-card" href="${esc(d.link)}" target="_blank" rel="noopener">
				<div class="ep-doc-card-eyebrow">${esc(d.eyebrow)}</div>
				<div class="ep-doc-card-title">${esc(d.title)}</div>
				<div class="ep-doc-card-desc">${esc(d.desc)}</div>
				<div class="ep-doc-card-link">${esc(d.label)} &rarr;</div>
			</a>`).join('');
	}

	const hooksList = document.getElementById('hooks-list');
	if (hooksList && hooks.length) {
		hooksList.innerHTML = hooks.map(h => `
			<div class="ep-hook-item">
				<span class="ep-hook-type ${h.type === 'filter' ? 'hook-filter' : 'hook-action'}">${esc(h.type)}</span>
				<span class="ep-hook-name">${esc(h.name)}</span>
				<span class="ep-hook-desc">${esc(h.desc)}</span>
			</div>`).join('');
	}

	/* ── Update tab counts ──────────────────────────────────────────── */

	const featureCount = Object.values(features).reduce((sum, arr) => sum + (arr ? arr.length : 0), 0);
	const counts = {
		't-features': featureCount,
		't-blocks': blocks.length,
		't-plugins': (pluginsReq ? pluginsReq.length : 0) + (pluginsRec ? pluginsRec.length : 0),
	};
	Object.entries(counts).forEach(([tabId, count]) => {
		const tab = document.getElementById(tabId);
		if (!tab) return;
		let countEl = tab.querySelector('.ep-tab-count');
		if (!countEl) {
			countEl = document.createElement('span');
			countEl.className = 'ep-tab-count';
			tab.appendChild(countEl);
		}
		countEl.textContent = count;
	});
});
