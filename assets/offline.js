document.querySelector('#retry').onclick = () => location.reload();
function status() { document.querySelector('#connection').textContent = navigator.onLine ? 'Ağ bağlantısı var. Yeniden deneyebilirsin.' : 'Şu anda çevrimdışısın.'; }
window.addEventListener('online', status);
window.addEventListener('offline', status);
status();
