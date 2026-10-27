document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('notifications-list');

    if (!list) {
        return;
    }

    const apiUrl = list.dataset.apiUrl;
    const csrfToken = list.dataset.csrfToken || '';
    const markAllButton = document.getElementById('mark-all-notifications-read');
    const actionStatus = document.getElementById('notifications-action-status');
    let unreadCount = 0;

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const formatDate = (value) => {
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString('en-PH', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });
    };

    const load = async () => {
        try {
            const response = await fetch(apiUrl, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to load notifications.');
            }

            unreadCount = Number(data.unread_count) || 0;
            updateMarkAllButton();

            if (!data.notifications.length) {
                list.innerHTML = `
                    <div class="panel empty-announcements empty-notifications">
                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        <h2>No notifications yet</h2>
                        <p>Important updates and activity will appear here.</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = data.notifications.map((notification) => `
                <article
                    class="panel announce-card notification-item cat-${escapeHtml(notification.type)} ${Number(notification.is_read) === 0 ? 'unread' : ''}"
                    data-id="${Number(notification.id)}"
                    data-update-date="${escapeHtml(String(notification.created_at || '').slice(0, 10))}"
                    tabindex="0"
                    aria-expanded="false"
                >
                    <div class="announce-body">
                        <div class="notification-summary">
                            <h2 class="announce-title">${escapeHtml(notification.title)}</h2>
                            ${Number(notification.is_read) === 0 ? '<span class="notification-status">Unread</span>' : ''}
                        </div>
                        <p class="announce-desc">${escapeHtml(notification.message)}</p>
                        <div class="notification-details" hidden>
                            <span class="announce-tag">${escapeHtml(notification.type).toUpperCase()} &bull; ${escapeHtml(formatDate(notification.created_at))}</span>
                            ${notification.registration_id ? `<p><strong>Registration ID:</strong> ${Number(notification.registration_id)}</p>` : ''}
                        </div>
                    </div>
                </article>
            `).join('');
        } catch (error) {
            console.error(error);
            list.innerHTML = `<p>${escapeHtml(error.message)}</p>`;
        }
    };

    const markRead = async (id) => {
        const body = new URLSearchParams({
            action: 'mark_as_read',
            notification_id: String(id),
            csrf_token: csrfToken,
        });

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to mark notification as read.');
        }
        return true;
    };

    const updateMarkAllButton = () => {
        if (markAllButton instanceof HTMLButtonElement) {
            markAllButton.disabled = unreadCount === 0;
        }
    };

    const updateHeaderUnreadBadge = () => {
        const badge = document.querySelector('.notification-toggle .notification-badge');
        if (unreadCount === 0) {
            badge?.remove();
        } else if (badge instanceof HTMLElement) {
            badge.textContent = String(unreadCount);
        }
    };

    markAllButton?.addEventListener('click', async () => {
        if (!(markAllButton instanceof HTMLButtonElement)) {
            return;
        }

        markAllButton.disabled = true;
        if (actionStatus instanceof HTMLElement) {
            actionStatus.textContent = 'Marking notifications as read...';
        }

        try {
            const body = new URLSearchParams({
                action: 'mark_all_as_read',
                csrf_token: csrfToken,
            });
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body,
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to mark notifications as read.');
            }

            unreadCount = 0;
            list.querySelectorAll('.notification-item.unread').forEach((item) => {
                item.classList.remove('unread');
                item.querySelector('.notification-status')?.remove();
            });
            document.querySelectorAll('.notification-preview.unread').forEach((item) => {
                item.classList.remove('unread');
            });
            updateMarkAllButton();
            updateHeaderUnreadBadge();
            if (actionStatus instanceof HTMLElement) {
                actionStatus.textContent = 'All notifications marked as read.';
            }
        } catch (error) {
            console.error(error);
            if (actionStatus instanceof HTMLElement) {
                actionStatus.textContent = error instanceof Error ? error.message : 'Unable to mark notifications as read.';
            }
            updateMarkAllButton();
        }
    });

    const markNotificationRead = async (item) => {
        if (!item.classList.contains('unread')) {
            return;
        }

        try {
            await markRead(Number(item.dataset.id));
            item.classList.remove('unread');
            item.querySelector('.notification-status')?.remove();
            unreadCount = Math.max(0, unreadCount - 1);
            updateMarkAllButton();
            updateHeaderUnreadBadge();
        } catch (error) {
            console.error(error);
            if (actionStatus instanceof HTMLElement) {
                actionStatus.textContent = error instanceof Error ? error.message : 'Unable to mark notification as read.';
            }
        }
    };

    const toggle = async (item) => {
        const details = item.querySelector('.notification-details');
        const expanded = item.getAttribute('aria-expanded') === 'true';

        details.hidden = expanded;
        item.setAttribute('aria-expanded', expanded ? 'false' : 'true');

        if (!expanded) {
            await markNotificationRead(item);
        }
    };

    const hoverPointer = window.matchMedia('(hover: hover) and (pointer: fine)');

    list.addEventListener('pointerover', (event) => {
        if (!hoverPointer.matches) {
            return;
        }

        const item = event.target.closest('.notification-item');
        if (item && !item.contains(event.relatedTarget)) {
            item.setAttribute('aria-expanded', 'true');
        }
    });

    list.addEventListener('pointerout', (event) => {
        if (!hoverPointer.matches) {
            return;
        }

        const item = event.target.closest('.notification-item');
        if (item && !item.contains(event.relatedTarget)) {
            const details = item.querySelector('.notification-details');
            item.setAttribute('aria-expanded', details.hidden ? 'false' : 'true');
        }
    });

    list.addEventListener('click', (event) => {
        const item = event.target.closest('.notification-item');
        if (item) {
            if (hoverPointer.matches) {
                markNotificationRead(item);
            } else {
                toggle(item);
            }
        }
    });

    list.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const item = event.target.closest('.notification-item');
        if (!item) {
            return;
        }

        event.preventDefault();
        toggle(item);
    });

    load();
});
