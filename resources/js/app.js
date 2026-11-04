import './echo';

// Real WebSocket status in the top bar ("WS ON", "WS CONNECTING", ...).
const connection = window.Echo.connector.pusher.connection;

const showState = (state) => {
    document.querySelectorAll('[data-ws]').forEach((el) => {
        el.textContent = state === 'connected' ? 'ON' : state.toUpperCase();
        el.style.color = state === 'connected' ? '' : 'var(--bad)';
    });
};

connection.bind('state_change', ({ current }) => showState(current));
showState(connection.state);
