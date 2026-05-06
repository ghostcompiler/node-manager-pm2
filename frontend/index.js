import { createElement, Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom';
import {
    Button,
    CodeEditor,
    Dialog,
    Drawer,
    FormField,
    Grid,
    GridCol,
    Icon,
    Item,
    List,
    ListAction,
    ListActions,
    Pagination,
    Select,
    SelectOption,
    Status,
    Switch,
    Tab,
    Tabs,
    Toaster,
    Toolbar,
    ToolbarGroup,
} from '@plesk/plesk-ext-sdk';
import PropTypes from 'prop-types';

const DEFAULT_PERMISSIONS = {
    admin: false,
    canInstallPm2: false,
    canManage: false,
    canControl: false,
    canLogs: false,
};

const DEFAULT_CREATE_FORM = {
    name: '',
    scriptPath: '',
    cwd: '.',
    envName: 'production',
    instances: 1,
    autorestart: true,
    maxRestarts: '',
    restartDelay: '',
    gitRepo: '',
    gitBranch: 'main',
};

const DEFAULT_DEPLOY_FORM = {
    npmInstall: true,
    production: true,
    reload: true,
};

const DEFAULT_PICKER = {
    open: false,
    loading: false,
    field: '',
    mode: 'file',
    title: '',
    currentPath: '',
    currentValue: '.',
    parentValue: null,
    rootPath: '',
    homePath: '',
    entries: [],
    error: '',
};

const ITEMS_PER_PAGE_OPTIONS = [10, 25, 100, 'all'];
const METRICS_PER_PAGE_OPTIONS = [5, 10, 25, 100, 'all'];
const DEFAULT_ITEMS_PER_PAGE = 10;

const getProps = element => {
    try {
        return JSON.parse(element.getAttribute('data-nm-ui-props') || '{}');
    } catch {
        return {};
    }
};

const pleskForgeryToken = () => {
    const meta = document.querySelector('meta[name="forgery_protection_token"], meta#forgery_protection_token');
    if (meta && meta.getAttribute('content')) {
        return meta.getAttribute('content');
    }

    const input = document.querySelector('input[name="forgery_protection_token"]');
    return input && input.value ? input.value : '';
};

const withParams = (url, params = {}) => {
    const query = new URLSearchParams();
    Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            query.set(key, params[key]);
        }
    });
    const qs = query.toString();
    return qs ? `${url}${url.indexOf('?') === -1 ? '?' : '&'}${qs}` : url;
};

const request = (url, options = {}) => {
    const nextOptions = {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    };

    return fetch(url, nextOptions).then(response => response.text().then(text => {
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch {
            data = {
                success: false,
                error: {
                    message: `${response.status} ${response.statusText}. The server returned a non-JSON response. Check the Plesk extension log for PHP errors.`,
                },
            };
        }

        if (!response.ok && data.success !== false) {
            data = { success: false, error: { message: `${response.status} ${response.statusText}` } };
        }

        return { data };
    }));
};

const unwrap = response => {
    if (!response.data || !response.data.success) {
        const message = response.data && response.data.error
            ? response.data.error.message
            : 'Request failed.';
        throw new Error(message);
    }
    return response.data.data;
};

const formatBytes = bytes => {
    const value = Number(bytes || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1073741824) return `${(value / 1048576).toFixed(1)} MB`;
    return `${(value / 1073741824).toFixed(1)} GB`;
};

const formatUptime = seconds => {
    const value = Number(seconds || 0);
    const days = Math.floor(value / 86400);
    const hours = Math.floor((value % 86400) / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    if (days) return `${days}d ${hours}h`;
    if (hours) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
};

const runtimeLabel = key => ({
    node: 'Node.js',
    npm: 'npm',
    pm2: 'PM2',
    git: 'Git',
}[key] || key);

const settingLabel = key => ({
    pm2Binary: 'PM2 binary',
    nodeBinary: 'Node.js binary',
    npmBinary: 'npm binary',
    gitBinary: 'Git binary',
    extraPath: 'Extra PATH entries',
    pollInterval: 'Polling interval, ms',
    maxLogBytes: 'Max log bytes',
    metricsRetentionDays: 'Metrics retention, days',
    deploymentTimeout: 'Deployment timeout, seconds',
}[key] || key);

const statusIntent = status => {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'online' || normalized === 'ready') return 'success';
    if (normalized === 'stopped' || normalized === 'waiting restart') return 'warning';
    if (normalized === 'errored' || normalized === 'missing') return 'danger';
    return 'info';
};

const getPageCount = (items, itemsPerPage) => {
    if (!items.length || itemsPerPage === 'all') {
        return 1;
    }

    return Math.max(1, Math.ceil(items.length / itemsPerPage));
};

const getVisibleRows = (items, currentPage, itemsPerPage) => {
    if (itemsPerPage === 'all') {
        return items;
    }

    const offset = (currentPage - 1) * itemsPerPage;
    return items.slice(offset, offset + itemsPerPage);
};

const useListPagination = (items, initialItemsPerPage = DEFAULT_ITEMS_PER_PAGE, pageOptions = ITEMS_PER_PAGE_OPTIONS) => {
    const [currentPage, setCurrentPage] = useState(1);
    const [itemsPerPage, setItemsPerPage] = useState(initialItemsPerPage);
    const totalPages = getPageCount(items, itemsPerPage);

    useEffect(() => {
        if (currentPage > totalPages) {
            setCurrentPage(totalPages);
        }
    }, [currentPage, totalPages]);

    const visibleRows = useMemo(
        () => getVisibleRows(items, currentPage, itemsPerPage),
        [items, currentPage, itemsPerPage],
    );

    const pagination = items.length ? (
        <Pagination
            current={currentPage}
            itemsPerPage={itemsPerPage}
            itemsPerPageOptions={pageOptions}
            onItemsPerPageChange={value => {
                setItemsPerPage(value);
                setCurrentPage(1);
            }}
            onSelect={setCurrentPage}
            total={totalPages}
        />
    ) : null;

    return { pagination, visibleRows };
};

const Feedback = ({ message, intent }) => {
    if (!message) {
        return null;
    }

    return (
        <div className={`nm-ui-message nm-ui-message-${intent}`}>
            {message}
        </div>
    );
};

Feedback.propTypes = {
    intent: PropTypes.string,
    message: PropTypes.string,
};

Feedback.defaultProps = {
    intent: 'info',
    message: '',
};

const AppTabs = ({ active, items, onSelect }) => {
    const visible = items.filter(item => !item.hidden);
    if (!visible.length) {
        return null;
    }

    return (
        <div className="nm-tabs" role="tablist">
            {visible.map(item => (
                <button
                    key={item.key}
                    type="button"
                    className={`nm-tab${item.key === active ? ' nm-tab-active' : ''}`}
                    onClick={() => onSelect(item.key)}
                    role="tab"
                    aria-selected={item.key === active ? 'true' : 'false'}
                >
                    {item.title}
                </button>
            ))}
        </div>
    );
};

AppTabs.propTypes = {
    active: PropTypes.string.isRequired,
    items: PropTypes.arrayOf(PropTypes.shape({
        hidden: PropTypes.bool,
        key: PropTypes.string,
        title: PropTypes.string,
    })).isRequired,
    onSelect: PropTypes.func.isRequired,
};

const EmptyView = ({ title, description, icon }) => (
    <div className="nm-empty-view">
        <Icon name={icon} />
        <div>
            <strong>{title}</strong>
            {description && <span>{description}</span>}
        </div>
    </div>
);

EmptyView.propTypes = {
    description: PropTypes.string,
    icon: PropTypes.string,
    title: PropTypes.string.isRequired,
};

EmptyView.defaultProps = {
    description: '',
    icon: 'info-circle',
};

const SkeletonBlock = ({ className }) => <span className={`nm-skeleton ${className || ''}`} />;

SkeletonBlock.propTypes = {
    className: PropTypes.string,
};

SkeletonBlock.defaultProps = {
    className: '',
};

const WorkspaceSkeleton = () => (
    <div className="nm-skeleton-stack" aria-label="Loading PM2 workspace" aria-busy="true">
        <div className="nm-panel nm-domain-card">
            <div className="nm-domain-grid">
                <div className="nm-field">
                    <SkeletonBlock className="nm-skeleton-label" />
                    <SkeletonBlock className="nm-skeleton-input" />
                </div>
                <div className="nm-field">
                    <SkeletonBlock className="nm-skeleton-label" />
                    <SkeletonBlock className="nm-skeleton-text" />
                </div>
                <div className="nm-field nm-field-root">
                    <SkeletonBlock className="nm-skeleton-label" />
                    <SkeletonBlock className="nm-skeleton-path" />
                </div>
                <div className="nm-field">
                    <SkeletonBlock className="nm-skeleton-label" />
                    <SkeletonBlock className="nm-skeleton-short" />
                </div>
            </div>
        </div>
        <div className="nm-skeleton-tabs">
            <SkeletonBlock className="nm-skeleton-tab" />
            <SkeletonBlock className="nm-skeleton-tab" />
            <SkeletonBlock className="nm-skeleton-tab" />
            <SkeletonBlock className="nm-skeleton-tab" />
        </div>
        <div className="nm-stat-grid">
            {[0, 1, 2, 3, 4].map(item => (
                <div className="nm-stat-card" key={item}>
                    <SkeletonBlock className="nm-skeleton-label" />
                    <SkeletonBlock className="nm-skeleton-number" />
                </div>
            ))}
        </div>
        <div className="nm-panel nm-skeleton-table">
            {[0, 1, 2, 3, 4].map(row => (
                <div className="nm-skeleton-row" key={row}>
                    <SkeletonBlock className="nm-skeleton-cell-wide" />
                    <SkeletonBlock className="nm-skeleton-cell" />
                    <SkeletonBlock className="nm-skeleton-cell" />
                    <SkeletonBlock className="nm-skeleton-cell" />
                </div>
            ))}
        </div>
    </div>
);

const MetricChart = ({ metrics }) => {
    const points = metrics.slice(-48);
    const maxMemory = points.reduce((max, point) => Math.max(max, Number(point.memory || 0)), 1);

    const polyline = (field, max) => {
        if (!points.length) {
            return '';
        }
        if (points.length === 1) {
            const singleValue = Math.max(0, Math.min(Number(points[0][field] || 0), max || 1));
            const singleY = 164 - (singleValue / Math.max(max || 1, 1)) * 140;
            return `0,${singleY.toFixed(1)} 600,${singleY.toFixed(1)}`;
        }

        const limit = Math.max(max || 1, 1);
        return points.map((point, index) => {
            const x = (index / (points.length - 1)) * 600;
            const value = Math.max(0, Math.min(Number(point[field] || 0), limit));
            const y = 164 - (value / limit) * 140;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');
    };

    return (
        <div className="nm-panel nm-view-card">
            <div className="nm-panel-header">
                <div>
                    <h3>CPU and memory</h3>
                    <p className="nm-muted">{points.length} sample{points.length === 1 ? '' : 's'}</p>
                </div>
                <div className="nm-metric-legend">
                    <span><i className="nm-legend-cpu" /> CPU</span>
                    <span><i className="nm-legend-memory" /> Memory</span>
                </div>
            </div>
            <svg className="nm-metric-chart" viewBox="0 0 600 180" preserveAspectRatio="none" aria-hidden="true">
                {[24, 59, 94, 129, 164].map(line => (
                    <line key={line} x1="0" y1={line} x2="600" y2={line} className="grid" />
                ))}
                {points.length > 0 && <polyline points={polyline('memory', maxMemory)} className="memory" />}
                {points.length > 0 && <polyline points={polyline('cpu', 100)} className="cpu" />}
            </svg>
            {!points.length && <p className="nm-muted">No metrics collected yet.</p>}
        </div>
    );
};

MetricChart.propTypes = {
    metrics: PropTypes.arrayOf(PropTypes.object).isRequired,
};

const MetricsPanel = ({ metrics }) => {
    const { pagination, visibleRows } = useListPagination(metrics.slice().reverse(), 5, METRICS_PER_PAGE_OPTIONS);
    const columns = [
        { key: 'collected_at', title: 'Time', width: '44%' },
        {
            key: 'status',
            title: 'Status',
            width: '20%',
            render: row => (
                <Status intent={statusIntent(row.status)} compact>
                    {row.status || 'unknown'}
                </Status>
            ),
        },
        { key: 'cpu', title: 'CPU', width: '14%', render: row => `${row.cpu || 0}%` },
        { key: 'memory', title: 'Memory', width: '22%', render: row => formatBytes(row.memory) },
    ];

    return (
        <div className="nm-detail-stack">
            <MetricChart metrics={metrics} />
            <List
                columns={columns}
                data={visibleRows}
                emptyView={<EmptyView title="No metrics found" description="Metrics are collected while process status is refreshed." icon="graph" />}
                emptyViewMode="items"
                pagination={pagination}
                rowKey="id"
                totalRows={metrics.length}
            />
        </div>
    );
};

MetricsPanel.propTypes = {
    metrics: PropTypes.arrayOf(PropTypes.object).isRequired,
};

const NativeInput = props => <input className="nm-native-input" {...props} />;

const NodeManagerApp = ({ config }) => {
    const endpoints = config.endpoints || {};
    const toasterRef = useRef(null);
    const pollTimer = useRef(null);
    const logTimer = useRef(null);
    const alertsToastRef = useRef('');
    const runtimeActionRef = useRef('');
    const processActionRef = useRef({});

    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [runtimeAction, setRuntimeAction] = useState('');
    const [processActions, setProcessActions] = useState({});
    const [accessEnabled, setAccessEnabled] = useState(true);
    const [accessMessage, setAccessMessage] = useState('');
    const [domains, setDomains] = useState([]);
    const [selectedDomainId, setSelectedDomainId] = useState(config.initialDomainId || null);
    const [domainLocked, setDomainLocked] = useState(Boolean(config.domainLocked));
    const [permissions, setPermissions] = useState(DEFAULT_PERMISSIONS);
    const [settings, setSettings] = useState({});
    const [runtime, setRuntime] = useState(null);
    const [alerts, setAlerts] = useState([]);
    const [processes, setProcesses] = useState([]);
    const [activeView, setActiveView] = useState('processes');
    const [showCreate, setShowCreate] = useState(false);
    const [createForm, setCreateForm] = useState(DEFAULT_CREATE_FORM);
    const [picker, setPicker] = useState(DEFAULT_PICKER);
    const [selectedProcess, setSelectedProcess] = useState(null);
    const [activeDetail, setActiveDetail] = useState('logs');
    const [logStream, setLogStream] = useState('stdout');
    const [logBytes] = useState(120000);
    const [logContent, setLogContent] = useState('');
    const [logMeta, setLogMeta] = useState(null);
    const [metrics, setMetrics] = useState([]);
    const [envRows, setEnvRows] = useState([]);
    const [newEnv, setNewEnv] = useState({ name: '', value: '', isSecret: false });
    const [ecosystem, setEcosystem] = useState({ path: '', content: '' });
    const [deployForm, setDeployForm] = useState(DEFAULT_DEPLOY_FORM);
    const [webhook, setWebhook] = useState({ enabled: false, url: '', token: '' });
    const [backups, setBackups] = useState([]);
    const [fileEditor, setFileEditor] = useState({
        open: false,
        path: '',
        value: '',
        content: '',
        originalContent: '',
        loading: false,
        saving: false,
        error: '',
    });

    const selectedDomain = useMemo(() => {
        const id = String(selectedDomainId || '');
        return domains.find(domain => String(domain.id) === id) || null;
    }, [domains, selectedDomainId]);

    const runtimeReady = Boolean(runtime && runtime.ready);
    const nodeReady = Boolean(runtime && runtime.nodeReady);
    const pm2Ready = Boolean(runtime && runtime.pm2Ready);
    const setupNeeded = Boolean(runtime && !runtimeReady);
    const selectedDomainPermissions = selectedDomain && selectedDomain.permissions ? selectedDomain.permissions : {};
    const canManage = Boolean(permissions.admin || permissions.canManage || selectedDomainPermissions.manage);
    const canControl = Boolean(permissions.admin || permissions.canControl || selectedDomainPermissions.control);
    const canLogs = Boolean(permissions.admin || permissions.canLogs || selectedDomainPermissions.logs);
    const selectedRootPath = selectedDomain ? (selectedDomain.documentRoot || selectedDomain.homePath || '') : '';
    const selectedAppId = selectedProcess && selectedProcess.appId ? selectedProcess.appId : '';

    const totals = useMemo(() => {
        let online = 0;
        let memory = 0;
        let cpu = 0;
        let restarts = 0;
        processes.forEach(process => {
            if (process.status === 'online') online += 1;
            memory += Number(process.memory || 0);
            cpu += Number(process.cpu || 0);
            restarts += Number(process.restarts || 0);
        });

        return {
            total: processes.length,
            online,
            memory,
            cpu: Math.round(cpu * 100) / 100,
            restarts,
        };
    }, [processes]);

    const processPagination = useListPagination(processes, 10);

    function clearFeedback() {
    }

    function notify(intent, message) {
        if (toasterRef.current && typeof toasterRef.current.add === 'function') {
            toasterRef.current.add({ intent, message });
        }
    }

    function fail(errorObject) {
        notify('danger', errorObject && errorObject.message ? errorObject.message : 'Request failed.');
    }

    function post(url, data = {}) {
        const token = pleskForgeryToken();
        const payload = token && !data.forgery_protection_token
            ? { ...data, forgery_protection_token: token }
            : data;
        const headers = {
            'Content-Type': 'application/json',
            'X-Node-Manager-CSRF': config.csrf || '',
        };

        if (token) {
            headers['X-Forgery-Protection-Token'] = token;
        }

        return request(url, { method: 'POST', headers, body: JSON.stringify(payload) });
    }

    function get(url) {
        return request(url, { method: 'GET' });
    }

    function bootstrap() {
        setLoading(true);
        return get(withParams(endpoints.bootstrap, {
            domainId: config.initialDomainId || '',
            contextDomainId: config.domainContextId || '',
        }))
            .then(unwrap)
            .then(data => {
                const nextDomains = data.domains || [];
                const enabled = data.accessEnabled !== false;
                const nextRuntime = data.runtime || null;
                setDomains(nextDomains);
                setAccessEnabled(enabled);
                setAccessMessage(data.accessMessage || '');
                setSelectedDomainId(data.selectedDomainId);
                setDomainLocked(Boolean(data.domainLocked || config.domainLocked));
                setPermissions(data.permissions || DEFAULT_PERMISSIONS);
                setSettings(data.settings || {});
                setRuntime(nextRuntime);
                setAlerts(data.alerts || []);

                if (!enabled) {
                    setProcesses([]);
                    setSelectedProcess(null);
                    setActiveView('disabled');
                    return null;
                }

                if (nextRuntime && nextRuntime.ready) {
                    setActiveView('processes');
                    return refreshProcesses(true, data.selectedDomainId);
                }

                setProcesses([]);
                setSelectedProcess(null);
                setActiveView('runtime');
                return null;
            })
            .catch(fail)
            .finally(() => setLoading(false));
    }

    function runtimeInfo(domainId = selectedDomainId) {
        if (!domainId) {
            return Promise.resolve(null);
        }

        return post(endpoints.runtime, { domainId })
            .then(unwrap)
            .then(data => {
                setRuntime(data.runtime || null);
                if (!data.runtime || !data.runtime.ready) {
                    setActiveView('runtime');
                }
                return data.runtime || null;
            })
            .catch(fail);
    }

    function refreshProcesses(silent = false, domainId = selectedDomainId) {
        if (!domainId) {
            return Promise.resolve(null);
        }
        if (!silent) {
            setBusy(true);
        }

        return get(withParams(endpoints.processes, { domainId }))
            .then(unwrap)
            .then(data => {
                const rows = data.processes || [];
                setProcesses(rows);
                setSelectedProcess(current => {
                    if (!current) {
                        return current;
                    }
                    return rows.find(process => process.pm2Name === current.pm2Name) || null;
                });
                return rows;
            })
            .catch(fail)
            .finally(() => {
                if (!silent) {
                    setBusy(false);
                }
            });
    }

    function refreshAll() {
        clearFeedback();
        if (!accessEnabled) {
            return bootstrap();
        }

        setBusy(true);
        return runtimeInfo()
            .then(nextRuntime => {
                if (nextRuntime && nextRuntime.ready) {
                    return refreshProcesses(true);
                }
                return null;
            })
            .finally(() => setBusy(false));
    }

    function selectDomain(domainId) {
        if (domainLocked) {
            setSelectedDomainId(config.domainContextId || selectedDomainId);
            return;
        }

        clearFeedback();
        setShowCreate(false);
        setSelectedDomainId(domainId);
        setSelectedProcess(null);
        setLogContent('');
        runtimeInfo(domainId).then(nextRuntime => {
            if (nextRuntime && nextRuntime.ready) {
                setActiveView('processes');
                refreshProcesses(false, domainId);
            } else {
                setActiveView('runtime');
                setProcesses([]);
            }
        });
    }

    function applyDetectedPaths() {
        if (runtimeActionRef.current) {
            return Promise.resolve(null);
        }
        runtimeActionRef.current = 'paths';
        setRuntimeAction('paths');
        setBusy(true);
        clearFeedback();
        return post(endpoints.runtime, { domainId: selectedDomainId, applyDetectedPaths: true })
            .then(unwrap)
            .then(data => {
                setRuntime(data.runtime || null);
                setSettings(data.settings || settings);
                notify('success', 'Detected runtime paths applied.');
                if (data.runtime && data.runtime.ready) {
                    return refreshProcesses(true);
                }
                return null;
            })
            .catch(fail)
            .finally(() => {
                runtimeActionRef.current = '';
                setRuntimeAction('');
                setBusy(false);
            });
    }

    function installPm2() {
        if (runtimeActionRef.current) {
            return Promise.resolve(null);
        }
        runtimeActionRef.current = 'pm2';
        setRuntimeAction('pm2');
        setBusy(true);
        clearFeedback();
        return post(endpoints.runtime, { domainId: selectedDomainId, installPm2: true })
            .then(unwrap)
            .then(data => {
                setRuntime(data.runtime || null);
                notify('success', 'PM2 install/update completed.');
                if (data.runtime && data.runtime.ready) {
                    setActiveView('processes');
                    return refreshProcesses(true);
                }
                return null;
            })
            .catch(fail)
            .finally(() => {
                runtimeActionRef.current = '';
                setRuntimeAction('');
                setBusy(false);
            });
    }

    function setView(view) {
        clearFeedback();
        setShowCreate(false);
        if (!runtimeReady && view !== 'runtime') {
            setActiveView('runtime');
            fail(new Error('Install PM2 before opening this section.'));
            return;
        }
        setActiveView(view);
    }

    function processAction(process, action) {
        if (action === 'delete' && !canManage) {
            fail(new Error('You do not have permission to delete PM2 applications for this domain.'));
            return Promise.resolve();
        }
        if (action !== 'delete' && !canControl) {
            fail(new Error('You do not have permission to control PM2 processes for this domain.'));
            return Promise.resolve();
        }

        const actionKey = `${process.pm2Name}:${action}`;
        if (processActionRef.current[actionKey]) {
            return Promise.resolve();
        }
        processActionRef.current[actionKey] = true;
        setProcessActions(current => ({ ...current, [actionKey]: true }));
        setBusy(true);
        return post(endpoints.process, {
            domainId: selectedDomainId,
            pm2Name: process.pm2Name,
            action,
        })
            .then(unwrap)
            .then(() => {
                notify('success', 'Action completed.');
                return refreshProcesses(true);
            })
            .catch(fail)
            .finally(() => {
                delete processActionRef.current[actionKey];
                setProcessActions(current => {
                    const next = { ...current };
                    delete next[actionKey];
                    return next;
                });
                setBusy(false);
            });
    }

    function scaleProcess(process, delta) {
        if (!canControl) {
            fail(new Error('You do not have permission to scale PM2 processes for this domain.'));
            return Promise.resolve();
        }

        const target = Math.max(1, Number(process.instances || 1) + delta);
        setBusy(true);
        return post(endpoints.process, {
            domainId: selectedDomainId,
            pm2Name: process.pm2Name,
            action: 'scale',
            instances: target,
        })
            .then(unwrap)
            .then(() => {
                notify('success', 'Scale updated.');
                return refreshProcesses(true);
            })
            .catch(fail)
            .finally(() => setBusy(false));
    }

    function openProcess(process, detail = null) {
        clearFeedback();
        setSelectedProcess(process);
        changeDetail(detail || (canLogs ? 'logs' : 'metrics'), process);
    }

    function closeProcess() {
        if (logTimer.current) {
            clearInterval(logTimer.current);
        }
        logTimer.current = null;
        setSelectedProcess(null);
        setLogContent('');
    }

    function changeDetail(detail, process = selectedProcess) {
        if (!process) {
            return;
        }
        if ((detail === 'env' || detail === 'deploy' || detail === 'ecosystem') && !process.appId) {
            fail(new Error('This PM2 process was not created or imported by Node Manager for this domain yet. Delete and recreate it from this domain to manage environment, deployment, and ecosystem settings.'));
            return;
        }
        clearFeedback();
        setActiveDetail(detail);
    }

    function loadLogs(silent = false) {
        if (!selectedProcess || !canLogs) {
            return Promise.resolve(null);
        }

        return get(withParams(endpoints.logs, {
            domainId: selectedDomainId,
            pm2Name: selectedProcess.pm2Name,
            stream: logStream,
            bytes: logBytes,
        }))
            .then(unwrap)
            .then(data => {
                setLogContent(data.content || '');
                setLogMeta(data);
                if (!silent) {
                    window.setTimeout(() => {
                        const terminal = document.querySelector('.nm-terminal');
                        if (terminal) terminal.scrollTop = terminal.scrollHeight;
                    }, 0);
                }
                return data;
            })
            .catch(fail);
    }

    function clearLogs() {
        if (!selectedProcess) {
            return;
        }
        if (!canManage) {
            fail(new Error('You do not have permission to clear PM2 logs for this domain.'));
            return;
        }

        post(endpoints.clearLogs, {
            domainId: selectedDomainId,
            pm2Name: selectedProcess.pm2Name,
            stream: logStream,
        })
            .then(unwrap)
            .then(() => {
                setLogContent('');
                setLogMeta(current => current ? { ...current, content: '', size: 0, truncated: false } : { size: 0, truncated: false });
                notify('success', 'Logs cleared.');
                return loadLogs(true);
            })
            .catch(fail);
    }

    function downloadLogsUrl() {
        if (!selectedProcess || !canLogs) {
            return '#';
        }

        return withParams(endpoints.downloadLogs, {
            domainId: selectedDomainId,
            pm2Name: selectedProcess.pm2Name,
            stream: logStream,
        });
    }

    function loadMetrics() {
        if (!selectedProcess) {
            return Promise.resolve(null);
        }

        return get(withParams(endpoints.metrics, {
            domainId: selectedDomainId,
            pm2Name: selectedProcess.pm2Name,
        }))
            .then(unwrap)
            .then(data => {
                setMetrics(data.metrics || []);
                return data.metrics || [];
            })
            .catch(() => null);
    }

    function loadEnv() {
        if (!selectedAppId || !canManage) {
            return Promise.resolve(null);
        }

        return get(withParams(endpoints.env, {
            domainId: selectedDomainId,
            appId: selectedAppId,
        }))
            .then(unwrap)
            .then(data => {
                setEnvRows(data.env || []);
                return data.env || [];
            })
            .catch(fail);
    }

    function saveEnv() {
        if (!selectedAppId) {
            return;
        }
        if (!canManage) {
            fail(new Error('You do not have permission to edit environment variables for this domain.'));
            return;
        }

        post(endpoints.env, {
            domainId: selectedDomainId,
            appId: selectedAppId,
            name: newEnv.name,
            value: newEnv.value,
            isSecret: newEnv.isSecret,
        })
            .then(unwrap)
            .then(data => {
                setEnvRows(data.env || []);
                setNewEnv({ name: '', value: '', isSecret: false });
                notify('success', 'Environment updated.');
            })
            .catch(fail);
    }

    function deleteEnv(row) {
        if (!canManage) {
            fail(new Error('You do not have permission to edit environment variables for this domain.'));
            return;
        }

        post(endpoints.env, {
            domainId: selectedDomainId,
            appId: selectedAppId,
            name: row.name,
            delete: true,
        })
            .then(unwrap)
            .then(data => {
                setEnvRows(data.env || []);
                notify('success', 'Environment updated.');
            })
            .catch(fail);
    }

    function loadEcosystem() {
        if (!selectedAppId || !canManage) {
            return Promise.resolve(null);
        }

        return get(withParams(endpoints.ecosystem, {
            domainId: selectedDomainId,
            appId: selectedAppId,
        }))
            .then(unwrap)
            .then(data => {
                setEcosystem(data || { path: '', content: '' });
                return data;
            })
            .catch(fail);
    }

    function saveEcosystem(start) {
        if (!canManage) {
            fail(new Error('You do not have permission to edit ecosystem configs for this domain.'));
            return;
        }

        post(endpoints.ecosystem, {
            domainId: selectedDomainId,
            appId: selectedAppId,
            content: ecosystem.content,
            start: Boolean(start),
        })
            .then(unwrap)
            .then(data => {
                setEcosystem(data || ecosystem);
                notify('success', start ? 'Ecosystem started.' : 'Ecosystem saved.');
                return refreshProcesses(true);
            })
            .catch(fail);
    }

    function deploy() {
        if (!canManage) {
            fail(new Error('You do not have permission to deploy PM2 applications for this domain.'));
            return;
        }
        if (!selectedAppId) {
            return;
        }

        setBusy(true);
        post(endpoints.deploy, {
            ...deployForm,
            domainId: selectedDomainId,
            appId: selectedAppId,
        })
            .then(unwrap)
            .then(data => {
                notify('success', data.message || 'Deployment completed.');
                return refreshProcesses(true);
            })
            .catch(fail)
            .finally(() => setBusy(false));
    }

    function toggleWebhook(enabled) {
        if (!canManage) {
            fail(new Error('You do not have permission to manage webhooks for this domain.'));
            return;
        }

        post(endpoints.webhook, {
            domainId: selectedDomainId,
            appId: selectedAppId,
            enabled,
        })
            .then(unwrap)
            .then(data => {
                setWebhook({
                    enabled: Boolean(data.enabled),
                    url: data.url || '',
                    token: data.token || '',
                });
                notify('success', 'Webhook updated.');
            })
            .catch(fail);
    }

    function createProcess() {
        clearFeedback();
        if (busy) {
            return;
        }
        if (!runtimeReady) {
            setActiveView('runtime');
            fail(new Error('Complete runtime setup before creating a PM2 process.'));
            return;
        }
        if (!canManage) {
            fail(new Error('You do not have permission to create PM2 applications for this domain.'));
            return;
        }

        setBusy(true);
        post(endpoints.createProcess, { ...createForm, domainId: selectedDomainId })
            .then(unwrap)
            .then(() => {
                notify('success', 'Process created.');
                setCreateForm(DEFAULT_CREATE_FORM);
                setShowCreate(false);
                setActiveView('processes');
                return refreshProcesses(true);
            })
            .catch(fail)
            .finally(() => setBusy(false));
    }

    function saveSettings() {
        post(endpoints.settings, settings)
            .then(unwrap)
            .then(data => {
                setSettings(data.settings || settings);
                notify('success', 'Settings saved.');
                return runtimeInfo();
            })
            .catch(fail);
    }

    function listBackups() {
        return post(endpoints.backup, {})
            .then(unwrap)
            .then(data => {
                setBackups(data.backups || []);
                return data.backups || [];
            })
            .catch(fail);
    }

    function createBackup() {
        post(endpoints.backup, { create: true })
            .then(unwrap)
            .then(() => {
                notify('success', 'Backup created.');
                return listBackups();
            })
            .catch(fail);
    }

    function restoreBackup(backup) {
        post(endpoints.backup, { restore: backup.name })
            .then(unwrap)
            .then(() => {
                notify('success', 'Backup restored.');
                return bootstrap();
            })
            .catch(fail);
    }

    function updateCreateField(field, value) {
        setCreateForm(current => ({ ...current, [field]: value }));
    }

    function pickerStartPath(field, mode) {
        const value = createForm[field] || '';
        if (!value) return '.';
        if (mode === 'directory') return value;
        const slash = value.lastIndexOf('/');
        if (slash === -1) return '.';
        if (slash === 0) return '/';
        return value.slice(0, slash);
    }

    function openPicker(field, mode) {
        if (!canManage) {
            fail(new Error('You do not have permission to browse files for this domain.'));
            return;
        }

        const title = mode === 'directory' ? 'Select working directory' : 'Select script file';
        setPicker({
            ...DEFAULT_PICKER,
            open: true,
            field,
            mode,
            title,
        });
        browsePath(pickerStartPath(field, mode), mode);
    }

    function closePicker() {
        setPicker(DEFAULT_PICKER);
    }

    function browsePath(path, mode = picker.mode) {
        setPicker(current => ({ ...current, loading: true, error: '' }));
        return get(withParams(endpoints.browse, {
            domainId: selectedDomainId,
            mode,
            path: path || '.',
        }))
            .then(unwrap)
            .then(data => {
                setPicker(current => ({
                    ...current,
                    currentPath: data.currentPath || '',
                    currentValue: data.currentValue || '.',
                    parentValue: data.parentValue || null,
                    rootPath: data.rootPath || '',
                    homePath: data.homePath || '',
                    entries: data.entries || [],
                    loading: false,
                }));
            })
            .catch(errorObject => {
                setPicker(current => ({
                    ...current,
                    error: errorObject && errorObject.message ? errorObject.message : 'Unable to browse files.',
                    loading: false,
                }));
            });
    }

    function choosePickerEntry(entry) {
        if (entry.type === 'directory') {
            browsePath(entry.value);
            return;
        }
        if (!entry.selectable) {
            return;
        }
        updateCreateField(picker.field, entry.value);
        closePicker();
    }

    function chooseCurrentDirectory() {
        if (picker.mode !== 'directory') {
            return;
        }
        updateCreateField(picker.field, picker.currentValue || '.');
        closePicker();
    }

    function openFileEditor(entry) {
        if (!entry || entry.type !== 'file') {
            return;
        }
        setFileEditor({
            open: true,
            path: entry.value,
            value: entry.value,
            content: '',
            originalContent: '',
            loading: true,
            saving: false,
            error: '',
        });
        closePicker();
        post(endpoints.file, { domainId: selectedDomainId, path: entry.value })
            .then(unwrap)
            .then(data => {
                setFileEditor(current => ({
                    ...current,
                    path: data.path || current.path,
                    value: data.value || entry.value,
                    content: data.content || '',
                    originalContent: data.content || '',
                    loading: false,
                }));
            })
            .catch(errorObject => {
                setFileEditor(current => ({
                    ...current,
                    error: errorObject && errorObject.message ? errorObject.message : 'Unable to open file.',
                    loading: false,
                }));
            });
    }

    function closeFileEditor() {
        setFileEditor({
            open: false,
            path: '',
            value: '',
            content: '',
            originalContent: '',
            loading: false,
            saving: false,
            error: '',
        });
    }

    function saveFileEditor(content) {
        setFileEditor(current => ({ ...current, saving: true, error: '' }));
        post(endpoints.file, {
            domainId: selectedDomainId,
            path: fileEditor.value,
            content,
        })
            .then(unwrap)
            .then(data => {
                setFileEditor(current => ({
                    ...current,
                    path: data.path || current.path,
                    value: data.value || current.value,
                    content: data.content || content,
                    saving: false,
                }));
                notify('success', 'File saved.');
                closeFileEditor();
            })
            .catch(errorObject => {
                setFileEditor(current => ({
                    ...current,
                    error: errorObject && errorObject.message ? errorObject.message : 'Unable to save file.',
                    saving: false,
                }));
            });
    }

    useEffect(() => {
        bootstrap();
        return () => {
            if (pollTimer.current) clearInterval(pollTimer.current);
            if (logTimer.current) clearInterval(logTimer.current);
        };
    }, []);

    useEffect(() => {
        if (!alerts.length) {
            alertsToastRef.current = '';
            return;
        }

        const message = `${alerts.length} alert${alerts.length === 1 ? '' : 's'}: ${alerts.slice(0, 3).map(alert => alert.message).join(' ')}`;
        if (alertsToastRef.current !== message) {
            alertsToastRef.current = message;
            notify('warning', message);
        }
    }, [alerts]);

    useEffect(() => {
        if (pollTimer.current) {
            clearInterval(pollTimer.current);
            pollTimer.current = null;
        }
        if (!accessEnabled || !runtimeReady || activeView !== 'processes') {
            return undefined;
        }
        const interval = Math.max(3000, Number(settings.pollInterval || 5000));
        pollTimer.current = setInterval(() => refreshProcesses(true), interval);
        return () => {
            if (pollTimer.current) clearInterval(pollTimer.current);
            pollTimer.current = null;
        };
    }, [accessEnabled, runtimeReady, activeView, selectedDomainId, settings.pollInterval]);

    useEffect(() => {
        if (activeView === 'backups' && runtimeReady && permissions.admin) {
            listBackups();
        }
    }, [activeView, runtimeReady, permissions.admin]);

    useEffect(() => {
        if (logTimer.current) {
            clearInterval(logTimer.current);
            logTimer.current = null;
        }

        if (!selectedProcess) {
            return undefined;
        }

        if (activeDetail === 'logs') {
            loadLogs();
            logTimer.current = setInterval(() => loadLogs(true), 2500);
        }
        if (activeDetail === 'metrics') loadMetrics();
        if (activeDetail === 'env') loadEnv();
        if (activeDetail === 'ecosystem') loadEcosystem();
        if (!selectedProcess.appId) {
            setEnvRows([]);
            setEcosystem({ path: '', content: '' });
        }

        return () => {
            if (logTimer.current) clearInterval(logTimer.current);
            logTimer.current = null;
        };
    }, [selectedProcess ? selectedProcess.pm2Name : '', activeDetail, logStream]);

    const runtimeRows = ['node', 'npm', 'pm2', 'git'].map(key => ({
        key,
        ...(runtime && runtime.items && runtime.items[key] ? runtime.items[key] : {}),
    }));

    const viewTabs = accessEnabled ? [
        { key: 'processes', title: 'Processes', hidden: !runtimeReady },
        { key: 'runtime', title: 'Runtime' },
        { key: 'settings', title: 'Settings', hidden: !runtimeReady || !permissions.admin },
        { key: 'backups', title: 'Backups', hidden: !runtimeReady || !permissions.admin },
    ] : [];

    const processColumns = [
        {
            key: 'name',
            title: 'Name',
            type: 'title',
            width: '30%',
            render: row => (
                <span>
                    <strong>{row.name || row.pm2Name}</strong>
                    <small className="nm-muted nm-path">{row.scriptPath || '-'}</small>
                </span>
            ),
        },
        {
            key: 'status',
            title: 'Status',
            width: 130,
            render: row => (
                <Status intent={statusIntent(row.status)} compact>
                    {row.status || 'unknown'}
                </Status>
            ),
        },
        { key: 'execMode', title: 'Mode', width: 120 },
        { key: 'cpu', title: 'CPU', width: 90, render: row => `${row.cpu || 0}%` },
        { key: 'memory', title: 'Memory', width: 110, render: row => formatBytes(row.memory) },
        { key: 'uptime', title: 'Uptime', width: 100, render: row => formatUptime(row.uptime) },
        { key: 'restarts', title: 'Restarts', width: 100 },
        {
            key: 'instances',
            title: 'Instances',
            type: 'controls',
            width: 150,
            render: row => (
                <div className="nm-inline-actions" onClick={event => event.stopPropagation()}>
                    {canControl && (
                        <Button icon="minus" tooltip="Decrease instances" tooltipAsLabel onClick={() => scaleProcess(row, -1)} />
                    )}
                    <strong>{row.instances || 1}</strong>
                    {canControl && (
                        <Button icon="plus" tooltip="Increase instances" tooltipAsLabel onClick={() => scaleProcess(row, 1)} />
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            title: 'Actions',
            type: 'actions',
            width: 280,
            render: row => (
                <div onClick={event => event.stopPropagation()}>
                    <ListActions>
                    {canLogs && <ListAction onClick={() => openProcess(row, 'logs')}>Logs</ListAction>}
                    {canControl && <ListAction disabled={Boolean(processActions[`${row.pm2Name}:start`])} onClick={() => processAction(row, 'start')}>Start</ListAction>}
                    {canControl && <ListAction disabled={Boolean(processActions[`${row.pm2Name}:stop`])} onClick={() => processAction(row, 'stop')}>Stop</ListAction>}
                    {canControl && <ListAction disabled={Boolean(processActions[`${row.pm2Name}:restart`])} onClick={() => processAction(row, 'restart')}>Restart</ListAction>}
                    {canControl && <ListAction disabled={Boolean(processActions[`${row.pm2Name}:reload`])} onClick={() => processAction(row, 'reload')}>Reload</ListAction>}
                    {canManage && <ListAction disabled={Boolean(processActions[`${row.pm2Name}:delete`])} onClick={() => processAction(row, 'delete')}>Delete</ListAction>}
                    </ListActions>
                </div>
            ),
        },
    ];

    const runtimeColumns = [
        {
            key: 'name',
            title: 'Runtime',
            width: '18%',
            render: row => runtimeLabel(row.key),
        },
        {
            key: 'status',
            title: 'Status',
            width: 120,
            render: row => (
                <Status intent={row.available ? 'success' : 'danger'} compact>
                    {row.available ? 'Ready' : 'Missing'}
                </Status>
            ),
        },
        {
            key: 'version',
            title: 'Detected version',
            width: '30%',
            render: row => row.version || 'Not detected',
        },
        {
            key: 'path',
            title: 'Path',
            render: row => (
                <span>
                    {row.path || row.launcher || row.detected || '-'}
                    {row.error && <small className="nm-error-line">{row.error}</small>}
                </span>
            ),
        },
    ];

    const envColumns = [
        { key: 'name', title: 'Name', type: 'title', width: '30%' },
        { key: 'value', title: 'Value', render: row => (row.is_secret ? 'Stored encrypted' : row.value) },
        {
            key: 'actions',
            title: 'Actions',
            type: 'actions',
            width: 90,
            render: row => (
                <ListActions>
                    <ListAction onClick={() => deleteEnv(row)}>Delete</ListAction>
                </ListActions>
            ),
        },
    ];

    const backupColumns = [
        { key: 'name', title: 'Name', type: 'title' },
        { key: 'size', title: 'Size', width: 120, render: row => formatBytes(row.size) },
        { key: 'createdAt', title: 'Created', width: 220 },
        {
            key: 'actions',
            title: 'Actions',
            type: 'actions',
            width: 100,
            render: row => (
                <ListActions>
                    <ListAction onClick={() => restoreBackup(row)}>Restore</ListAction>
                </ListActions>
            ),
        },
    ];

    return (
        <Fragment>
            <Toaster ref={toasterRef} position="top-end" />
            <div className="nm-app nm-ui">
                <div className="nm-top-actions">
                    <Button icon="info-circle" component="a" href={config.infoUrl || '#'}>Info</Button>
                    <Button icon="refresh" disabled={loading} onClick={refreshAll} state={busy ? 'loading' : undefined}>Refresh</Button>
                    {runtimeReady && canManage && (
                        <Button icon="plus" intent="primary" onClick={() => setShowCreate(true)}>New Process</Button>
                    )}
                </div>

                {loading ? (
                    <WorkspaceSkeleton />
                ) : (
                    <Fragment>
                {!accessEnabled && (
                    <div className="nm-panel nm-state-panel">
                        <Icon name="lock-closed" />
                        <div>
                            <h3>Node Manager (PM2) is not enabled</h3>
                            <p>{accessMessage || 'Ask the server administrator to enable Node Manager (PM2) for this subscription or service plan.'}</p>
                        </div>
                    </div>
                )}

                {accessEnabled && (
                    <div className="nm-panel nm-domain-card">
                        <div className="nm-domain-grid">
                            <label className="nm-field nm-field-domain">
                                <span>Domain</span>
                                {!domainLocked ? (
                                    <select
                                        className="nm-native-input"
                                        value={selectedDomainId === null || selectedDomainId === undefined ? '' : String(selectedDomainId)}
                                        onChange={event => selectDomain(event.target.value)}
                                    >
                                        {domains.map(domain => (
                                            <option key={domain.id} value={String(domain.id)}>{domain.name}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <NativeInput readOnly value={selectedDomain ? selectedDomain.name : 'Selected domain'} />
                                )}
                            </label>
                            <div className="nm-field">
                                <span>Subscription user</span>
                                <strong>{selectedDomain ? selectedDomain.systemUser : '-'}</strong>
                            </div>
                            <div className="nm-field nm-field-root">
                                <span>Application root</span>
                                <strong className="nm-path">{selectedRootPath || '-'}</strong>
                            </div>
                            <div className="nm-field">
                                <span>Permissions</span>
                                <strong>{canManage ? 'Manage' : (canControl ? 'Control' : (canLogs ? 'Logs' : 'Access'))}</strong>
                            </div>
                        </div>
                    </div>
                )}

                {accessEnabled && showCreate && (
                    <div className="nm-panel nm-view-card">
                        <div className="nm-panel-header">
                            <div>
                                <h3>Create PM2 process</h3>
                                {selectedRootPath && <p className="nm-muted">Relative paths use {selectedRootPath}.</p>}
                            </div>
                            <div className="nm-card-actions">
                                <Button onClick={() => setShowCreate(false)}>Cancel</Button>
                                <Button intent="primary" onClick={createProcess} state={busy ? 'loading' : undefined}>Create and Start</Button>
                            </div>
                        </div>
                        <div className="nm-form-grid">
                            <label className="nm-field">
                                <span>Name</span>
                                <NativeInput value={createForm.name} placeholder="assets-ssr" onChange={event => updateCreateField('name', event.target.value)} />
                            </label>
                            <label className="nm-field">
                                <span>Script path</span>
                                <div className="nm-input-action">
                                    <NativeInput value={createForm.scriptPath} placeholder="server.js" onChange={event => updateCreateField('scriptPath', event.target.value)} />
                                    <Button icon="folder" onClick={() => openPicker('scriptPath', 'file')}>Browse</Button>
                                </div>
                            </label>
                            <label className="nm-field">
                                <span>Working directory</span>
                                <div className="nm-input-action">
                                    <NativeInput value={createForm.cwd} placeholder="." onChange={event => updateCreateField('cwd', event.target.value)} />
                                    <Button icon="folder-open" onClick={() => openPicker('cwd', 'directory')}>Browse</Button>
                                </div>
                            </label>
                            <label className="nm-field">
                                <span>Environment</span>
                                <NativeInput value={createForm.envName} placeholder="production" onChange={event => updateCreateField('envName', event.target.value)} />
                            </label>
                            <label className="nm-field">
                                <span>Instances</span>
                                <NativeInput type="number" min="1" value={createForm.instances} onChange={event => updateCreateField('instances', event.target.value)} />
                            </label>
                            <label className="nm-field">
                                <span>Max restarts</span>
                                <NativeInput type="number" min="0" value={createForm.maxRestarts} onChange={event => updateCreateField('maxRestarts', event.target.value)} />
                            </label>
                            <label className="nm-field">
                                <span>Restart delay, ms</span>
                                <NativeInput type="number" min="0" value={createForm.restartDelay} onChange={event => updateCreateField('restartDelay', event.target.value)} />
                            </label>
                            <label className="nm-field">
                                <span>Git repository</span>
                                <NativeInput value={createForm.gitRepo} placeholder="https://..." onChange={event => updateCreateField('gitRepo', event.target.value)} />
                            </label>
                            <label className="nm-field">
                                <span>Git branch</span>
                                <NativeInput value={createForm.gitBranch} placeholder="main" onChange={event => updateCreateField('gitBranch', event.target.value)} />
                            </label>
                            <div className="nm-field nm-field-wide">
                                <Switch checked={Boolean(createForm.autorestart)} onChange={checked => updateCreateField('autorestart', checked)}>
                                    Autorestart if the process exits
                                </Switch>
                            </div>
                        </div>
                    </div>
                )}

                {accessEnabled && !showCreate && setupNeeded && (
                    <div className="nm-panel nm-setup-panel">
                        <Icon name="warning-triangle" />
                        <div>
                            <h3>Runtime setup required</h3>
                            <p>
                                {!nodeReady
                                    ? 'Node.js and npm were not found for this subscription. Enable Plesk Node.js support or configure the paths below.'
                                    : (!pm2Ready
                                        ? 'PM2 is not installed for the detected Node.js runtime. Administrators can install it from this page.'
                                        : 'Some runtime checks failed. Review detected paths before creating processes.')}
                            </p>
                        </div>
                        <div className="nm-card-actions">
                            {permissions.admin && (
                                <Button
                                    disabled={Boolean(runtimeAction)}
                                    onClick={applyDetectedPaths}
                                    state={runtimeAction === 'paths' ? 'loading' : undefined}
                                >
                                    Use detected paths
                                </Button>
                            )}
                            {permissions.canInstallPm2 && nodeReady && !pm2Ready && (
                                <Button
                                    disabled={Boolean(runtimeAction)}
                                    intent="primary"
                                    onClick={installPm2}
                                    state={runtimeAction === 'pm2' ? 'loading' : undefined}
                                >
                                    Install PM2
                                </Button>
                            )}
                            <Button onClick={() => setView('runtime')}>Review runtime</Button>
                        </div>
                    </div>
                )}

                {accessEnabled && !showCreate && <AppTabs active={activeView} items={viewTabs} onSelect={setView} />}

                {accessEnabled && !showCreate && activeView === 'processes' && runtimeReady && (
                    <div className="nm-view-stack">
                        <div className="nm-stat-grid">
                            <div className="nm-stat-card"><span>Total</span><strong>{totals.total}</strong></div>
                            <div className="nm-stat-card"><span>Online</span><strong>{totals.online}</strong></div>
                            <div className="nm-stat-card"><span>CPU</span><strong>{totals.cpu}%</strong></div>
                            <div className="nm-stat-card"><span>Memory</span><strong>{formatBytes(totals.memory)}</strong></div>
                            <div className="nm-stat-card"><span>Restarts</span><strong>{totals.restarts}</strong></div>
                        </div>
                        <List
                            columns={processColumns}
                            data={processPagination.visibleRows}
                            emptyView={<EmptyView title="No PM2 processes found for this domain" description="Create a process when your app is ready to run under PM2." icon="server" />}
                            emptyViewMode="items"
                            pagination={processPagination.pagination}
                            rowKey="pm2Name"
                            rowProps={row => ({
                                onClick: () => openProcess(row),
                                className: selectedProcess && selectedProcess.pm2Name === row.pm2Name ? 'nm-selected-row' : '',
                            })}
                            totalRows={processes.length}
                        />
                    </div>
                )}

                {accessEnabled && !showCreate && activeView === 'runtime' && (
                    <div className="nm-panel nm-view-card">
                        <div className="nm-panel-header">
                            <h3>Runtime</h3>
                            <div className="nm-card-actions">
                                {permissions.admin && (
                                    <Button
                                        disabled={Boolean(runtimeAction)}
                                        onClick={applyDetectedPaths}
                                        state={runtimeAction === 'paths' ? 'loading' : undefined}
                                    >
                                        Use detected paths
                                    </Button>
                                )}
                                {permissions.canInstallPm2 && nodeReady && (
                                    <Button
                                        disabled={Boolean(runtimeAction)}
                                        intent="primary"
                                        onClick={installPm2}
                                        state={runtimeAction === 'pm2' ? 'loading' : undefined}
                                    >
                                        Install or update PM2
                                    </Button>
                                )}
                            </div>
                        </div>
                        <List
                            columns={runtimeColumns}
                            data={runtimeRows}
                            emptyView={<EmptyView title="No runtime information" icon="info-circle" />}
                            emptyViewMode="items"
                            rowKey="key"
                        />
                        <div className="nm-field nm-pm2-home">
                            <span>PM2 home</span>
                            <strong className="nm-path">{runtime && runtime.pm2Home ? runtime.pm2Home : '-'}</strong>
                        </div>
                    </div>
                )}

                {accessEnabled && !showCreate && activeView === 'settings' && runtimeReady && permissions.admin && (
                    <div className="nm-panel nm-view-card">
                        <div className="nm-panel-header">
                            <h3>Settings</h3>
                        </div>
                        <div className="nm-form-grid">
                            {Object.keys(settings || {}).map(key => (
                                <label className="nm-field" key={key}>
                                    <span>{settingLabel(key)}</span>
                                    <NativeInput
                                        value={settings[key] === null || settings[key] === undefined ? '' : settings[key]}
                                        onChange={event => setSettings(current => ({ ...current, [key]: event.target.value }))}
                                    />
                                </label>
                            ))}
                        </div>
                        <div className="nm-card-actions">
                            <Button intent="primary" onClick={saveSettings}>Save Settings</Button>
                        </div>
                    </div>
                )}

                {accessEnabled && !showCreate && activeView === 'backups' && runtimeReady && permissions.admin && (
                    <div className="nm-panel nm-view-card">
                        <div className="nm-panel-header">
                            <h3>Backups</h3>
                            <div className="nm-card-actions">
                                <Button icon="plus" intent="primary" onClick={createBackup}>Create Backup</Button>
                            </div>
                        </div>
                        <List
                            columns={backupColumns}
                            data={backups}
                            emptyView={<EmptyView title="No backups created yet" icon="backup" />}
                            emptyViewMode="items"
                            rowKey="name"
                        />
                    </div>
                )}
                    </Fragment>
                )}
            </div>

            <Dialog
                isOpen={picker.open}
                onClose={closePicker}
                size="lg"
                title={picker.title || 'Select path'}
                subtitle={picker.currentPath || selectedRootPath}
                cancelButton={{ children: 'Close' }}
            >
                <Feedback message={picker.error} intent="danger" />
                <Toolbar>
                    <ToolbarGroup title="Browse actions" groupable={false}>
                        <Button icon="home" onClick={() => browsePath('.')}>Document root</Button>
                        <Button icon="arrow-up" disabled={!picker.parentValue} onClick={() => browsePath(picker.parentValue)}>Up</Button>
                        {picker.mode === 'directory' && (
                            <Button intent="primary" icon="check-mark" onClick={chooseCurrentDirectory}>Use this directory</Button>
                        )}
                    </ToolbarGroup>
                </Toolbar>
                <List
                    columns={[
                        {
                            key: 'name',
                            title: 'Name',
                            type: 'title',
                            render: row => (
                                <div className="nm-picker-name">
                                    <button
                                        className="nm-picker-link"
                                        type="button"
                                        onClick={() => choosePickerEntry(row)}
                                    >
                                        <Icon name={row.type === 'directory' ? 'folder' : 'file'} /> {row.name}
                                    </button>
                                    <span className="nm-picker-actions">
                                        {row.type === 'directory' && (
                                            <Button icon="folder-open" onClick={() => browsePath(row.value)}>Open</Button>
                                        )}
                                        {row.type === 'file' && row.selectable && (
                                            <Button icon="check-mark" intent="primary" onClick={() => choosePickerEntry(row)}>Select</Button>
                                        )}
                                        {row.type === 'file' && row.editable && (
                                            <Button icon="pencil" onClick={() => openFileEditor(row)}>Edit</Button>
                                        )}
                                    </span>
                                </div>
                            ),
                        },
                        { key: 'type', title: 'Type', width: 120 },
                        { key: 'modifiedAt', title: 'Modified', width: 190 },
                    ]}
                    data={picker.entries}
                    emptyView={<EmptyView title={picker.loading ? 'Loading...' : 'No files found'} icon="folder" />}
                    emptyViewMode="items"
                    rowKey="path"
                />
            </Dialog>

            <Drawer
                title="Edit file"
                subtitle={fileEditor.value || ''}
                isOpen={fileEditor.open}
                placement="right"
                size="lg"
                className="nm-file-editor-drawer"
                closingConfirmation={fileEditor.content !== fileEditor.originalContent && !fileEditor.saving}
                onClose={closeFileEditor}
                form={{
                    values: {
                        content: fileEditor.content || '',
                    },
                    state: fileEditor.saving ? 'submit' : undefined,
                    submitButton: {
                        children: 'Save',
                        disabled: fileEditor.loading,
                    },
                    cancelButton: {
                        children: 'Close',
                    },
                    applyButton: false,
                    onFieldChange: (name, value) => {
                        if (name === 'content') {
                            setFileEditor(current => ({ ...current, content: value }));
                        }
                    },
                    onSubmit: values => saveFileEditor(values.content || ''),
                }}
                data-type="file-editor"
            >
                <div className="nm-file-editor-drawer-body">
                    {fileEditor.error && <Feedback message={fileEditor.error} intent="danger" />}
                    {fileEditor.loading ? (
                        <div className="nm-file-editor-loading">
                            <SkeletonBlock className="nm-skeleton-label" />
                            <SkeletonBlock className="nm-skeleton-path" />
                            <SkeletonBlock className="nm-skeleton-path" />
                        </div>
                    ) : (
                        <FormField name="content" label={null} vertical>
                            {({ setValue, getValue }) => (
                                <CodeEditor
                                    fileName={fileEditor.value || 'file'}
                                    onChange={content => setValue(content)}
                                    options={{
                                        lineNumbers: true,
                                        lineWrapping: true,
                                        viewportMargin: Infinity,
                                    }}
                                >
                                    {getValue('') || ''}
                                </CodeEditor>
                            )}
                        </FormField>
                    )}
                </div>
            </Drawer>

            <Drawer
                isOpen={Boolean(selectedProcess)}
                onClose={closeProcess}
                size="lg"
                placement="right"
                className="nm-process-drawer"
                title={selectedProcess ? (
                    <div className="nm-process-drawer-title">
                        <span>{selectedProcess.name}</span>
                        <Status intent={statusIntent(selectedProcess.status)} compact>
                            {selectedProcess.status || 'unknown'}
                        </Status>
                    </div>
                ) : ''}
                subtitle={selectedProcess ? selectedProcess.cwd || selectedProcess.scriptPath || '' : ''}
                form={{
                    submitButton: false,
                    applyButton: false,
                    cancelButton: { children: 'Close' },
                }}
            >
                {selectedProcess && (
                    <div className="nm-detail-stack nm-process-detail-stack">
                        <AppTabs
                            active={activeDetail}
                            onSelect={changeDetail}
                            items={[
                                { key: 'logs', title: 'Logs', hidden: !canLogs },
                                { key: 'env', title: 'Env', hidden: !canManage },
                                { key: 'deploy', title: 'Deploy', hidden: !canManage },
                                { key: 'ecosystem', title: 'Ecosystem', hidden: !canManage },
                                { key: 'metrics', title: 'Metrics' },
                            ]}
                        />

                        {activeDetail === 'logs' && (
                            <div className="nm-detail-stack nm-process-pane nm-process-logs">
                                <Toolbar>
                                    <ToolbarGroup title="Log actions" groupable={false}>
                                        <Select value={logStream} onChange={value => setLogStream(value || 'stdout')} size="sm">
                                            <SelectOption value="stdout">stdout</SelectOption>
                                            <SelectOption value="stderr">stderr</SelectOption>
                                        </Select>
                                        <Button icon="refresh" onClick={() => loadLogs()}>Refresh</Button>
                                        {canManage && <Button icon="remove" onClick={clearLogs}>Clear</Button>}
                                        <Button icon="download" component="a" href={downloadLogsUrl()}>Download</Button>
                                        {logMeta && <span className="nm-muted">{formatBytes(logMeta.size)}</span>}
                                    </ToolbarGroup>
                                </Toolbar>
                                <pre className="nm-terminal">{logContent}</pre>
                            </div>
                        )}

                        {activeDetail === 'env' && (
                            <div className="nm-detail-stack nm-process-pane">
                                <div className="nm-env-editor">
                                    <NativeInput placeholder="NAME" value={newEnv.name} onChange={event => setNewEnv(current => ({ ...current, name: event.target.value }))} />
                                    <NativeInput placeholder="value" type={newEnv.isSecret ? 'password' : 'text'} value={newEnv.value} onChange={event => setNewEnv(current => ({ ...current, value: event.target.value }))} />
                                    <Switch checked={Boolean(newEnv.isSecret)} onChange={checked => setNewEnv(current => ({ ...current, isSecret: checked }))}>Secret</Switch>
                                    <Button intent="primary" onClick={saveEnv}>Save</Button>
                                </div>
                                <List
                                    columns={envColumns}
                                    data={envRows}
                                    emptyView={<EmptyView title="No environment variables" icon="key" />}
                                    emptyViewMode="items"
                                    rowKey="name"
                                />
                            </div>
                        )}

                        {activeDetail === 'deploy' && (
                            <div className="nm-detail-stack nm-process-pane">
                                <div className="nm-switch-grid">
                                    <Switch checked={deployForm.npmInstall} onChange={checked => setDeployForm(current => ({ ...current, npmInstall: checked }))}>npm install</Switch>
                                    <Switch checked={deployForm.production} onChange={checked => setDeployForm(current => ({ ...current, production: checked }))}>production dependencies only</Switch>
                                    <Switch checked={deployForm.reload} onChange={checked => setDeployForm(current => ({ ...current, reload: checked }))}>zero-downtime reload</Switch>
                                </div>
                                <div className="nm-card-actions">
                                    <Button intent="primary" icon="upload" onClick={deploy} state={busy ? 'loading' : undefined}>Deploy Latest</Button>
                                    <Button onClick={() => toggleWebhook(true)}>Enable Webhook</Button>
                                    <Button onClick={() => toggleWebhook(false)}>Disable Webhook</Button>
                                </div>
                                {webhook.url && <NativeInput readOnly value={webhook.url} />}
                            </div>
                        )}

                        {activeDetail === 'ecosystem' && (
                            <div className="nm-detail-stack nm-process-pane nm-process-ecosystem">
                                <Toolbar>
                                    <ToolbarGroup title="Ecosystem actions" groupable={false}>
                                        <span className="nm-muted nm-path">{ecosystem.path || '-'}</span>
                                        <Button onClick={() => saveEcosystem(false)}>Save</Button>
                                        <Button intent="primary" onClick={() => saveEcosystem(true)}>Save and Start</Button>
                                    </ToolbarGroup>
                                </Toolbar>
                                <CodeEditor
                                    fileName="ecosystem.config.js"
                                    onChange={content => setEcosystem(current => ({ ...current, content }))}
                                    options={{
                                        lineNumbers: true,
                                        lineWrapping: true,
                                        viewportMargin: Infinity,
                                    }}
                                >
                                    {ecosystem.content || ''}
                                </CodeEditor>
                            </div>
                        )}

                        {activeDetail === 'metrics' && (
                            <div className="nm-process-pane nm-process-metrics">
                                <MetricsPanel metrics={metrics} />
                            </div>
                        )}
                    </div>
                )}
            </Drawer>
        </Fragment>
    );
};

NodeManagerApp.propTypes = {
    config: PropTypes.shape({
        csrf: PropTypes.string,
        domainContextId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
        domainLocked: PropTypes.bool,
        endpoints: PropTypes.object,
        info: PropTypes.object,
        infoUrl: PropTypes.string,
        initialDomainId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    }).isRequired,
};

const Root = props => <NodeManagerApp config={props} />;

export const mount = id => {
    const element = document.getElementById(id);
    if (!element || element.dataset.nmUiMounted === '1') {
        return;
    }

    element.dataset.nmUiMounted = '1';
    const root = createRoot(element);
    root.render(<Root {...getProps(element)} />);
};

export default { mount };
