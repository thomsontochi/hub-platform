<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checklist Updates Demo</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        hub: {
                            charcoal: '#1C1917',
                            accent: '#FF5A3C',
                            slate: '#F5F5F4',
                        },
                    },
                },
            },
        }
    </script>
    <script defer src="https://js.pusher.com/8.2/pusher.min.js"></script>
</head>
<body class="min-h-screen bg-hub-slate text-hub-charcoal">
    <div class="max-w-4xl mx-auto px-6 py-10">
        <header class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Real-Time Checklist Demo</h1>
                <p class="mt-2 text-sm text-neutral-600">Connects to Soketi and streams <code class="px-1 py-0.5 bg-white rounded text-xs border">checklist.updated</code> events.</p>
            </div>
            <span class="inline-flex items-center gap-2 text-xs font-medium uppercase tracking-wide bg-white border border-neutral-200 px-3 py-1 rounded-full">
                <span class="size-2.5 rounded-full bg-emerald-500" id="status-indicator"></span>
                <span id="status-label">Connecting</span>
            </span>
        </header>

        <section class="bg-white border border-neutral-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-200 flex flex-wrap items-center gap-4">
                <label class="text-sm font-medium tracking-wide uppercase text-neutral-500" for="country-input">Country</label>
                <select id="country-input" class="text-sm border border-neutral-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-hub-accent">
                    <option value="usa">USA</option>
                    <option value="germany">Germany</option>
                </select>
                <button id="connect-btn" class="ml-auto inline-flex items-center gap-2 text-sm font-medium bg-hub-charcoal text-white px-4 py-2 rounded-lg shadow hover:bg-black transition">
                    <span>Reconnect</span>
                </button>
            </div>
            <div class="grid gap-6 md:grid-cols-2 px-6 py-6" id="event-panels">
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-neutral-500">Summary</h2>
                    <div id="summary-panel" class="bg-neutral-50 border border-neutral-200 rounded-lg p-4 text-sm text-neutral-700 min-h-[140px] flex items-center justify-center">
                        <span class="text-neutral-400">Waiting for events&hellip;</span>
                    </div>
                </div>
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-neutral-500">Event Log</h2>
                    <div class="bg-neutral-900 text-white rounded-lg p-4 text-xs font-mono space-y-3 max-h-64 overflow-y-auto" id="log-panel">
                        <div class="opacity-60">Logs will appear here when messages arrive.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-10 text-sm text-neutral-600 leading-relaxed">
            <h2 class="text-base font-semibold text-hub-charcoal mb-2">How it works</h2>
            <ol class="list-decimal list-inside space-y-1">
                <li>The hub service broadcasts <code class="px-1 py-0.5 bg-white rounded text-xs border">checklist.updated</code> events on <code class="px-1 py-0.5 bg-white rounded text-xs border">hub.country.&lt;country&gt;.checklist</code>.</li>
                <li>This page reads your Pusher/Soketi credentials from configuration and establishes a WebSocket connection.</li>
                <li>Use the CLI utilities in the HR service to seed employees and publish simulated events to see updates appear live.</li>
            </ol>
        </section>
    </div>

    <script>
        const pusherConfig = {
            key: @json($config['key']),
            cluster: @json($config['cluster']),
            wsHost: @json($config['host']),
            wsPort: @json($config['port']),
            wssPort: @json($config['port']),
            forceTLS: @json($config['scheme'] === 'https'),
            enabledTransports: ['ws', 'wss'],
            authorizer: null,
        };

        const statusIndicator = document.getElementById('status-indicator');
        const statusLabel = document.getElementById('status-label');
        const summaryPanel = document.getElementById('summary-panel');
        const logPanel = document.getElementById('log-panel');
        const countrySelect = document.getElementById('country-input');
        const reconnectButton = document.getElementById('connect-btn');

        let pusherInstance = null;
        let activeChannel = null;
        let retryTimeoutId = null;

        const scheduleRetry = () => {
            if (retryTimeoutId) {
                return;
            }

            retryTimeoutId = setTimeout(() => {
                retryTimeoutId = null;
                subscribe();
            }, 200);
        };

        const logMessage = (payload) => {
            const entry = document.createElement('pre');
            entry.className = 'whitespace-pre-wrap break-words bg-neutral-800/70 border border-neutral-700 rounded-lg p-3';
            entry.textContent = JSON.stringify(payload, null, 2);
            logPanel.prepend(entry);
        };

        const updateSummary = (payload) => {
            summaryPanel.innerHTML = `
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-neutral-500 uppercase tracking-wide text-xs">Country</dt>
                        <dd class="font-semibold text-lg">${payload.country}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 uppercase tracking-wide text-xs">Employees</dt>
                        <dd class="font-semibold text-lg">${payload.summary.total_employees}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 uppercase tracking-wide text-xs">Completed</dt>
                        <dd class="font-semibold text-lg">${payload.summary.complete_employees}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500 uppercase tracking-wide text-xs">Completion Rate</dt>
                        <dd class="font-semibold text-lg">${(payload.summary.average_completion_rate * 100).toFixed(1)}%</dd>
                    </div>
                </dl>
            `;
        };

        const setStatus = (state, message) => {
            const palette = {
                connecting: 'bg-yellow-400',
                connected: 'bg-emerald-500',
                failed: 'bg-red-500',
            };
            statusIndicator.className = `size-2.5 rounded-full ${palette[state] || 'bg-gray-300'}`;
            statusLabel.textContent = message;
        };

        const subscribe = () => {
            const country = countrySelect.value;
            const channelName = `hub.country.${country}.checklist`;

            if (activeChannel) {
                activeChannel.unsubscribe();
            }

            if (typeof window.Pusher === 'undefined') {
                setStatus('connecting', 'Loading client SDK…');
                scheduleRetry();
                return;
            }

            if (!pusherInstance) {
                Pusher.logToConsole = false;
                pusherInstance = new Pusher(pusherConfig.key, {
                    cluster: pusherConfig.cluster || undefined,
                    wsHost: pusherConfig.wsHost,
                    wsPort: pusherConfig.wsPort,
                    wssPort: pusherConfig.wssPort,
                    forceTLS: pusherConfig.forceTLS,
                    enabledTransports: pusherConfig.enabledTransports,
                    disableStats: true,
                });

                if (retryTimeoutId) {
                    clearTimeout(retryTimeoutId);
                    retryTimeoutId = null;
                }

                pusherInstance.connection.bind('connected', () => setStatus('connected', 'Connected'));
                pusherInstance.connection.bind('connecting_in', () => setStatus('connecting', 'Reconnecting'));
                pusherInstance.connection.bind('failed', () => setStatus('failed', 'Disconnected'));
            }

            setStatus('connecting', 'Connecting');
            activeChannel = pusherInstance.subscribe(channelName);
            activeChannel.bind('pusher:subscription_succeeded', () => {
                setStatus('connected', `Listening on ${channelName}`);
                logMessage({ info: `Subscribed to ${channelName}` });
            });

            activeChannel.bind('checklist.updated', (data) => {
                updateSummary(data);
                logMessage({ event: 'checklist.updated', received_at: new Date().toISOString(), payload: data });
            });

            activeChannel.bind_global((eventName, data) => {
                if (eventName !== 'checklist.updated') {
                    logMessage({ event: eventName, payload: data });
                }
            });
        };

        reconnectButton.addEventListener('click', () => {
            subscribe();
        });

        countrySelect.addEventListener('change', () => {
            subscribe();
        });

        subscribe();
    </script>
</body>
</html>
