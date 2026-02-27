import fs from 'fs';
const col = JSON.parse(fs.readFileSync('postman/tandil_backend.json', 'utf8'));
const out = [];
function walk(items, folder) {
  if (!items) return;
  for (const it of items) {
    const name = it.name || '';
    if (it.request) {
      const method = it.request.method || '';
      const raw = (it.request.url && it.request.url.raw) || '';
      const path = raw.replace(/\{\{base_url\}\}/i, '').trim();
      if (path.startsWith('/api/')) out.push({ folder: folder || 'Root', name, method, path });
    }
    if (it.item) walk(it.item, name || folder);
  }
}
walk(col.item, '');
fs.writeFileSync('postman-apis-extract.json', JSON.stringify(out, null, 2));
