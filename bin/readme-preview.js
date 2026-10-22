// Renders readme.txt + .wordpress-org assets as an approximate WordPress.org plugin page,
// for reviewing the listing before submission. Dev tool; not shipped.
// Usage: node bin/readme-preview.js   ->  build/plugin-page-preview.html
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const readme = fs.readFileSync(path.join(root, 'readme.txt'), 'utf8').replace(/\r\n/g, '\n');
const main = fs.readFileSync(path.join(root, 'mercury-schema.php'), 'utf8');
const author = (main.match(/Author:\s*(.+)/) || [])[1].trim();
const assets = '../.wordpress-org/';

// ---- Parse -------------------------------------------------------------------
const name = readme.match(/^=== (.+) ===/)[1];
const headerBlock = readme.split('\n\n')[0];
const header = {};
headerBlock.split('\n').slice(1).forEach(l => {
  const m = l.match(/^([^:]+):\s*(.*)$/);
  if (m) { header[m[1].trim()] = m[2].trim(); }
});
const afterHeader = readme.slice(headerBlock.length).trim();
const short = afterHeader.split('\n\n')[0].trim();
const sections = {};
afterHeader.split(/^== (.+) ==$/m).slice(1).forEach((v, i, a) => { if (i % 2 === 0) { sections[v.trim()] = a[i + 1].trim(); } });

// ---- Tiny Markdown (the subset WordPress.org readmes use) ----------------------
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inline = s => esc(s)
  .replace(/`([^`]+)`/g, '<code>$1</code>')
  .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
  .replace(/\[([^\]]+)\]\((https?:[^)]+)\)/g, '<a href="$2">$1</a>');
function md(text) {
  const out = [];
  for (const block of text.split(/\n{2,}/)) {
    const lines = block.split('\n');
    if (/^= .+ =$/.test(lines[0])) {
      out.push(`<h3>${inline(lines[0].replace(/^= | =$/g, ''))}</h3>`);
      if (lines.length > 1) { out.push(md(lines.slice(1).join('\n'))); }
    } else if (lines.every(l => /^\* /.test(l))) {
      out.push('<ul>' + lines.map(l => `<li>${inline(l.slice(2))}</li>`).join('') + '</ul>');
    } else if (lines.every(l => /^\d+\. /.test(l))) {
      out.push('<ol>' + lines.map(l => `<li>${inline(l.replace(/^\d+\. /, ''))}</li>`).join('') + '</ol>');
    } else {
      out.push(`<p>${inline(lines.join(' '))}</p>`);
    }
  }
  return out.join('\n');
}
function faq(text) {
  return text.split(/^= (.+) =$/m).slice(1).reduce((acc, v, i, a) => {
    if (i % 2 === 0) { acc.push(`<details class="faq"><summary>${inline(v)}</summary><div>${md(a[i + 1].trim())}</div></details>`); }
    return acc;
  }, []).join('\n');
}
const captions = (sections.Screenshots || '').split('\n').filter(l => /^\d+\. /.test(l)).map(l => l.replace(/^\d+\. /, ''));
const shots = captions.map((c, i) => {
  const file = `screenshot-${i + 1}.png`;
  const exists = fs.existsSync(path.join(root, '.wordpress-org', file));
  return `<figure class="shot"><a href="${assets}${file}">${exists ? `<img src="${assets}${file}" alt="">` : '<span class="missing">missing file</span>'}</a><figcaption><span>${i + 1}.</span> ${inline(c)}</figcaption></figure>`;
}).join('\n');
const tags = (header.Tags || '').split(',').map(t => t.trim()).filter(Boolean);
const shortLen = short.length;

// ---- Page --------------------------------------------------------------------
const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(name)} listing preview</title>
<style>
:root { --ink:#1e1e1e; --muted:#50575e; --line:#dcdcde; --soft:#f6f7f7; --link:#2271b1; --btn:#2271b1; --note:#fcf9e8; }
* { box-sizing: border-box; }
body { margin: 0; background: #fff; color: var(--ink); font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
a { color: var(--link); }
.preview-bar { background: var(--note); border-bottom: 1px solid #f0d48a; padding: 10px 16px; font-size: 14px; text-align: center; color: #5c4200; }
.preview-bar strong { font-weight: 600; }
.wrap { max-width: 960px; margin: 0 auto; padding: 0 16px; }
.banner { margin-top: 24px; aspect-ratio: 772 / 250; border-radius: 4px; overflow: hidden; background: var(--soft); }
.banner img { width: 100%; height: 100%; object-fit: cover; display: block; }
.head { display: flex; align-items: center; gap: 20px; padding: 24px 0; border-bottom: 1px solid var(--line); flex-wrap: wrap; }
.head img { width: 128px; height: 128px; border-radius: 4px; flex: none; }
.head__text { flex: 1; min-width: 240px; }
.head h1 { margin: 0 0 4px; font-size: 32px; line-height: 1.2; font-weight: 400; }
.head .by { color: var(--muted); font-size: 15px; }
.head .by a { text-decoration: none; }
.download { flex: none; background: var(--btn); color: #fff; border: 0; border-radius: 3px; padding: 12px 22px; font-size: 16px; cursor: not-allowed; }
.tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--line); overflow-x: auto; }
.tabs button { background: none; border: 0; border-bottom: 3px solid transparent; padding: 14px 16px 11px; font: inherit; font-size: 15px; color: var(--muted); cursor: pointer; white-space: nowrap; }
.tabs button[aria-selected="true"] { color: var(--ink); border-bottom-color: var(--btn); font-weight: 600; }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 40px; padding: 28px 0 64px; }
.main h2 { font-size: 22px; font-weight: 600; margin: 36px 0 12px; }
.main h2:first-child { margin-top: 0; }
.main h3 { font-size: 17px; font-weight: 600; margin: 26px 0 8px; }
.main p, .main li { color: #2c3338; }
.main ul, .main ol { padding-left: 1.4em; }
code { font: 14px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; background: var(--soft); padding: 1px 5px; border-radius: 3px; }
.short { font-size: 18px; color: var(--muted); margin: 0 0 20px; }
.shots { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.shot { margin: 0; }
.shot img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; object-position: top; border: 1px solid var(--line); border-radius: 3px; display: block; }
.shot figcaption { font-size: 14px; color: var(--muted); margin-top: 6px; line-height: 1.45; }
.shot figcaption span { font-weight: 600; color: var(--ink); }
.missing { display: grid; place-items: center; aspect-ratio: 16/10; border: 2px dashed #d63638; color: #d63638; }
.faq { border: 1px solid var(--line); border-bottom: 0; }
.faq:last-of-type { border-bottom: 1px solid var(--line); }
.faq summary { padding: 12px 16px; cursor: pointer; font-weight: 600; }
.faq > div { padding: 0 16px 8px; }
.side { font-size: 15px; }
.meta { list-style: none; padding: 0; margin: 0 0 24px; border-top: 1px solid var(--line); }
.meta li { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); }
.meta strong { font-weight: 600; text-align: right; }
.tagcloud { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 24px; }
.tagcloud span { background: var(--soft); border: 1px solid var(--line); border-radius: 999px; padding: 2px 10px; font-size: 13px; }
.side h4 { font-size: 15px; margin: 24px 0 8px; font-weight: 600; }
.contrib { display: flex; align-items: center; gap: 10px; }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: #1f4fd6; color: #fff; display: grid; place-items: center; font-weight: 600; font-size: 14px; }
.stars { color: #dba617; letter-spacing: 2px; }
.check { margin-top: 32px; padding: 14px 16px; background: var(--soft); border-radius: 4px; font-size: 14px; color: var(--muted); }
.check b { color: var(--ink); }
.ok { color: #00a32a; } .bad { color: #d63638; }
[hidden] { display: none !important; }
@media (max-width: 760px) { .layout { grid-template-columns: 1fr; } .head img { width: 88px; height: 88px; } .head h1 { font-size: 26px; } }
</style>
</head>
<body>
<div class="preview-bar"><strong>Preview only.</strong> An approximation of your plugin page, built from readme.txt and the .wordpress-org images. Fonts and spacing on the real page will differ slightly.</div>
<div class="wrap">
  <div class="banner"><img src="${assets}banner-1544x500.png" alt="${esc(name)} banner"></div>
  <div class="head">
    <img src="${assets}icon-256x256.png" alt="">
    <div class="head__text">
      <h1>${esc(name)}</h1>
      <div class="by">By <a href="#">${esc(author)}</a></div>
    </div>
    <button class="download" type="button" title="Preview only">Download</button>
  </div>
  <div class="tabs" role="tablist">
    <button role="tab" aria-selected="true" data-tab="details">Details</button>
    <button role="tab" aria-selected="false" data-tab="reviews">Reviews</button>
    <button role="tab" aria-selected="false" data-tab="installation">Installation</button>
    <button role="tab" aria-selected="false" data-tab="development">Development</button>
  </div>
  <div class="layout">
    <main class="main">
      <section data-panel="details">
        <p class="short">${inline(short)}</p>
        <h2>Description</h2>
        ${md(sections.Description || '')}
        <h2>Screenshots</h2>
        <div class="shots">${shots}</div>
        <h2>FAQ</h2>
        ${faq(sections['Frequently Asked Questions'] || '')}
      </section>
      <section data-panel="reviews" hidden>
        <h2>Reviews</h2>
        <p>No reviews yet. Reviews from your users appear here once the plugin is live.</p>
      </section>
      <section data-panel="installation" hidden>
        <h2>Installation</h2>
        ${md(sections.Installation || '')}
      </section>
      <section data-panel="development" hidden>
        <h2>Changelog</h2>
        ${md(sections.Changelog || '')}
        ${sections['Upgrade Notice'] ? '<h2>Upgrade notice</h2>' + md(sections['Upgrade Notice']) : ''}
      </section>
    </main>
    <aside class="side">
      <ul class="meta">
        <li>Version <strong>${esc(header['Stable tag'] || '')}</strong></li>
        <li>Last updated <strong>just now</strong></li>
        <li>Active installations <strong>Fewer than 10</strong></li>
        <li>WordPress version <strong>${esc(header['Requires at least'] || '')} or higher</strong></li>
        <li>Tested up to <strong>${esc(header['Tested up to'] || '')}</strong></li>
        <li>PHP version <strong>${esc(header['Requires PHP'] || '')} or higher</strong></li>
      </ul>
      <div class="tagcloud">${tags.map(t => `<span>${esc(t)}</span>`).join('')}</div>
      <h4>Ratings</h4>
      <div><span class="stars">☆☆☆☆☆</span> No ratings yet</div>
      <h4>Contributors</h4>
      ${(header.Contributors || '').split(',').map(c => c.trim()).filter(Boolean).map(c => `<div class="contrib"><span class="avatar">${esc(c[0].toUpperCase())}</span><a href="https://profiles.wordpress.org/${esc(c)}/">${esc(c)}</a></div>`).join('')}
      <h4>Support</h4>
      <p>Your plugin gets its own support forum here once it's live.</p>
      <div class="check">
        <b>Readme checks</b><br>
        Short description: ${shortLen} / 150 characters <span class="${shortLen <= 150 ? 'ok' : 'bad'}">${shortLen <= 150 ? '✓' : '✗ too long'}</span><br>
        Tags: ${tags.length} (first 5 are shown) <span class="${tags.length <= 5 ? 'ok' : 'bad'}">${tags.length <= 5 ? '✓' : '✗'}</span><br>
        Screenshots: ${captions.length} captions <span class="ok">✓</span>
      </div>
    </aside>
  </div>
</div>
<script>
document.querySelectorAll('.tabs button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.tabs button').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== b.getAttribute('data-tab'); });
  });
});
</script>
</body>
</html>
`;
fs.mkdirSync(path.join(root, 'build'), { recursive: true });
fs.writeFileSync(path.join(root, 'build', 'plugin-page-preview.html'), html);
console.log('build/plugin-page-preview.html written');
