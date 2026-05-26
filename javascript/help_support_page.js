/**
 * Help & Support full page (teacher sidebar).
 *
 * @package theme_remui_kids
 */

(function() {
    'use strict';

    function initHelpSupportPage() {
    const page = document.getElementById('helpSupportPage');
    if (!page) {
        return;
    }

    const content = document.getElementById('helpPageMainContent');
    if (!content) {
        return;
    }

    const rawTicket = page.getAttribute('data-ticket-id') || '';
    let currentTicketId = rawTicket && rawTicket !== '0' ? rawTicket : null;
    let currentView = page.getAttribute('data-initial-view') || 'new';
    let selectedFiles = [];

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function pageNavButtons() {
        const root = page.closest('.help-support-page') || page;
        return root.querySelectorAll('.help-nav-btn');
    }

    function switchView(view) {
        currentView = view;
        pageNavButtons().forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-view') === view);
        });

        if (view === 'new') {
            renderNewTicketForm(content);
        } else if (view === 'list') {
            loadTicketsList(content);
        } else if (view === 'detail' && currentTicketId) {
            loadTicketDetail(content, currentTicketId);
        } else {
            switchView('new');
        }
    }

    function renderNewTicketForm(container) {
        container.innerHTML = `
            <div class="help-form">
                <h3 style="margin-top: 0; color: #1f2937;">Create New Support Ticket</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Need help with a bug, have a question, or want to suggest a feature? We're here to help!
                </p>
                <div class="help-form-group">
                    <label class="help-form-label">Category <span style="color: #ef4444;">*</span></label>
                    <select class="help-form-select" id="helpPageCategory" required>
                        <option value="">Select a category</option>
                        <option value="bug">Bug Report</option>
                        <option value="query">Question/Query</option>
                        <option value="feature">Feature Request</option>
                        <option value="general">General Support</option>
                    </select>
                </div>
                <div class="help-form-group">
                    <label class="help-form-label">Subject <span style="color: #ef4444;">*</span></label>
                    <input type="text" class="help-form-input" id="helpPageSubject" placeholder="Brief summary of your issue" maxlength="255" required />
                </div>
                <div class="help-form-group">
                    <label class="help-form-label">Description <span style="color: #ef4444;">*</span></label>
                    <textarea class="help-form-textarea" id="helpPageDescription" placeholder="Please provide detailed information about your issue..." required></textarea>
                </div>
                <div class="help-form-group">
                    <label class="help-form-label">Priority</label>
                    <select class="help-form-select" id="helpPagePriority">
                        <option value="low">Low</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="help-form-group">
                    <label class="help-form-label">Attachments (Optional)</label>
                    <div class="help-file-upload" id="helpPageFileUpload">
                        <i class="fa fa-cloud-upload" style="font-size: 32px; color: #9ca3af; margin-bottom: 10px;"></i>
                        <p style="margin: 0; color: #6b7280;"><strong>Click to upload</strong> or drag and drop</p>
                        <input type="file" class="help-file-input" id="helpPageFileInput" multiple accept="image/*,.pdf,.doc,.docx,.txt" />
                    </div>
                    <div class="help-file-list" id="helpPageFileList"></div>
                </div>
                <button class="help-submit-btn" id="helpPageSubmitBtn" type="button">
                    <i class="fa fa-paper-plane"></i> Submit Ticket
                </button>
            </div>`;
        attachFormListeners();
    }

    function attachFormListeners() {
        const fileUploadArea = document.getElementById('helpPageFileUpload');
        const fileInput = document.getElementById('helpPageFileInput');
        if (!fileUploadArea || !fileInput) {
            return;
        }

        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        fileInput.addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        fileUploadArea.addEventListener('dragleave', function() {
            fileUploadArea.classList.remove('dragover');
        });
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        document.getElementById('helpPageSubmitBtn').addEventListener('click', submitTicket);
    }

    function handleFiles(files) {
        Array.from(files).forEach(function(file) {
            if (file.size > 10 * 1024 * 1024) {
                window.alert('File ' + file.name + ' is too large. Maximum size is 10MB.');
                return;
            }
            selectedFiles.push(file);
        });
        renderFileList();
    }

    function renderFileList() {
        const fileList = document.getElementById('helpPageFileList');
        if (!fileList) {
            return;
        }
        fileList.innerHTML = '';
        selectedFiles.forEach(function(file, index) {
            const fileItem = document.createElement('div');
            fileItem.className = 'help-file-item';
            fileItem.innerHTML = '<i class="fa fa-file"></i><span></span><button type="button" class="help-file-remove" data-index="' + index + '"><i class="fa fa-times"></i></button>';
            fileItem.querySelector('span').textContent = file.name;
            fileList.appendChild(fileItem);
        });
        fileList.querySelectorAll('.help-file-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                selectedFiles.splice(parseInt(btn.getAttribute('data-index'), 10), 1);
                renderFileList();
            });
        });
    }

    function submitTicket() {
        const category = document.getElementById('helpPageCategory').value;
        const subject = document.getElementById('helpPageSubject').value;
        const description = document.getElementById('helpPageDescription').value;
        const priority = document.getElementById('helpPagePriority').value;

        if (!category || !subject || !description) {
            window.alert('Please fill in all required fields.');
            return;
        }

        const submitBtn = document.getElementById('helpPageSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';

        const formData = new FormData();
        formData.append('action', 'create_ticket');
        formData.append('category', category);
        formData.append('subject', subject);
        formData.append('description', description);
        formData.append('priority', priority);
        formData.append('sesskey', M.cfg.sesskey);
        selectedFiles.forEach(function(file) {
            formData.append('files[]', file);
        });

        fetch(M.cfg.wwwroot + '/theme/remui_kids/ajax/help_tickets.php', {
            method: 'POST',
            body: formData
        })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    selectedFiles = [];
                    currentTicketId = String(data.ticketid);
                    switchView('list');
                } else {
                    window.alert('Error: ' + (data.message || 'Failed to create ticket'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Ticket';
                }
            })
            .catch(function() {
                window.alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Ticket';
            });
    }

    function loadTicketsList(container) {
        container.innerHTML = '<div class="help-loading"><div class="help-spinner"></div></div>';
        fetch(M.cfg.wwwroot + '/theme/remui_kids/ajax/help_tickets.php?action=list_tickets&sesskey=' + M.cfg.sesskey)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderTicketsList(container, data.tickets);
                } else {
                    container.innerHTML = '<div class="help-empty-state"><p>Error loading tickets</p></div>';
                }
            })
            .catch(function() {
                container.innerHTML = '<div class="help-empty-state"><p>Error loading tickets</p></div>';
            });
    }

    function renderTicketsList(container, tickets) {
        if (!tickets || !tickets.length) {
            container.innerHTML = '<div class="help-empty-state"><div class="help-empty-icon"><i class="fa fa-inbox"></i></div><div class="help-empty-text">No tickets yet</div></div>';
            return;
        }

        let html = '<h3 style="margin-top: 0; color: #1f2937;">My Support Tickets</h3><div class="help-tickets-list">';
        tickets.forEach(function(ticket) {
            const hasUnread = ticket.unread > 0;
            html += '<div class="help-ticket-card' + (hasUnread ? ' unread' : '') + '" data-ticket-id="' + ticket.id + '">';
            html += '<div class="help-ticket-header"><span class="help-ticket-number">#' + escapeHtml(ticket.ticketnumber) + '</span>';
            html += '<span class="help-ticket-status ' + ticket.status + '">' + escapeHtml(ticket.status.replace('_', ' ')) + '</span></div>';
            html += '<div class="help-ticket-subject">' + escapeHtml(ticket.subject) + '</div>';
            html += '<div class="help-ticket-meta"><span><i class="fa fa-tag"></i> ' + escapeHtml(ticket.category) + '</span>';
            html += '<span><i class="fa fa-clock-o"></i> ' + escapeHtml(ticket.timeago) + '</span></div></div>';
        });
        html += '</div>';
        container.innerHTML = html;

        container.querySelectorAll('.help-ticket-card').forEach(function(card) {
            card.addEventListener('click', function() {
                currentTicketId = card.getAttribute('data-ticket-id');
                switchView('detail');
            });
        });
    }

    function loadTicketDetail(container, ticketId) {
        container.innerHTML = '<div class="help-loading"><div class="help-spinner"></div></div>';
        fetch(M.cfg.wwwroot + '/theme/remui_kids/ajax/help_tickets.php?action=get_ticket&ticket_id=' + ticketId + '&sesskey=' + M.cfg.sesskey)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderTicketDetail(container, data.ticket, data.messages);
                    markTicketAsRead(ticketId);
                } else {
                    container.innerHTML = '<div class="help-empty-state"><p>Error loading ticket</p></div>';
                }
            })
            .catch(function() {
                container.innerHTML = '<div class="help-empty-state"><p>Error loading ticket</p></div>';
            });
    }

    function renderTicketDetail(container, ticket, messages) {
        let html = '<div class="help-ticket-detail"><div class="help-ticket-detail-header">';
        html += '<button type="button" class="help-back-btn" id="helpPageBackBtn"><i class="fa fa-arrow-left"></i> Back to Tickets</button>';
        html += '<h3 style="margin: 10px 0; color: #1f2937;">' + escapeHtml(ticket.subject) + '</h3></div><div class="help-messages" id="helpPageMessages">';

        messages.forEach(function(msg) {
            const isAdmin = msg.isadmin == 1;
            html += '<div class="help-message' + (isAdmin ? ' admin' : '') + '"><div class="help-message-avatar">';
            html += (msg.username || '?').charAt(0).toUpperCase();
            html += '</div><div class="help-message-content"><div class="help-message-bubble"><p class="help-message-text">';
            html += escapeHtml(msg.message) + '</p></div><div class="help-message-time">' + escapeHtml(msg.timeago) + '</div></div></div>';
        });

        html += '</div><div class="help-reply-form"><textarea class="help-reply-input" id="helpPageReplyInput" placeholder="Type your reply..."></textarea>';
        html += '<div class="help-reply-actions"><button type="button" class="help-reply-btn" id="helpPageReplyBtn"><i class="fa fa-paper-plane"></i> Send Reply</button></div></div></div>';

        container.innerHTML = html;
        document.getElementById('helpPageBackBtn').addEventListener('click', function() {
            switchView('list');
        });
        document.getElementById('helpPageReplyBtn').addEventListener('click', function() {
            sendReply(ticket.id, container);
        });
    }

    function sendReply(ticketId, container) {
        const message = document.getElementById('helpPageReplyInput').value.trim();
        if (!message) {
            window.alert('Please enter a message');
            return;
        }
        const replyBtn = document.getElementById('helpPageReplyBtn');
        replyBtn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'add_message');
        formData.append('ticket_id', ticketId);
        formData.append('message', message);
        formData.append('sesskey', M.cfg.sesskey);

        fetch(M.cfg.wwwroot + '/theme/remui_kids/ajax/help_tickets.php', {
            method: 'POST',
            body: formData
        })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    loadTicketDetail(container, ticketId);
                } else {
                    window.alert('Error: ' + (data.message || 'Failed to send reply'));
                    replyBtn.disabled = false;
                }
            })
            .catch(function() {
                window.alert('An error occurred. Please try again.');
                replyBtn.disabled = false;
            });
    }

    function markTicketAsRead(ticketId) {
        fetch(M.cfg.wwwroot + '/theme/remui_kids/ajax/help_tickets.php?action=mark_read&ticket_id=' + ticketId + '&sesskey=' + M.cfg.sesskey);
    }

    pageNavButtons().forEach(function(btn) {
        btn.addEventListener('click', function() {
            switchView(btn.getAttribute('data-view'));
        });
    });

    if (currentTicketId) {
        switchView('detail');
    } else if (currentView === 'list') {
        switchView('list');
    } else if (!content.querySelector('.help-form')) {
        switchView('new');
    } else {
        attachFormListeners();
    }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHelpSupportPage);
    } else {
        initHelpSupportPage();
    }
})();
