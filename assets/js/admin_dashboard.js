document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('dashboardSearch');
    const tableRows = document.querySelectorAll('.tbl-wrap tbody tr');
    const refreshBtn = document.getElementById('btnRefresh');

    // Filter table by search input
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            tableRows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }

    // Refresh page / simulate data refresh
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            refreshBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 11-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg> Refreshing...`;
            refreshBtn.disabled = true;
            
            // Reload page to get fresh data
            setTimeout(() => {
                window.location.reload();
            }, 600);
        });
    }
});
