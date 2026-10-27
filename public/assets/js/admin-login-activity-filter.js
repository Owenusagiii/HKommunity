document.addEventListener('DOMContentLoaded', () => {
    const periodFilter = document.getElementById('login-activity-period');
    const loginCount = document.getElementById('login-activity-result-count');
    const filterEmptyRow = document.querySelector('.admin-login-activity-filter-empty');
    const loginRows = Array.from(document.querySelectorAll('tr[data-admin-login-activity-row]'));

    if (
        periodFilter instanceof HTMLSelectElement
        && loginCount instanceof HTMLElement
        && filterEmptyRow instanceof HTMLTableRowElement
    ) {
        const filterLoginActivities = () => {
            const selectedPeriod = periodFilter.value;
            const now = new Date();
            const sevenDaysAgo = now.getTime() - 7 * 24 * 60 * 60 * 1000;
            const startOfThisWeek = new Date(now);
            startOfThisWeek.setHours(0, 0, 0, 0);
            startOfThisWeek.setDate(startOfThisWeek.getDate() - ((startOfThisWeek.getDay() + 6) % 7));
            const startOfLastWeek = new Date(startOfThisWeek);
            startOfLastWeek.setDate(startOfLastWeek.getDate() - 7);
            const startOfThisMonth = new Date(now.getFullYear(), now.getMonth(), 1);
            const startOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            let visibleCount = 0;

            loginRows.forEach((row, index) => {
                const timestamp = Number(row.dataset.loginTimestamp) * 1000;
                const matchesPeriod = (selectedPeriod === 'recent' && index < 8)
                    || (selectedPeriod === 'last_7_days' && timestamp >= sevenDaysAgo)
                    || (selectedPeriod === 'last_week'
                        && timestamp >= startOfLastWeek.getTime()
                        && timestamp < startOfThisWeek.getTime())
                    || (selectedPeriod === 'last_month'
                        && timestamp >= startOfLastMonth.getTime()
                        && timestamp < startOfThisMonth.getTime());
                row.hidden = !matchesPeriod;

                if (!row.hidden) {
                    visibleCount++;
                }
            });

            filterEmptyRow.hidden = visibleCount > 0;
            loginCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'login' : 'logins'}`;
        };

        periodFilter.addEventListener('change', filterLoginActivities);
        filterLoginActivities();
    } else if (loginRows.length > 0) {
        console.error('Unable to initialize login activity filter: filter controls were not found.');
    }
});
