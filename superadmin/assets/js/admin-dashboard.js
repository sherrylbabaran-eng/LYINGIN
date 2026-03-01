/**
 * Admin Dashboard Module
 * Manages data fetching and rendering for the super admin dashboard
 */

const AdminDashboard = (() => {
    const config = {
        baseApiUrl: '../auth/api',
        loginPage: '../index.html',
        refreshInterval: 300000 // 5 minutes
    };

    let csrfToken = null;
    let refreshTimer = null;
    let detailsModalInstance = null; // Reuse modal instance

    /**
     * Initialize the dashboard
     */
    async function init() {
        const isAuthenticated = await ensureSuperAdminSession();
        if (!isAuthenticated) {
            return;
        }

        // Initialize modal instance once with focus disabled
        const modalEl = document.getElementById('detailsModal');
        if (modalEl) {
            // Set inert on hidden modal to prevent focus conflicts
            modalEl.inert = true;
            
            // Create modal with focus disabled
            detailsModalInstance = new bootstrap.Modal(modalEl, {
                focus: false
            });
            
            // Remove inert when modal is shown
            modalEl.addEventListener('show.bs.modal', () => {
                modalEl.inert = false;
            });
            
            // Add inert back when modal is hidden
            modalEl.addEventListener('hide.bs.modal', () => {
                modalEl.inert = true;
            });
        }

        bindTopbarActions();
        await fetchCSRFToken();
        await loadDashboardData();
        await loadClinicsList();
        await loadMessages();
        await loadNotifications();
        await loadChartsData();
        
        // Setup auto-refresh and timestamp updates
        setupAutoRefresh();
        setupTimestampUpdates();
    }

    /**
     * Fetch CSRF token from server
     */
    async function fetchCSRFToken() {
        try {
            const response = await fetch('../auth/api/get-csrf-token.php');
            const data = await response.json();
            if (data && data.token) {
                csrfToken = data.token;
            }
        } catch (error) {
            console.error('Failed to fetch CSRF token:', error);
        }
    }

    async function ensureSuperAdminSession() {
        try {
            const response = await fetch('../auth/api/current-user.php', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                window.location.replace(config.loginPage);
                return false;
            }

            const payload = await response.json();
            const userType = payload?.user?.type || '';

            if (payload.status !== 'success' || userType !== 'superadmin') {
                window.location.replace(config.loginPage);
                return false;
            }

            applyCurrentUser(payload.user || {});

            return true;
        } catch (error) {
            window.location.replace(config.loginPage);
            return false;
        }
    }

    function applyCurrentUser(user) {
        const profileNameEl = document.getElementById('profileUserName');
        if (!profileNameEl) {
            return;
        }

        const resolvedName = (user.name || user.email || 'Super Admin').toString().trim();
        profileNameEl.textContent = resolvedName || 'Super Admin';
    }

    function bindTopbarActions() {
        const activityLogActionEl = document.getElementById('activityLogAction');
        if (activityLogActionEl) {
            activityLogActionEl.addEventListener('click', async (event) => {
                event.preventDefault();
                await openActivityLogModal();
            });
        }
    }

    async function openActivityLogModal() {
        const modalEl = document.getElementById('activityLogModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return;
        }

        // Set inert on the modal to prevent focus conflicts
        modalEl.inert = false;
        
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
            focus: false
        });
        modal.show();

        await loadActivityLogs();
        
        // Add inert management for future shows/hides
        if (!modalEl._inertManaged) {
            modalEl.addEventListener('hide.bs.modal', () => {
                modalEl.inert = true;
            });
            modalEl.addEventListener('show.bs.modal', () => {
                modalEl.inert = false;
            });
            modalEl._inertManaged = true;
        }
    }

    async function loadActivityLogs() {
        const tbody = document.getElementById('activityLogTableBody');
        if (!tbody) {
            return;
        }

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Loading activity logs...</td></tr>';

        try {
            const response = await fetch(`${config.baseApiUrl}/admin-activity-log.php?limit=20`, {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const payload = await response.json();
            if (payload.status !== 'success') {
                throw new Error(payload.message || 'Failed to fetch activity logs');
            }

            renderActivityLogRows(payload?.data?.logs || []);
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Unable to load activity logs</td></tr>';
            console.error('Error loading activity logs:', error);
        }
    }

    function renderActivityLogRows(logs) {
        const tbody = document.getElementById('activityLogTableBody');
        if (!tbody) {
            return;
        }

        if (!Array.isArray(logs) || logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">No activity logs found</td></tr>';
            return;
        }

        tbody.innerHTML = '';

        logs.forEach((log) => {
            const tr = document.createElement('tr');
            const targetText = log.target_patient_id === null ? '—' : String(log.target_patient_id);

            tr.innerHTML = `
                <td>${log.actor_role || '—'}</td>
                <td>${log.action || '—'}</td>
                <td>${targetText}</td>
                <td>${log.created_at || '—'}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    /**
     * Load dashboard statistics
     */
    async function loadDashboardData() {
        try {
            const response = await fetch(`${config.baseApiUrl}/admin-dashboard.php`);
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                updateDashboardStats(data.data.stats);
                updateMetrics(data.data.metrics);
            } else {
                showErrorNotification('Failed to load dashboard data');
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
            showErrorNotification('Unable to fetch dashboard statistics');
        }
    }

    /**
     * Update dashboard stat cards
     */
    function updateDashboardStats(stats) {
        const monthlyStatusCard = document.querySelector('[data-stat="monthly-status"]');
        const monthlyScheduleCard = document.querySelector('[data-stat="monthly-schedule"]');
        const usersCard = document.querySelector('[data-stat="users"]');

        if (monthlyStatusCard) {
            monthlyStatusCard.querySelector('.stat-value').textContent = 
                stats.total_clinics.toLocaleString();
            monthlyStatusCard.querySelector('.stat-change').textContent = 
                `${stats.verified_clinics} verified clinics`;
        }

        if (monthlyScheduleCard) {
            monthlyScheduleCard.querySelector('.stat-value').textContent = 
                stats.total_appointments.toLocaleString();
            monthlyScheduleCard.querySelector('.stat-change').textContent = 
                'Total appointments';
        }

        if (usersCard) {
            usersCard.querySelector('.stat-value').textContent = 
                stats.total_patients.toLocaleString();
            usersCard.querySelector('.stat-change').textContent = 
                `${stats.verified_patients} verified`;
        }
    }

    /**
     * Update growth metrics
     */
    function updateMetrics(metrics) {
        // This can be extended to update additional metric displays
        console.log('Metrics updated:', metrics);
    }

    /**
     * Load clinics list and populate table
     */
    async function loadClinicsList() {
        try {
            const response = await fetch(`${config.baseApiUrl}/admin-clinics.php?action=list&limit=10`);
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                renderClinicsTable(data.data.clinics);
            } else {
                showErrorNotification('Failed to load clinics list');
            }
        } catch (error) {
            console.error('Error loading clinics:', error);
            showErrorNotification('Unable to fetch clinics');
        }
    }

    /**
     * Render clinics table body
     */
    function renderClinicsTable(clinics) {
        const tableBody = document.querySelector('table tbody');
        
        if (!tableBody) {
            console.warn('Table body not found');
            return;
        }

        tableBody.innerHTML = '';

        if (clinics.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4">No clinics found</td></tr>';
            return;
        }

        clinics.forEach(clinic => {
            const row = createClinicRow(clinic);
            tableBody.appendChild(row);
        });
    }

    /**
     * Create a table row element for a clinic
     */
    function createClinicRow(clinic) {
        const row = document.createElement('tr');
        
        const statusBadgeClass = clinic.status === 'verified' 
            ? 'badge-gradient-success' 
            : 'badge-gradient-warning';
        
        const statusText = clinic.status === 'verified' ? 'VERIFIED' : 'PENDING';

        row.innerHTML = `
            <td>
                <span class="text-muted">${clinic.id}</span>
            </td>
            <td>
                <span class="font-weight-medium">${clinic.clinic_name}</span>
                <br/>
                <small class="text-muted">${clinic.email}</small>
            </td>
            <td>
                <label class="badge ${statusBadgeClass}">${statusText}</label>
            </td>
            <td>
                <small class="text-muted">${clinic.created_at}</small>
            </td>
        `;

        return row;
    }

    /**
     * Setup auto-refresh of dashboard
     */
    function setupAutoRefresh() {
        refreshTimer = setInterval(() => {
            loadDashboardData();
            loadClinicsList();
            loadMessages();
            loadNotifications();
        }, config.refreshInterval);
    }

    /**
     * Load messages from API
     */
    async function loadMessages() {
        try {
            const response = await fetch(`${config.baseApiUrl}/admin-messages.php?limit=5`);
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                renderMessages(data.messages, data.unread_count);
            } else {
                showMessagesError();
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            showMessagesError();
        }
    }

    /**
     * Render messages in dropdown
     */
    function renderMessages(messages, unreadCount) {
        const container = document.getElementById('messagesContainer');
        const badge = document.getElementById('messageCountBadge');
        const footer = document.getElementById('messagesCountFooter');
        const markAllLink = document.getElementById('markAllMessagesRead');

        if (!container) {
            console.warn('Messages container not found');
            return;
        }

        // Update badge
        if (badge) {
            if (unreadCount > 0) {
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }

        // Show/hide "Mark all as read" link
        if (markAllLink) {
            if (unreadCount > 0) {
                markAllLink.style.display = '';
                // Remove old event listener and add new one
                markAllLink.replaceWith(markAllLink.cloneNode(true));
                const newMarkAllLink = document.getElementById('markAllMessagesRead');
                newMarkAllLink.addEventListener('click', async (e) => {
                    e.preventDefault();
                    await markAllMessagesAsRead();
                });
            } else {
                markAllLink.style.display = 'none';
            }
        }

        // Update footer
        if (footer) {
            const msgText = unreadCount === 1 ? 'new message' : 'new messages';
            footer.textContent = `${unreadCount} ${msgText}`;
        }

        // Clear loading state
        container.innerHTML = '';

        if (messages.length === 0) {
            container.innerHTML = `
                <div class="text-center p-3">
                    <p class="text-muted mb-0">No messages</p>
                </div>
            `;
            return;
        }

        // Render each message
        messages.forEach((message, index) => {
            if (index > 0) {
                const divider = document.createElement('div');
                divider.className = 'dropdown-divider';
                container.appendChild(divider);
            }

            const messageItem = createMessageItem(message);
            container.appendChild(messageItem);
        });
    }

    /**
     * Create a message item element
     */
    function createMessageItem(message) {
        const item = document.createElement('a');
        item.className = 'dropdown-item preview-item';
        item.href = '#';
        item.setAttribute('data-created-at', message.created_at);
        
        // Add unread styling
        if (!message.is_read) {
            item.style.backgroundColor = '#f8f9fa';
        }
        
        // Get sender initial
        const initial = message.sender_name.charAt(0).toUpperCase();
        
        // Determine badge color based on sender type
        const badgeColorMap = {
            'system': 'bg-primary',
            'admin': 'bg-success',
            'superadmin': 'bg-info',
            'clinic': 'bg-warning'
        };
        const badgeColor = badgeColorMap[message.sender_type] || 'bg-secondary';

        item.innerHTML = `
            <div class="preview-thumbnail">
                <div class="preview-icon ${badgeColor}">
                    <span class="text-white font-weight-bold">${initial}</span>
                </div>
            </div>
            <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                <h6 class="preview-subject ellipsis mb-1 font-weight-normal">
                    ${escapeHtml(message.subject)}
                </h6>
                <p class="text-gray mb-0 update-time">${calculateTimeAgo(message.created_at)}</p>
            </div>
        `;

        // Add click handler to show message details
        item.addEventListener('click', (e) => {
            e.preventDefault();
            showMessageDetails(message);
        });

        return item;
    }

    /**
     * Show messages error state
     */
    function showMessagesError() {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center p-3">
                    <p class="text-danger mb-0 small">Failed to load messages</p>
                </div>
            `;
        }
    }

    /**
     * Load notifications from API
     */
    async function loadNotifications() {
        try {
            const response = await fetch(`${config.baseApiUrl}/admin-notifications.php?limit=5`);
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                renderNotifications(data.notifications, data.unread_count);
            } else {
                showNotificationsError();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            showNotificationsError();
        }
    }

    /**
     * Render notifications in dropdown
     */
    function renderNotifications(notifications, unreadCount) {
        const container = document.getElementById('notificationsContainer');
        const badge = document.getElementById('notificationCountBadge');
        const markAllLink = document.getElementById('markAllNotificationsRead');

        if (!container) {
            console.warn('Notifications container not found');
            return;
        }

        // Update badge
        if (badge) {
            if (unreadCount > 0) {
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }

        // Show/hide "Mark all as read" link
        if (markAllLink) {
            if (unreadCount > 0) {
                markAllLink.style.display = '';
                // Remove old event listeners by cloning the element
                const newMarkAllLink = markAllLink.cloneNode(true);
                markAllLink.parentNode.replaceChild(newMarkAllLink, markAllLink);
                // Add new event listener
                newMarkAllLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    markAllNotificationsAsRead();
                });
            } else {
                markAllLink.style.display = 'none';
            }
        }

        // Clear loading state
        container.innerHTML = '';

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="text-center p-3">
                    <p class="text-muted mb-0">No notifications</p>
                </div>
            `;
            return;
        }

        // Render each notification
        notifications.forEach((notification, index) => {
            if (index > 0) {
                const divider = document.createElement('div');
                divider.className = 'dropdown-divider';
                container.appendChild(divider);
            }

            const notificationItem = createNotificationItem(notification);
            container.appendChild(notificationItem);
        });
    }

    /**
     * Create a notification item element
     */
    function createNotificationItem(notification) {
        const item = document.createElement('a');
        item.className = 'dropdown-item preview-item notification-item';
        item.href = '#';
        item.setAttribute('data-created-at', notification.created_at);

        // Add unread styling
        if (!notification.is_read) {
            item.style.backgroundColor = '#f8f9fa';
        }

        item.innerHTML = `
            <div class="preview-thumbnail">
                <div class="preview-icon ${notification.color_class}">
                    <i class="mdi ${notification.icon}"></i>
                </div>
            </div>
            <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                <h6 class="preview-subject font-weight-normal mb-1">
                    ${escapeHtml(notification.title)}
                </h6>
                <p class="text-gray ellipsis mb-0">${escapeHtml(notification.message)}</p>
            </div>
        `;

        // Add click handler to show notification details
        item.addEventListener('click', (e) => {
            e.preventDefault();
            showNotificationDetails(notification);
        });

        return item;
    }

    /**
     * Show notifications error state
     */
    function showNotificationsError() {
        const container = document.getElementById('notificationsContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center p-3">
                    <p class="text-danger mb-0 small">Failed to load notifications</p>
                </div>
            `;
        }
    }

    /**
     * Show message details in a modal
     */
    function showMessageDetails(message) {
        const modalEl = document.getElementById('detailsModal');
        const modalTitle = document.getElementById('detailsModalLabel');
        const modalHeader = document.getElementById('detailsModalHeader');
        const modalBody = document.getElementById('detailsModalBody');
        
        if (!modalEl || !detailsModalInstance) return;
        
        // Determine badge color based on sender type
        const badgeColorMap = {
            'system': 'badge-primary',
            'admin': 'badge-success',
            'superadmin': 'badge-info',
            'clinic': 'badge-warning'
        };
        const badgeClass = badgeColorMap[message.sender_type] || 'badge-secondary';
        
        // Set modal title
        modalTitle.innerHTML = `<i class="mdi mdi-email-outline me-2"></i>Message`;
        
        // Set modal header background based on sender type
        const headerColorMap = {
            'system': 'bg-primary',
            'admin': 'bg-success',
            'superadmin': 'bg-info',
            'clinic': 'bg-warning'
        };
        const headerColor = headerColorMap[message.sender_type] || 'bg-secondary';
        modalHeader.className = 'modal-header ' + headerColor + ' text-white';
        
        // Set modal body content
        modalBody.innerHTML = `
            <div class="mb-3">
                <h6 class="text-muted mb-1">From</h6>
                <p class="mb-0">
                    <strong>${escapeHtml(message.sender_name)}</strong>
                    <span class="badge ${badgeClass} ms-2">${escapeHtml(message.sender_type)}</span>
                </p>
            </div>
            <div class="mb-3">
                <h6 class="text-muted mb-1">Subject</h6>
                <p class="mb-0"><strong>${escapeHtml(message.subject)}</strong></p>
            </div>
            <div class="mb-3">
                <h6 class="text-muted mb-1">Time</h6>
                <p class="mb-0 update-time">${calculateTimeAgo(message.created_at)}</p>
            </div>
            <div class="mb-3">
                <h6 class="text-muted mb-1">Message</h6>
                <p class="mb-0">${escapeHtml(message.body || 'No message body')}</p>
            </div>
        `;
        
        // Show the modal using the cached instance
        detailsModalInstance.show();
    }

    /**
     * Show notification details in a modal
     */
    function showNotificationDetails(notification) {
        const modalEl = document.getElementById('detailsModal');
        const modalTitle = document.getElementById('detailsModalLabel');
        const modalHeader = document.getElementById('detailsModalHeader');
        const modalBody = document.getElementById('detailsModalBody');
        
        if (!modalEl || !detailsModalInstance) return;
        
        // Determine badge and icon color
        const typeColorMap = {
            'success': 'text-success',
            'info': 'text-info',
            'warning': 'text-warning',
            'danger': 'text-danger'
        };
        const iconColor = typeColorMap[notification.type] || 'text-info';
        
        // Set modal title
        modalTitle.innerHTML = `<i class="mdi mdi-bell-outline me-2"></i>Notification`;
        
        // Set modal header color based on type
        const headerColorMap = {
            'success': 'bg-success',
            'info': 'bg-info',
            'warning': 'bg-warning',
            'danger': 'bg-danger'
        };
        const headerColor = headerColorMap[notification.type] || 'bg-info';
        modalHeader.className = 'modal-header ' + headerColor + ' text-white';
        
        // Set modal body content
        modalBody.innerHTML = `
            <div class="text-center mb-4">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                     style="width: 64px; height: 64px; background-color: ${notification.color_class === 'bg-success' ? '#13DEB9' : notification.color_class === 'bg-warning' ? '#FFAB00' : notification.color_class === 'bg-danger' ? '#FA424A' : '#57B9F1'};">
                    <i class="mdi ${notification.icon} text-white" style="font-size: 32px;"></i>
                </div>
            </div>
            <div class="mb-3 text-center">
                <h5 class="mb-2"><strong>${escapeHtml(notification.title)}</strong></h5>
                <p class="text-muted mb-0 update-time" style="font-size: 14px;">${calculateTimeAgo(notification.created_at)}</p>
            </div>
            <div class="mb-3">
                <p class="mb-0">${escapeHtml(notification.message)}</p>
            </div>
            <div class="text-center">
                <span class="badge badge-${notification.type}">${notification.type.toUpperCase()}</span>
            </div>
        `;
        
        // Show the modal using the cached instance
        detailsModalInstance.show();
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Load charts data from API
     */
    async function loadChartsData() {
        try {
            const response = await fetch(`${config.baseApiUrl}/admin-charts.php`);
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                // Trigger custom event with chart data
                const event = new CustomEvent('updateCharts', { 
                    detail: {
                        monthlyData: data.monthly_data,
                        months: data.months,
                        appointmentStats: data.appointment_stats
                    }
                });
                document.dispatchEvent(event);
            } else {
                console.error('Failed to load charts data');
            }
        } catch (error) {
            console.error('Error loading charts:', error);
        }
    }

    /**     * Calculate "time ago" string from a date string
     */
    function calculateTimeAgo(dateString) {
        const timestamp = new Date(dateString).getTime();
        const now = new Date().getTime();
        const diff = Math.floor((now - timestamp) / 1000); // Difference in seconds
        
        if (diff < 60) {
            return 'Just now';
        } else if (diff < 3600) {
            const mins = Math.floor(diff / 60);
            return mins + ' minute' + (mins > 1 ? 's' : '') + ' ago';
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        } else if (diff < 604800) {
            const days = Math.floor(diff / 86400);
            return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        } else {
            const date = new Date(timestamp);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }

    /**
     * Update all message timestamps to show real-time "time ago"
     */
    function updateMessageTimestamps() {
        const messageItems = document.querySelectorAll('.dropdown-item.preview-item[data-created-at]');
        messageItems.forEach(item => {
            const createdAt = item.getAttribute('data-created-at');
            const timeElement = item.querySelector('.update-time');
            if (timeElement && createdAt) {
                timeElement.textContent = calculateTimeAgo(createdAt);
            }
        });
    }

    /**
     * Update all notification timestamps to show real-time "time ago"
     */
    function updateNotificationTimestamps() {
        const notificationItems = document.querySelectorAll('.notification-item[data-created-at]');
        notificationItems.forEach(item => {
            const createdAt = item.getAttribute('data-created-at');
            const timeElement = item.querySelector('.update-time');
            if (timeElement && createdAt) {
                timeElement.textContent = calculateTimeAgo(createdAt);
            }
        });
    }

    /**
     * Setup real-time timestamp updates
     */
    function setupTimestampUpdates() {
        // Update timestamps every 60 seconds
        setInterval(() => {
            updateMessageTimestamps();
            updateNotificationTimestamps();
        }, 60000);
    }

    /**     * Setup auto-refresh of dashboard
     */
    function setupAutoRefresh() {
        refreshTimer = setInterval(() => {
            loadDashboardData();
            loadClinicsList();
            loadMessages();
            loadNotifications();
            loadChartsData();
        }, config.refreshInterval);
    }

    /**
     * Stop auto-refresh
     */
    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    /**
     * Show error notification
     */
    function showErrorNotification(message) {
        // Create a toast-like notification
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const contentWrapper = document.querySelector('.content-wrapper');
        if (contentWrapper) {
            contentWrapper.insertBefore(alertDiv, contentWrapper.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    }

    /**
     * Show alert notification with custom type
     * @param {string} message - The message to display
     * @param {string} type - Bootstrap alert type (success, danger, warning, info)
     */
    function showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const contentWrapper = document.querySelector('.content-wrapper');
        if (contentWrapper) {
            contentWrapper.insertBefore(alertDiv, contentWrapper.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    }

    /**
     * Mark all messages as read
     */
    async function markAllMessagesAsRead() {
        try {
            const response = await fetch('../auth/api/mark-messages-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to mark messages as read');
            }

            const data = await response.json();

            if (data.status === 'success') {
                // Reload messages to reflect changes
                await loadMessages();
                showAlert(`Marked ${data.marked_count} message(s) as read`);
            } else {
                showAlert('Error marking messages as read', 'danger');
            }
        } catch (error) {
            console.error('Error marking messages as read:', error);
            showAlert('Failed to mark messages as read', 'danger');
        }
    }

    /**
     * Mark all notifications as read
     */
    async function markAllNotificationsAsRead() {
        try {
            const response = await fetch('../auth/api/mark-notifications-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to mark notifications as read');
            }

            const data = await response.json();

            if (data.status === 'success') {
                // Reload notifications to reflect changes
                await loadNotifications();
                showAlert(`Marked ${data.marked_count} notification(s) as read`);
            } else {
                showAlert('Error marking notifications as read', 'danger');
            }
        } catch (error) {
            console.error('Error marking notifications as read:', error);
            showAlert('Failed to mark notifications as read', 'danger');
        }
    }

    /**
     * Public API
     */
    return {
        init,
        refresh: loadDashboardData,
        refreshClinics: loadClinicsList,
        refreshMessages: loadMessages,
        refreshNotifications: loadNotifications,
        destroy: stopAutoRefresh
    };
})();

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    AdminDashboard.init();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    AdminDashboard.destroy();
});
