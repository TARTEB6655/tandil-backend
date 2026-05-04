/**
 * Injects "Notifications – All roles (hub)" into tandil_backend.json (after Health Check).
 * Run: node postman/tools/inject-notifications-hub.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const collectionPath = path.join(root, 'tandil_backend.json');

function headersAuthJson() {
    return [
        { key: 'Authorization', value: 'Bearer {{token}}' },
        { key: 'Accept', value: 'application/json' },
    ];
}

function reqGet(name, raw, urlPath, query = null, description = '') {
    const url = {
        raw,
        host: ['{{base_url}}'],
        path: ['api', ...urlPath],
    };
    if (query) {
        url.query = query;
    }
    return {
        name,
        request: {
            method: 'GET',
            header: headersAuthJson(),
            url,
            description,
        },
    };
}

function reqPost(name, raw, urlPath, description = '', query = null) {
    const url = {
        raw,
        host: ['{{base_url}}'],
        path: ['api', ...urlPath],
    };
    if (query) {
        url.query = query;
    }
    return {
        name,
        request: {
            method: 'POST',
            header: headersAuthJson(),
            url,
            description,
        },
    };
}

function reqDelete(name, raw, urlPath, description = '') {
    return {
        name,
        request: {
            method: 'DELETE',
            header: headersAuthJson(),
            url: {
                raw,
                host: ['{{base_url}}'],
                path: ['api', ...urlPath],
            },
            description,
        },
    };
}

function reqPostJson(name, raw, urlPath, bodyRaw, description = '') {
    return {
        name,
        request: {
            method: 'POST',
            header: [
                ...headersAuthJson(),
                { key: 'Content-Type', value: 'application/json' },
            ],
            body: { mode: 'raw', raw: bodyRaw },
            url: {
                raw,
                host: ['{{base_url}}'],
                path: ['api', ...urlPath],
            },
            description,
        },
    };
}

function standardInboxFolder(displayName, _apiPrefix, pathSeg, note) {
    const apiPath = `api/${pathSeg.join('/')}`;
    return {
        name: displayName,
        description: note,
        item: [
            reqGet(
                'List',
                `{{base_url}}/${apiPath}?per_page=20&audience_role=`,
                pathSeg,
                [
                    { key: 'per_page', value: '20', description: '1-100' },
                    { key: 'audience_role', value: '', description: 'Optional' },
                ],
                `GET /${apiPath}. Response: data.notifications, data.unread_count.`,
            ),
            reqPost(
                'Mark as read',
                `{{base_url}}/${apiPath}/{{notification_id}}/mark-read`,
                [...pathSeg, '{{notification_id}}', 'mark-read'],
                `POST /${apiPath}/{uuid}/mark-read`,
            ),
            reqPost(
                'Mark all as read',
                `{{base_url}}/${apiPath}/mark-all-read`,
                [...pathSeg, 'mark-all-read'],
                `POST /${apiPath}/mark-all-read`,
            ),
            reqDelete(
                'Delete one',
                `{{base_url}}/${apiPath}/{{notification_id}}`,
                [...pathSeg, '{{notification_id}}'],
                `DELETE /${apiPath}/{uuid}`,
            ),
            reqPost(
                'Clear all',
                `{{base_url}}/${apiPath}/clear-all`,
                [...pathSeg, 'clear-all'],
                `POST /${apiPath}/clear-all. Response: deleted_count.`,
            ),
        ],
    };
}

const hub = {
    name: 'Notifications – All roles (hub)',
    description:
        'Single place for all notification APIs. Subfolders = role or route family. Auth: Bearer {{token}} with matching role. Optional query audience_role on list/mark-all/clear where supported.',
    item: [
        {
            name: 'Shared – /api/notifications',
            description:
                'Middleware: client | technician | supervisor | area_manager | hr | admin. Same inbox helpers as role dashboards; path has no role prefix.',
            item: [
                reqGet(
                    'List',
                    '{{base_url}}/api/notifications?per_page=20&audience_role=',
                    ['notifications'],
                    [
                        { key: 'per_page', value: '20' },
                        { key: 'audience_role', value: '', description: 'Optional' },
                    ],
                    'GET /api/notifications',
                ),
                reqPost(
                    'Mark as read',
                    '{{base_url}}/api/notifications/{{notification_id}}/mark-read',
                    ['notifications', '{{notification_id}}', 'mark-read'],
                    'POST /api/notifications/{uuid}/mark-read',
                ),
                reqPost(
                    'Mark all as read',
                    '{{base_url}}/api/notifications/mark-all-read',
                    ['notifications', 'mark-all-read'],
                ),
                reqDelete(
                    'Delete one',
                    '{{base_url}}/api/notifications/{{notification_id}}',
                    ['notifications', '{{notification_id}}'],
                ),
                reqPost(
                    'Clear all',
                    '{{base_url}}/api/notifications/clear-all',
                    ['notifications', 'clear-all'],
                    'POST /api/notifications/clear-all',
                ),
            ],
        },
        {
            name: 'User profile – /api/user/notifications',
            description:
                'auth:sanctum any user. Database inbox; same rows as role inbox for that user. Use UUID from list (not numeric tip id).',
            item: [
                reqGet(
                    'List',
                    '{{base_url}}/api/user/notifications?per_page=20&audience_role=',
                    ['user', 'notifications'],
                    [
                        { key: 'per_page', value: '20' },
                        { key: 'audience_role', value: '', description: 'Optional' },
                    ],
                    'GET /api/user/notifications',
                ),
                reqPost(
                    'Mark as read',
                    '{{base_url}}/api/user/notifications/{{notification_id}}/read',
                    ['user', 'notifications', '{{notification_id}}', 'read'],
                    'POST /api/user/notifications/{uuid}/read',
                ),
                reqPost(
                    'Mark all as read',
                    '{{base_url}}/api/user/notifications/read-all?audience_role=',
                    ['user', 'notifications', 'read-all'],
                    'POST /api/user/notifications/read-all',
                    [{ key: 'audience_role', value: '', description: 'Optional' }],
                ),
                reqPost(
                    'Clear all',
                    '{{base_url}}/api/user/notifications/clear-all?audience_role=',
                    ['user', 'notifications', 'clear-all'],
                    'POST /api/user/notifications/clear-all. Response: deleted_count.',
                    [{ key: 'audience_role', value: '', description: 'Optional' }],
                ),
            ],
        },
        standardInboxFolder(
            'Client – /api/client/notifications',
            'client',
            ['client', 'notifications'],
            'role:client',
        ),
        standardInboxFolder(
            'Technician – /api/technician/notifications',
            'technician',
            ['technician', 'notifications'],
            'role:technician',
        ),
        standardInboxFolder(
            'Supervisor – /api/supervisor/notifications',
            'supervisor',
            ['supervisor', 'notifications'],
            'role:supervisor',
        ),
        standardInboxFolder(
            'Area Manager – /api/area-manager/notifications',
            'area_manager',
            ['area-manager', 'notifications'],
            'role:area_manager. Uses AreaManagerNotificationsApiController.',
        ),
        standardInboxFolder(
            'HR – /api/hr/notifications',
            'hr',
            ['hr', 'notifications'],
            'role:hr|admin on /api/hr/*. Uses HrNotificationFilter (subset of types).',
        ),
        {
            name: 'Admin – /api/admin/notifications',
            description:
                'role:admin. Inbox + broadcast + delivery analytics. Register specific paths before generic list.',
            item: [
                reqGet(
                    'Delivery stats',
                    '{{base_url}}/api/admin/notifications/delivery-stats?since=&until=',
                    ['admin', 'notifications', 'delivery-stats'],
                    [
                        { key: 'since', value: '', description: 'Y-m-d optional' },
                        { key: 'until', value: '', description: 'Y-m-d optional' },
                    ],
                    'GET …/delivery-stats → grand_total, by_audience, by_audience_labeled, by_notification_type',
                ),
                reqPostJson(
                    'Broadcast send',
                    '{{base_url}}/api/admin/notifications/broadcast',
                    ['admin', 'notifications', 'broadcast'],
                    '{\n  "title": "System update",\n  "message": "Please read.",\n  "type": "all"\n}',
                    'POST …/broadcast → broadcast_id, recipient_counts',
                ),
                reqGet(
                    'Broadcasts list',
                    '{{base_url}}/api/admin/notifications/broadcasts?per_page=20',
                    ['admin', 'notifications', 'broadcasts'],
                    [{ key: 'per_page', value: '20' }],
                ),
                reqGet(
                    'Broadcast detail',
                    '{{base_url}}/api/admin/notifications/broadcasts/{{broadcast_id}}',
                    ['admin', 'notifications', 'broadcasts', '{{broadcast_id}}'],
                    null,
                    'GET …/broadcasts/{id}',
                ),
                reqGet(
                    'Inbox list',
                    '{{base_url}}/api/admin/notifications?per_page=20&audience_role=',
                    ['admin', 'notifications'],
                    [
                        { key: 'per_page', value: '20' },
                        { key: 'audience_role', value: '', description: 'Optional' },
                    ],
                ),
                reqPost(
                    'Mark as read',
                    '{{base_url}}/api/admin/notifications/{{notification_id}}/mark-read',
                    ['admin', 'notifications', '{{notification_id}}', 'mark-read'],
                ),
                reqPost(
                    'Mark all as read',
                    '{{base_url}}/api/admin/notifications/mark-all-read',
                    ['admin', 'notifications', 'mark-all-read'],
                ),
                reqDelete(
                    'Delete one',
                    '{{base_url}}/api/admin/notifications/{{notification_id}}',
                    ['admin', 'notifications', '{{notification_id}}'],
                ),
                reqPost(
                    'Clear all',
                    '{{base_url}}/api/admin/notifications/clear-all',
                    ['admin', 'notifications', 'clear-all'],
                ),
            ],
        },
    ],
};

const data = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));
const hubName = 'Notifications – All roles (hub)';
const idx = data.item.findIndex((x) => x.name === hubName);
if (idx >= 0) {
    data.item[idx] = hub;
} else {
    data.item.splice(1, 0, hub);
}

const v = data.info.version?.split('.').map(Number) || [3, 3, 6];
v[2] = (v[2] || 0) + 1;
data.info.version = v.join('.');

const desc = data.info.description || '';
const hubHint =
    '\n\n**Notifications hub:** Folder **Notifications - All roles (hub)** lists every notification API by role (Shared, User profile, Client, Technician, Supervisor, Area Manager, HR, Admin).';
if (!desc.includes('Notifications hub:')) {
    data.info.description = desc + hubHint;
}

fs.writeFileSync(collectionPath, JSON.stringify(data, null, 4) + '\n', 'utf8');
console.log('OK: injected', hubName, 'version', data.info.version);
