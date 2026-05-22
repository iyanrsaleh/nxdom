// ─────────────────────────────────────────────────────────────────────────────
// NexaChart — renders a Chart.js chart from a saved Analysis (IndexedDB)
//
// Usage:
//   const nxChart = await NXUI.Chart({ key:'petani_group', target:'#Chart', type:'bar' });
//   return `<div id="Chart"></div>`;
//
// Options:
//   key    {string}  — nama analysis (sama dengan `name` yang disimpan di bucketsStore/analysis)
//   target {string}  — CSS selector elemen container
//   type   {string}  — override tipe chart: 'bar'|'pie'|'donut'|'line'|'top5'|'' (kosong=ambil dari analysis)
//   title  {string}  — override judul chart (opsional)
//   height {number}  — tinggi canvas px (default: 300)
// ─────────────────────────────────────────────────────────────────────────────

const _COLORS = [
	'#4c8ef7','#f7954c','#52d68a','#f74c4c','#9b59f7',
	'#f7d94c','#4ccef7','#f74c9e','#88c846','#8f6cf7'
];

function _genColors(n) {
	if (n <= _COLORS.length) return _COLORS.slice(0, n);
	return Array.from({ length: n }, (_, i) => {
		const h = Math.round((i * 360) / n);
		const s = 65 + (i % 3) * 10;
		const l = 52 + (i % 2) * 8;
		return `hsl(${h},${s}%,${l}%)`;
	});
}

function _waitFor(fn, maxMs = 5000, interval = 80) {
	return new Promise((resolve, reject) => {
		const start = Date.now();
		const tick = () => {
			const val = fn();
			if (val) return resolve(val);
			if (Date.now() - start >= maxMs) return reject(new Error('NexaChart: timeout waiting for dependency'));
			setTimeout(tick, interval);
		};
		tick();
	});
}

function _loadChartJS() {
	return new Promise((resolve, reject) => {
		if (window.Chart) return resolve();
		// Coba pakai bundled chart.umd.min.js relatif ke Nexa.js
		const candidates = [
			new URL('./chart.umd.min.js', import.meta.url).href,'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
		];
		let tried = 0;
		const tryNext = () => {
			if (tried >= candidates.length) return reject(new Error('Gagal memuat Chart.js'));
			const s = document.createElement('script');
			s.src = candidates[tried++];
			s.onload = () => resolve();
			s.onerror = tryNext;
			document.head.appendChild(s);
		};
		tryNext();
	});
}

async function _fetchAnalysis(key, ctx = {}) {
	// Tunggu NXUI.ref siap
	const ref = await _waitFor(() => window.NXUI?.ref);

	const normalizeKey = (v) => String(v ?? '')
		.trim()
		.toLowerCase()
		.replaceAll(/[^a-z0-9]+/g, '_')
		.replaceAll(/^_+|_+$/g, '');

	const toEntries = (source) => {
		if (!source) return [];
		if (Array.isArray(source)) return source.filter(x => x && typeof x === 'object');
		if (typeof source !== 'object') return [];
		return Object.entries(source)
			.filter(([, v]) => v && typeof v === 'object')
			.map(([k, v]) => ({ __key: k, ...v }));
	};

	const findByKey = (entries, wantedKey) => {
		const wanted = normalizeKey(wantedKey);
		if (!wanted) return null;
		return entries.find((x) => {
			const cands = [x?.__key, x?.key, x?.id, x?.name];
			return cands.some((c) => normalizeKey(c) === wanted);
		}) || null;
	};

	const mapStore = (store) => {
		const listCandidate = store && typeof store === 'object' && 'list' in store ? store.list : store;
		return toEntries(listCandidate);
	};

	const tokenCandidate = (ctx && typeof ctx === 'object')
		? (ctx.token || ctx.appId || ctx.appID || '')
		: '';

	// 1) Prioritas: bucketsStore('analysis')
	let entries = [];
	try {
		const store = await ref.get('bucketsStore', 'analysis');
		entries = mapStore(store);
		const item = findByKey(entries, key);
		if (item) return item;
	} catch (_) {
		// lanjut fallback
	}

	// 2) Fallback: nexaStore(token).production.analysis
	if (tokenCandidate) {
		try {
			const row = await ref.nexaStore(tokenCandidate).get();
			const fallbackEntries = toEntries(row?.production?.analysis);
			const exact = findByKey(fallbackEntries, key);
			if (exact) return exact;
			if (fallbackEntries.length) return fallbackEntries[0];
		} catch (_) {
			// biarkan lanjut ke error akhir
		}
	}

	throw new Error(`NexaChart: analysis "${key}" tidak ditemukan`);
}

async function _executeSQL(sql) {
  console.log('sql:', sql);
	const raw = await NXUI.Storage().models('Office').tabelExecuteQuery({ sql });
	const res = raw?.data ?? raw;
  console.log('res:', res);
	if (!res?.success) throw new Error(res?.message || 'Query gagal');
	if (res.type !== 'select') throw new Error('NexaChart hanya mendukung query SELECT');
	return { columns: res.columns, rows: res.rows };
}

function _autoDetect(columns, rows, labelCol, valCol) {
	const sample = rows[0] || {};
	let lCol = labelCol, vCol = valCol;
	if (!lCol) lCol = columns.find(c => isNaN(Number(sample[c])) || sample[c] === null || String(sample[c]).trim() === '') || columns[0];
	if (!vCol) vCol = columns.find(c => c !== lCol && !isNaN(Number(sample[c])) && sample[c] !== null) || columns[1] || columns[0];
	return { lCol, vCol };
}

function _buildChartConfig(type, labels, values, vCol, height) {
	const bg = _genColors(labels.length);

	if (type === 'pie' || type === 'donut') {
		return {
			type: type === 'donut' ? 'doughnut' : 'pie',
			data: { labels, datasets: [{ data: values, backgroundColor: bg, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }] },
			options: {
				cutout: type === 'donut' ? '55%' : '0%',
				responsive: true, maintainAspectRatio: false,
				plugins: {
					legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } },
					tooltip: { callbacks: { label: ctx => { const tot = ctx.dataset.data.reduce((a,b)=>a+b,0); return ` ${ctx.label}: ${ctx.formattedValue} (${((ctx.raw/tot)*100).toFixed(1)}%)`; } } }
				}
			}
		};
	}
	if (type === 'bar') {
		return {
			type: 'bar',
			data: { labels, datasets: [{ label: vCol, data: values, backgroundColor: bg, borderRadius: 4, borderSkipped: false }] },
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 10 }, maxRotation: 45 } } } }
		};
	}
	if (type === 'line') {
		return {
			type: 'line',
			data: { labels, datasets: [{ label: vCol, data: values, borderColor: _COLORS[0], backgroundColor: _COLORS[0]+'22', pointBackgroundColor: _COLORS[0], pointRadius: 4, tension: 0.35, fill: true }] },
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 10 }, maxRotation: 45 } } } }
		};
	}
	if (type === 'top5') {
		const sorted = labels.map((l,i) => ({l,v:values[i]})).sort((a,b)=>b.v-a.v).slice(0,5);
		return {
			type: 'bar',
			data: { labels: sorted.map(x=>x.l), datasets: [{ label: vCol, data: sorted.map(x=>x.v), backgroundColor: _genColors(5), borderRadius: 4 }] },
			options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } }, y: { ticks: { font: { size: 11 } } } } }
		};
	}
	return null;
}

// ─── Public class ─────────────────────────────────────────────────────────────
export class NexaChart {
	#instance = null;
	#container = null;
	#opts = {};

	constructor(opts = {}) {
		this.#opts = opts;
	}

	// Factory — langsung return instance, render berjalan di background.
	// Ini penting karena caller biasanya: await NXUI.Chart({...}); return `<div id="Chart"></div>`;
	// Container baru ada di DOM SETELAH return, jadi render harus async / fire-and-forget.
	static create(opts = {}) {
		const nc = new NexaChart(opts);
		nc.render().catch(err => console.error('NexaChart.render error:', err));
		return nc;
	}

	async render(overrideOpts = {}) {
		const opts = { ...this.#opts, ...overrideOpts };
		const { key, target, type: typeOverride, title: titleOverride, height = 300, token, appId, appID } = opts;

		if (!key) throw new Error('NexaChart: opsi "key" wajib diisi');

		// Tunggu container ada di DOM (maks 3s)
		const getContainer = () => document.querySelector(target || '#Chart');
		let container;
		try { container = await _waitFor(getContainer, 5000); }
		catch (_) { console.warn(`NexaChart: container "${target}" tidak ditemukan di DOM setelah 5s`); return this; }

		this.#container = container;

		// Loading state
		container.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:${height}px;gap:8px;font-size:12px;color:var(--fgColor-muted);">
			<span class="material-symbols-outlined" style="font-size:20px;animation:spin 1s linear infinite;">refresh</span> Memuat data...
		</div>
		<style>@keyframes spin{to{transform:rotate(360deg)}}</style>`;

		try {
			// 1. Ambil analysis dari IndexedDB
			const analysis = await _fetchAnalysis(key, { token, appId, appID });

			// 2. Jalankan SQL
			const { columns, rows } = await _executeSQL(analysis.sql);
			if (!rows.length) { container.innerHTML = `<p style="font-size:12px;color:var(--fgColor-muted);padding:12px;">Tidak ada data.</p>`; return this; }

			// 3. Tentukan tipe chart
			const chartCfg = analysis.chartCfg || {};
			const type = typeOverride || chartCfg.type || 'bar';
			const title = titleOverride || chartCfg.title || '';

			if (!type) { container.innerHTML = ''; return this; }

			// 4. Load Chart.js
			await _loadChartJS();

			// 5. Deteksi kolom
			const { lCol, vCol } = _autoDetect(columns, rows, chartCfg.labelCol, chartCfg.valCol);
			const maxRows = (type === 'pie' || type === 'donut') ? 12 : type === 'top5' ? 5 : 50;
			const slice = rows.slice(0, maxRows);
			const labels = slice.map(r => String(r[lCol] ?? ''));
			const values = slice.map(r => parseFloat(r[vCol]) || 0);

			// 6. Render
			container.innerHTML = '';
			if (title) {
				const h = document.createElement('div');
				h.style.cssText = 'font-size:12px;font-weight:600;margin-bottom:8px;';
				h.textContent = title;
				container.appendChild(h);
			}
			const wrap = document.createElement('div');
			wrap.style.cssText = `position:relative;width:100%;height:${height}px;`;
			container.appendChild(wrap);

			const canvas = document.createElement('canvas');
			wrap.appendChild(canvas);

			// Destroy previous instance
			if (this.#instance) { this.#instance.destroy(); this.#instance = null; }

			const cfg = _buildChartConfig(type, labels, values, vCol, height);
			if (!cfg) { container.innerHTML = `<p style="font-size:12px;color:var(--fgColor-muted);padding:8px;">Tipe chart "${type}" tidak dikenal.</p>`; return this; }

			this.#instance = new window.Chart(canvas, cfg);

		} catch (err) {
			container.innerHTML = `<div style="font-size:12px;padding:8px;color:var(--fgColor-danger,#cf222e);background:var(--bgColor-danger-muted,#fff0f0);border-radius:4px;">${String(err?.message || err)}</div>`;
			console.error('NexaChart error:', err);
		}
		return this;
	}

	destroy() {
		this.#instance?.destroy();
		this.#instance = null;
		if (this.#container) this.#container.innerHTML = '';
	}
}

// ─── Fungsi factory agar bisa dipanggil tanpa `new` ──────────────────────────
// NXUI.Chart({ key, target, type }) → auto tunggu target di DOM lalu render
const _NexaChartFactory = function(opts = {}) {
	return NexaChart.create(opts);
};
// Salin static methods agar tetap bisa `new NXUI.Chart(...)`
Object.setPrototypeOf(_NexaChartFactory, NexaChart);
_NexaChartFactory.prototype = NexaChart.prototype;
_NexaChartFactory.create = NexaChart.create;

export default _NexaChartFactory;
