document.addEventListener('DOMContentLoaded', () => {
    const getLocalDate = (dateString) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateString || '');
        if (!match) {
            return null;
        }

        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const dateRangeFor = (filterValue) => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (filterValue === 'latest') {
            const start = new Date(today);
            start.setDate(start.getDate() - 6);
            return { start, end: today };
        }

        if (filterValue === 'last-week') {
            const currentWeekStart = new Date(today);
            const daysSinceMonday = (today.getDay() + 6) % 7;
            currentWeekStart.setDate(currentWeekStart.getDate() - daysSinceMonday);
            const end = new Date(currentWeekStart);
            end.setDate(end.getDate() - 1);
            const start = new Date(end);
            start.setDate(start.getDate() - 6);
            return { start, end };
        }

        if (filterValue === 'last-month') {
            const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const end = new Date(today.getFullYear(), today.getMonth(), 0);
            return { start, end };
        }

        return null;
    };

    const filters = [
        {
            filterId: 'duty-record-date-filter',
            countId: 'duty-record-count',
            emptyId: 'duty-record-filter-empty',
            rowSelector: '[data-duty-record-row]',
            noun: 'record',
        },
        {
            filterId: 'duty-submission-date-filter',
            countId: 'duty-submission-count',
            emptyId: 'duty-submission-filter-empty',
            rowSelector: '[data-duty-submission-row]',
            noun: 'submission',
        },
        {
            filterId: 'updates-date-filter',
            countId: 'updates-count',
            rowSelector: '[data-update-date]',
            dateAttribute: 'updateDate',
            noun: 'update',
            observerId: 'notifications-list',
            emptyGroups: [
                {
                    emptySelector: '[data-updates-empty="announcements"]',
                    rowSelector: '.scholar-announcement-trigger',
                },
                {
                    emptySelector: '[data-updates-empty="notifications"]',
                    rowSelector: '.notification-item',
                },
            ],
        },
        {
            filterId: 'scholar-report-date-filter',
            countId: 'scholar-report-count',
            emptyId: 'scholar-report-filter-empty',
            rowSelector: '[data-scholar-report-row]',
            dateAttribute: 'reportDate',
            noun: 'report',
        },
    ];

    filters.forEach(({ filterId, countId, emptyId, rowSelector, dateAttribute = 'dutyDate', noun, observerId, emptyGroups = [] }) => {
        const filter = document.getElementById(filterId);
        const countLabel = document.getElementById(countId);
        const emptyRow = emptyId ? document.getElementById(emptyId) : null;

        if (!(filter instanceof HTMLSelectElement) || !(countLabel instanceof HTMLElement) || (!emptyRow && emptyGroups.length === 0)) {
            return;
        }

        const updateRows = () => {
            const range = dateRangeFor(filter.value);
            const rows = Array.from(document.querySelectorAll(rowSelector));
            let visibleCount = 0;

            rows.forEach((row) => {
                const date = getLocalDate(row.dataset[dateAttribute] || '');
                const visible = !range || (date !== null && date >= range.start && date <= range.end);
                row.hidden = !visible;
                if (visible) {
                    visibleCount++;
                }
            });

            countLabel.textContent = `${visibleCount} ${noun}${visibleCount === 1 ? '' : 's'}`;
            if (emptyRow) {
                emptyRow.hidden = visibleCount > 0 || rows.length === 0;
            }

            emptyGroups.forEach(({ emptySelector, rowSelector: groupRowSelector }) => {
                const groupEmpty = document.querySelector(emptySelector);
                if (!(groupEmpty instanceof HTMLElement)) {
                    return;
                }

                const groupRows = Array.from(document.querySelectorAll(groupRowSelector));
                const groupVisibleCount = groupRows.filter((row) => !row.hidden).length;
                groupEmpty.hidden = groupVisibleCount > 0 || groupRows.length === 0;
            });
        };

        filter.addEventListener('change', updateRows);
        updateRows();

        if (observerId) {
            const observedList = document.getElementById(observerId);
            if (observedList) {
                new MutationObserver(updateRows).observe(observedList, { childList: true, subtree: true });
            }
        }
    });
});
