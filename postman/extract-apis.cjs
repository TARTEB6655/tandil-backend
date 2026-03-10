const fs = require('fs');
const path = require('path');
const col = JSON.parse(fs.readFileSync(path.join(__dirname, 'tandil_backend.json'), 'utf8'));
const rows = [];
const seen = new Set();
let sno = 0;

function pathToEndpoint(pathArr) {
  if (!pathArr || !Array.isArray(pathArr)) return '';
  return '/' + pathArr.join('/').replace(/\{\{[^}]+\}\}/g, '{id}');
}

function walk(items, dashboard, moduleName) {
  if (!items || !Array.isArray(items)) return;
  for (const it of items) {
    const name = it.name || '';
    if (it.request) {
      const method = (it.request.method || 'GET').toUpperCase();
      let pathArr = it.request.url && it.request.url.path ? it.request.url.path : null;
      if (!pathArr && it.request.url && it.request.url.raw) {
        const raw = it.request.url.raw;
        pathArr = raw.replace(/\{\{base_url\}\}/, '').split('?')[0].split('/').filter(Boolean);
      }
      pathArr = Array.isArray(pathArr) ? pathArr : (typeof pathArr === 'string' ? pathArr.split('/').filter(Boolean) : []);
      const endpoint = pathToEndpoint(pathArr);
      const key = method + ' ' + endpoint;
      if (!endpoint || seen.has(key)) continue;
      seen.add(key);
      sno++;
      const mod = (moduleName || name).replace(/"/g, '""');
      rows.push([sno, mod, method, endpoint, 'Done']);
    } else if (it.item) {
      const nextDashboard = dashboard || name;
      walk(it.item, nextDashboard, name);
    }
  }
}

walk(col.item, '', '');

// Expected HR Dashboard APIs (not yet created) - Pending
const hrDashboard = 'HR Dashboard (Expected)';
const hrApis = [
  [hrDashboard, 'Dashboard Summary', 'GET', '/api/hr/dashboard/summary'],
  [hrDashboard, 'Dashboard Alerts', 'GET', '/api/hr/dashboard/alerts'],
  [hrDashboard, 'Profile - Get', 'GET', '/api/hr/profile'],
  [hrDashboard, 'Profile - Update', 'PUT', '/api/hr/profile'],
  [hrDashboard, 'Profile - Update', 'POST', '/api/hr/profile'],
  [hrDashboard, 'Employees - List', 'GET', '/api/hr/employees'],
  [hrDashboard, 'Employees - Create', 'POST', '/api/hr/employees'],
  [hrDashboard, 'Employees - Get', 'GET', '/api/hr/employees/{id}'],
  [hrDashboard, 'Employees - Update', 'PUT', '/api/hr/employees/{id}'],
  [hrDashboard, 'Employees - Delete', 'DELETE', '/api/hr/employees/{id}'],
  [hrDashboard, 'Leave Requests - List', 'GET', '/api/hr/leave-requests'],
  [hrDashboard, 'Leave Requests - Approve', 'POST', '/api/hr/leave-requests/{id}/approve'],
  [hrDashboard, 'Leave Requests - Reject', 'POST', '/api/hr/leave-requests/{id}/reject'],
  [hrDashboard, 'Analytics', 'GET', '/api/hr/analytics'],
  [hrDashboard, 'Reports - List', 'GET', '/api/hr/reports'],
  [hrDashboard, 'Reports - Generate', 'POST', '/api/hr/reports/generate'],
];
hrApis.forEach(([dash, mod, method, endpoint]) => {
  const key = method.toUpperCase() + ' ' + endpoint;
  if (seen.has(key)) return;
  seen.add(key);
  sno++;
  rows.push([sno, mod, method, endpoint, 'Pending']);
});

// Final dedupe: keep first occurrence of each (Method, Endpoint), re-number SNo
const seenFinal = new Set();
const deduped = [];
let idx = 0;
for (const row of rows) {
  const key = row[2] + ' ' + row[3];
  if (seenFinal.has(key)) continue;
  seenFinal.add(key);
  idx++;
  deduped.push([idx, row[1], row[2], row[3], row[4]]);
}

const header = 'SNo,Module,Method,Endpoint,Status';
const escapeCsv = (arr) => arr.map(c => {
  const s = String(c).trim();
  return (s.indexOf(',') >= 0 || s.indexOf('"') >= 0 ? '"' + s.replace(/"/g, '""') + '"' : s);
}).join(',');
const csv = [header, ...deduped.map(escapeCsv)].join('\r\n');
const outPath = path.join(__dirname, 'api_list.csv');
const outPathFixed = path.join(__dirname, 'api_list_fixed.csv');
try {
  fs.writeFileSync(outPath, csv, 'utf8');
  console.log('Written to postman/api_list.csv');
} catch (e) {
  if (e.code === 'EBUSY' || e.code === 'EPERM') {
    fs.writeFileSync(outPathFixed, csv, 'utf8');
    console.log('Written to postman/api_list_fixed.csv (api_list.csv was locked)');
  } else throw e;
}
const doneCount = deduped.filter(r => r[4] === 'Done').length;
const pendingCount = deduped.filter(r => r[4] === 'Pending').length;
console.log('Total APIs:', deduped.length, '(Done:', doneCount, ', Pending:', pendingCount, '), duplicates removed:', rows.length - deduped.length);
