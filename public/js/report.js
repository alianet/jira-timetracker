/* SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski */
/* SPDX-License-Identifier: MIT */

(() => {
    'use strict';

    const dialog = document.getElementById('worklog-dialog');
    const worklogForm = document.getElementById('worklog-form');
    const issueInput = document.getElementById('worklog-issue');
    const dateInput = document.getElementById('worklog-date');
    const idInput = document.getElementById('worklog-id');
    const timeInput = document.getElementById('worklog-time');
    const commentInput = document.getElementById('worklog-comment');
    const worklogList = document.getElementById('worklog-list');
    const listWrapper = document.getElementById('worklog-list-wrapper');
    const newWorklogButton = document.getElementById('worklog-new');
    const mode = document.getElementById('worklog-mode');
    const submitButton = document.getElementById('worklog-submit');
    const title = document.getElementById('worklog-title');
    const summary = document.getElementById('worklog-summary');
    const deleteForm = document.getElementById('worklog-delete-form');
    const deleteIssue = document.getElementById('delete-issue');
    const deleteDate = document.getElementById('delete-date');
    const deleteWorklogId = document.getElementById('delete-worklog-id');
    const dialogKind = document.getElementById('worklog-dialog-kind');
    const editOnlyElements = document.querySelectorAll('.worklog-edit-only');
    const searchForm = document.getElementById('issue-search-form');
    const searchInput = document.getElementById('issue-query');
    const searchResults = document.getElementById('issue-search-results');
    const searchStatus = document.getElementById('issue-search-status');
    const userSearchForm = document.getElementById('user-search-form');
    const userSearchInput = document.getElementById('user-query');
    const userSearchResults = document.getElementById('user-search-results');
    const userSearchStatus = document.getElementById('user-search-status');
    const dayFilterButtons = document.querySelectorAll('.day-filter');
    const taskRows = document.querySelectorAll('.task-row');
    const dayFilterEmpty = document.getElementById('day-filter-empty');
    const defaultWorklogDate = dialog.dataset.defaultWorklogDate;
    const i18n = {
        searching: dialog.dataset.searching,
        usersSearchFailed: dialog.dataset.usersSearchFailed,
        usersSearchEmpty: dialog.dataset.usersSearchEmpty,
        issuesSearchFailed: dialog.dataset.issuesSearchFailed,
        issuesSearchEmpty: dialog.dataset.issuesSearchEmpty,
        addEntry: dialog.dataset.addEntry,
        editEntry: dialog.dataset.editEntry,
        saveChanges: dialog.dataset.saveChanges,
        addInJira: dialog.dataset.addInJira,
        saveInJira: dialog.dataset.saveInJira,
        noComment: dialog.dataset.noComment,
        editButton: dialog.dataset.editButton,
        deleteButton: dialog.dataset.deleteButton,
        deleteConfirm: dialog.dataset.deleteConfirm,
        logTime: dialog.dataset.logTime,
        historyKind: dialog.dataset.historyKind,
        logKind: dialog.dataset.logKind,
    };
    let currentWorklogs = [];
    let currentReadOnly = false;
    let searchController = null;
    let searchTimer = null;
    let userSearchController = null;
    let userSearchTimer = null;

    const parseJsonResponse = async (response, fallbackMessage) => {
        if (response.status === 401) {
            window.location.assign('/');
            return null;
        }

        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error(fallbackMessage);
        }

        if (!response.ok) throw new Error(payload.error || fallbackMessage);

        return payload;
    };

    dayFilterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const shouldActivate = button.getAttribute('aria-pressed') !== 'true';
            let visibleRows = 0;

            dayFilterButtons.forEach((candidate) => candidate.setAttribute(
                'aria-pressed',
                String(shouldActivate && candidate === button),
            ));
            taskRows.forEach((row) => {
                const dayCell = row.querySelector(`[data-day="${button.dataset.day}"]`);
                const visible = !shouldActivate || dayCell?.dataset.hasHours === 'true';
                row.hidden = !visible;
                if (visible) visibleRows += 1;
            });
            if (dayFilterEmpty) dayFilterEmpty.hidden = !shouldActivate || visibleRows > 0;
        });
    });

    const showUserSearchStatus = (message) => {
        userSearchResults.replaceChildren();
        userSearchStatus.textContent = message;
        userSearchStatus.hidden = message === '';
    };

    const searchUsers = async () => {
        const query = userSearchInput.value.trim();
        if (query.length < 2) {
            userSearchController?.abort();
            showUserSearchStatus('');
            return;
        }
        userSearchController?.abort();
        userSearchController = new AbortController();
        showUserSearchStatus(i18n.searching);
        try {
            const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`, {
                headers: {Accept: 'application/json'}, signal: userSearchController.signal,
            });
            const payload = await parseJsonResponse(response, i18n.usersSearchFailed);
            if (payload === null) return;
            userSearchResults.replaceChildren();
            userSearchStatus.hidden = true;
            if (payload.users.length === 0) {
                showUserSearchStatus(i18n.usersSearchEmpty);
                return;
            }
            payload.users.forEach((user) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'user-search-result';
                if (user.avatarUrl) {
                    const avatar = document.createElement('img');
                    avatar.src = user.avatarUrl;
                    avatar.alt = '';
                    button.append(avatar);
                }
                const name = document.createElement('span');
                name.textContent = user.displayName;
                button.append(name);
                button.addEventListener('click', () => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('accountId', user.accountId);
                    url.searchParams.delete('saved');
                    window.location.assign(url);
                });
                userSearchResults.append(button);
            });
        } catch (error) {
            if (error.name !== 'AbortError') showUserSearchStatus(error.message);
        }
    };

    userSearchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        searchUsers();
    });
    userSearchInput.addEventListener('input', () => {
        window.clearTimeout(userSearchTimer);
        userSearchTimer = window.setTimeout(searchUsers, 300);
    });

    document.querySelectorAll('.comment-tag').forEach((button) => {
        button.addEventListener('click', () => {
            const tag = button.dataset.tag || '';
            const comment = commentInput.value.trimEnd();
            commentInput.value = comment === '' ? tag : `${comment}, ${tag}`;
            commentInput.focus();
            commentInput.setSelectionRange(commentInput.value.length, commentInput.value.length);
        });
    });

    const normalizeTimeSpent = (value) => {
        const match = value.trim().match(/^(\d+)(?:[,.](\d+))?\s*h$/i);
        if (!match) return value.trim().replace(/([wdhm])(?=\d)/gi, '$1 ');

        let hours = Number(match[1]);
        const fraction = match[2] || '';
        let minutes = fraction ? Math.round((Number(`0.${fraction}`)) * 60) : 0;
        if (minutes === 60) {
            hours += 1;
            minutes = 0;
        }

        return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
    };

    const addMode = () => {
        worklogForm.action = '/worklogs';
        idInput.value = '';
        timeInput.value = '';
        commentInput.value = '';
        mode.textContent = i18n.addEntry;
        submitButton.textContent = i18n.addInJira;
    };

    const editWorklog = (id) => {
        const worklog = currentWorklogs.find((entry) => String(entry.id) === String(id));
        idInput.value = worklog?.id ?? '';
        worklogForm.action = `/worklogs/${encodeURIComponent(idInput.value)}`;
        timeInput.value = worklog?.timeSpent ?? '';
        commentInput.value = worklog?.comment ?? '';
        mode.textContent = i18n.editEntry;
        submitButton.textContent = i18n.saveChanges;
    };

    const renderWorklogs = () => {
        worklogList.replaceChildren();
        currentWorklogs.forEach((entry) => {
            const item = document.createElement('div');
            item.className = 'worklog-item';
            const details = document.createElement('div');
            const time = document.createElement('strong');
            time.textContent = entry.timeSpent;
            const comment = document.createElement('span');
            comment.textContent = entry.comment || i18n.noComment;
            details.append(time, comment);
            item.append(details);

            if (currentReadOnly) {
                worklogList.append(item);
                return;
            }

            const actions = document.createElement('div');
            actions.className = 'worklog-item-actions';
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'worklog-icon';
            edit.title = i18n.editButton;
            edit.setAttribute('aria-label', i18n.editButton);
            edit.textContent = '✎';
            edit.addEventListener('click', () => {
                editWorklog(entry.id);
                timeInput.focus();
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'worklog-icon worklog-delete';
            remove.title = i18n.deleteButton;
            remove.setAttribute('aria-label', i18n.deleteButton);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                if (!window.confirm(i18n.deleteConfirm)) return;
                deleteIssue.value = issueInput.value;
                deleteDate.value = dateInput.value;
                deleteWorklogId.value = entry.id;
                deleteForm.action = `/worklogs/${encodeURIComponent(deleteWorklogId.value)}/delete`;
                deleteForm.submit();
            });
            actions.append(edit, remove);
            item.append(actions);
            worklogList.append(item);
        });
        listWrapper.hidden = currentWorklogs.length === 0;
    };

    const openWorklogDialog = ({key, summary: issueSummary, date = defaultWorklogDate, worklogs = [], readOnly = false}) => {
        issueInput.value = key;
        dateInput.value = date;
        title.textContent = key;
        summary.textContent = issueSummary;
        currentWorklogs = worklogs;
        currentReadOnly = readOnly;
        dialogKind.textContent = readOnly ? i18n.historyKind : i18n.logKind;
        editOnlyElements.forEach((element) => element.hidden = readOnly);
        renderWorklogs();
        if (!readOnly) addMode();
        dialog.showModal();
        if (!readOnly) timeInput.focus();
    };

    document.querySelectorAll('.worklog-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            openWorklogDialog({
                key: button.dataset.issue,
                summary: button.dataset.summary,
                date: button.dataset.date,
                worklogs: JSON.parse(button.dataset.worklogs || '[]'),
                readOnly: button.dataset.readonly === 'true',
            });
        });
    });

    const showSearchStatus = (message) => {
        searchResults.replaceChildren();
        searchStatus.textContent = message;
        searchStatus.hidden = message === '';
    };

    const searchIssues = async () => {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            searchController?.abort();
            showSearchStatus('');
            return;
        }

        searchController?.abort();
        searchController = new AbortController();
        showSearchStatus(i18n.searching);

        try {
            const response = await fetch(`/api/issues/search?q=${encodeURIComponent(query)}`, {
                headers: {Accept: 'application/json'},
                signal: searchController.signal,
            });
            const payload = await parseJsonResponse(response, i18n.issuesSearchFailed);
            if (payload === null) return;

            searchResults.replaceChildren();
            searchStatus.hidden = true;
            if (payload.issues.length === 0) {
                showSearchStatus(i18n.issuesSearchEmpty);
                return;
            }

            payload.issues.forEach((issue) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'search-result';
                const key = document.createElement('strong');
                key.textContent = issue.key;
                const issueSummary = document.createElement('span');
                issueSummary.textContent = issue.summary;
                const action = document.createElement('b');
                action.textContent = i18n.logTime;
                button.append(key, issueSummary, action);
                button.addEventListener('click', () => openWorklogDialog(issue));
                searchResults.append(button);
            });
        } catch (error) {
            if (error.name !== 'AbortError') showSearchStatus(error.message);
        }
    };

    searchForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        searchIssues();
    });
    searchInput?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(searchIssues, 300);
    });
    newWorklogButton.addEventListener('click', () => {
        addMode();
        timeInput.focus();
    });
    timeInput.addEventListener('blur', () => {
        timeInput.value = normalizeTimeSpent(timeInput.value);
    });
    document.querySelectorAll('.dialog-close, .dialog-cancel').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

})();
