// Minimal .pot extractor for __(), _e(), _n(), esc_html__(), esc_html_e(), esc_attr__() with the plugin text domain.
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
// _n( 'singular', 'plural', $count, 'mercury-schema' )
const reN = /\b_n\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'\s*,\s*[^,]+?,\s*'mercury-schema'\s*\)/g;
const entries = new Map();
for (const file of files.sort()) {
  const src = fs.readFileSync(file, 'utf8');
  const lines = src.split('\n');
  lines.forEach((line, i) => {
    const ref = path.relative(root, file).replace(/\\/g, '/') + ':' + (i + 1);
    const prev = lines[i - 1] || '';
    const comment = /translators:/.test(prev) ? prev.trim().replace(/^\/\*\s*|\s*\*\/$/g, '') : null;
    const add = (msg, plural) => {
      if (!entries.has(msg)) entries.set(msg, { refs: [], comment, plural });
      entries.get(msg).refs.push(ref);
    };
    let m;
    re.lastIndex = 0;
    while ((m = re.exec(line))) {
      add(m[1].replace(/\\'/g, "'"), null);
    }
    reN.lastIndex = 0;
    while ((m = reN.exec(line))) {
      add(m[1].replace(/\\'/g, "'"), m[2].replace(/\\'/g, "'"));
    }
  });
}
const esc = s => s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
let out = `# Copyright (C) 2026 AfridexD
# This file is distributed under the GPLv2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Mercury Schema ${version}\\n"
"Report-Msgid-Bugs-To: https://github.com/AfridexD/MercurySchema/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: mercury-schema\\n"
"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n"
`;
for (const [msg, e] of entries) {
  out += '\n';
  if (e.comment) out += `#. ${e.comment}\n`;
  out += `#: ${e.refs.join(' ')}\nmsgid "${esc(msg)}"\n`;
  out += e.plural ? `msgid_plural "${esc(e.plural)}"\nmsgstr[0] ""\nmsgstr[1] ""\n` : 'msgstr ""\n';
}
fs.mkdirSync(path.join(root, 'languages'), { recursive: true });
fs.writeFileSync(path.join(root, 'languages', 'mercury-schema.pot'), out);
console.log(entries.size + ' strings from ' + files.length + ' files');
