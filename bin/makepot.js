// Minimal .pot extractor for __(), _e(), esc_html__(), esc_html_e(), esc_attr__() with the plugin text domain.
// Usage: node bin/makepot.js .   (dev tool; not shipped)
const fs = require('fs');
const path = require('path');
const root = process.argv[2] || path.join(__dirname, '..');
const version = (fs.readFileSync(path.join(root, 'mercury-schema.php'), 'utf8').match(/Version:\s*([\d.]+)/) || [])[1] || 'dev';
const files = [];
(function walk(dir) {
  for (const f of fs.readdirSync(dir)) {
    const p = path.join(dir, f);
    if (['vendor', 'tests', '.git', 'build', 'node_modules'].includes(f)) continue;
    if (fs.statSync(p).isDirectory()) walk(p);
    else if (p.endsWith('.php')) files.push(p);
  }
})(root);

const re = /\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'mercury-schema'\s*\)/g;
const entries = new Map();
for (const file of files.sort()) {
  const src = fs.readFileSync(file, 'utf8');
  const lines = src.split('\n');
  lines.forEach((line, i) => {
    let m;
    re.lastIndex = 0;
    while ((m = re.exec(line))) {
      const msg = m[1].replace(/\\'/g, "'");
      const ref = path.relative(root, file).replace(/\\/g, '/') + ':' + (i + 1);
      const prev = lines[i - 1] || '';
      const comment = /translators:/.test(prev) ? prev.trim().replace(/^\/\*\s*|\s*\*\/$/g, '') : null;
      if (!entries.has(msg)) entries.set(msg, { refs: [], comment });
      entries.get(msg).refs.push(ref);
    }
  });
}
const esc = s => s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
let out = `# Copyright (C) 2026 AfridexD
# This file is distributed under the GPLv2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Mercury Schema ${version}\\n"
"Report-Msgid-Bugs-To: https://github.com/AfridexD/UnlimitedSchema/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: mercury-schema\\n"
`;
for (const [msg, e] of entries) {
  out += '\n';
  if (e.comment) out += `#. ${e.comment}\n`;
  out += `#: ${e.refs.join(' ')}\nmsgid "${esc(msg)}"\nmsgstr ""\n`;
}
fs.mkdirSync(path.join(root, 'languages'), { recursive: true });
fs.writeFileSync(path.join(root, 'languages', 'mercury-schema.pot'), out);
console.log(entries.size + ' strings from ' + files.length + ' files');
