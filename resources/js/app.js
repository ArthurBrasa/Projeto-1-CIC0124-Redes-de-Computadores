import './echo';

const connection = window.Echo.connector.pusher.connection;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let wasConnected = false;

// Real WebSocket status in the top bar ("WS ON", "WS CONNECTING", "WS UNAVAILABLE", ...).
const showState = (state) => {
    document.querySelectorAll('[data-ws]').forEach((el) => {
        el.textContent = state === 'connected' ? 'ON' : state.toUpperCase();
        el.style.color = state === 'connected' ? '' : 'var(--bad)';
    });
};

// Tell the server about disconnections / reconnections, so they are logged with timestamps.
const report = (previous, current) => {
    if (!csrf || !document.querySelector('[data-ws]')) return; // only on logged-in pages
    fetch('/telemetry/ws', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: JSON.stringify({ previous, current, socket_id: window.Echo.socketId() ?? null }),
        keepalive: true,
    }).catch(() => {}); // the server may be the thing that is down
};

connection.bind('state_change', ({ previous, current }) => {
    showState(current);

    if (current === 'connected' && wasConnected) {
        // back after a drop: events broadcast meanwhile were missed, so reload every component's data
        window.Livewire?.all().forEach((component) => component.$wire.$refresh());
    }

    if (wasConnected || current === 'connected') report(previous, current);
    if (current === 'connected') wasConnected = true;
});

showState(connection.state);
