<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title is set dynamically in index.php -->
    <!-- Common styles are loaded in index.php -->
    <!-- Dashboard specific styles (including EasyMDE) are loaded in index.php if $url === '/dashboard' -->
</head>
<body>
    <div class="dashboard-container">
        <h1>Dashboard</h1>
        
        <div id="messageAreaGlobal" class="message-area" style="display: none;"></div>

        <!-- Moderator Management Section -->
        <section id="moderatorManagementSection">
            <h2>Moderator Management</h2>
            <div id="messageAreaModerator" style="display: none;"></div>
            <form id="addModeratorForm">
                <label for="discordIdInput">Discord User ID:</label>
                <input type="text" id="discordIdInput" name="discord_id" placeholder="Enter Discord User ID" required>
                <button type="submit">Add Moderator</button>
            </form>
            <div id="moderatorListContainer"></div>
        </section>

        <!-- Guide Management Section -->
        <section id="guideManagementSection">
            <h2>Guide Management (My Guides)</h2>
            <button id="showCreateGuideFormBtn">Create New Guide</button>
            <div id="guideEditorContainer" style="display: none; margin-top: 20px;">
                <form id="guideForm">
                    <input type="hidden" id="guideIdInput">
                    <div>
                        <label for="guideTitleInput">Guide Title:</label>
                        <input type="text" id="guideTitleInput" placeholder="Enter guide title" required>
                    </div>
                    <div>
                        <label for="guideMarkdownEditor">Guide Content (Markdown):</label>
                        <textarea id="guideMarkdownEditor"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="saveDraftBtn">Save Draft</button>
                        <button type="button" id="submitReviewBtn">Submit for Review</button>
                    </div>
                </form>
            </div>
            <h3>Your Guides</h3>
            <div id="userGuidesList"></div>
        </section>

        <!-- Guides Pending Review Section -->
        <section id="guidesPendingReviewSection">
            <h2>Guides Pending Review</h2>
            <div id="reviewGuidesListContainer">
                <!-- Guides for review will be listed here -->
            </div>
        </section>

        <!-- Guide Preview Modal -->
        <div id="guidePreviewModal" class="modal">
            <div class="modal-content">
                <span class="modal-close-btn" id="closeGuidePreviewModal">&times;</span>
                <h3 id="guidePreviewTitle"></h3>
                <div id="guidePreviewContent"></div>
            </div>
        </div>

    </div>

    <script>
        // Common DOM Elements
        const messageAreaGlobal = document.getElementById('messageAreaGlobal');

        // --- Moderator Management ---
        const moderatorControllerUrl = '/src/Controllers/ModeratorController.php';
        const addModeratorForm = document.getElementById('addModeratorForm');
        const discordIdInput = document.getElementById('discordIdInput');
        const moderatorListContainer = document.getElementById('moderatorListContainer');
        const messageAreaModerator = document.getElementById('messageAreaModerator');

        function showModeratorMessage(message, type = 'success') { /* ... existing code ... */ }
        async function fetchModerators() { /* ... existing code ... */ }
        if(addModeratorForm) addModeratorForm.addEventListener('submit', async (event) => { /* ... existing code ... */ });
        if(moderatorListContainer) moderatorListContainer.addEventListener('click', async (event) => { /* ... existing code ... */ });
        
        // --- Guide Management ---
        const guideControllerUrl = '/src/Controllers/GuideController.php';
        const showCreateGuideFormBtn = document.getElementById('showCreateGuideFormBtn');
        const guideEditorContainer = document.getElementById('guideEditorContainer');
        const guideForm = document.getElementById('guideForm');
        const guideIdInput = document.getElementById('guideIdInput');
        const guideTitleInput = document.getElementById('guideTitleInput');
        const guideMarkdownEditorEl = document.getElementById('guideMarkdownEditor');
        const saveDraftBtn = document.getElementById('saveDraftBtn');
        const submitReviewBtn = document.getElementById('submitReviewBtn');
        const userGuidesList = document.getElementById('userGuidesList');
        let easyMDE;

        function showGlobalMessage(message, type = 'success') { /* ... existing code ... */ }
        function initializeEditor(initialValue = '') { /* ... existing code ... */ }
        if(showCreateGuideFormBtn) showCreateGuideFormBtn.addEventListener('click', () => { /* ... existing code ... */ });
        async function saveOrUpdateGuide() { /* ... existing code ... */ }
        if(saveDraftBtn) saveDraftBtn.addEventListener('click', saveOrUpdateGuide);
        if(submitReviewBtn) submitReviewBtn.addEventListener('click', async () => { /* ... existing code ... */ });
        async function fetchUserGuides() { /* ... existing code ... */ }
        if(userGuidesList) userGuidesList.addEventListener('click', async (event) => { /* ... existing code ... */ });

        // --- Guides Pending Review ---
        const reviewGuidesListContainer = document.getElementById('reviewGuidesListContainer');
        const guidePreviewModal = document.getElementById('guidePreviewModal');
        const closeGuidePreviewModalBtn = document.getElementById('closeGuidePreviewModal');
        const guidePreviewTitle = document.getElementById('guidePreviewTitle');
        const guidePreviewContent = document.getElementById('guidePreviewContent');

        async function fetchGuidesForReview() {
            if (!reviewGuidesListContainer) return;
            reviewGuidesListContainer.innerHTML = '<p>Loading guides for review...</p>';
            try {
                const response = await fetch(`${guideControllerUrl}?action=listGuidesForReview`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();

                if (result.status === 'success' && Array.isArray(result.data)) {
                    reviewGuidesListContainer.innerHTML = '';
                    if (result.data.length === 0) {
                        reviewGuidesListContainer.innerHTML = '<p>No guides are currently pending review.</p>';
                        return;
                    }
                    result.data.forEach(guide => {
                        const item = document.createElement('div');
                        item.classList.add('review-guide-item');
                        item.innerHTML = `
                            <div class="info">
                                <span class="title">${guide.title}</span>
                                <span class="author">by ${guide.author_username || 'Unknown'}</span>
                            </div>
                            <div class="actions">
                                <button class="preview-guide-btn" data-guide-id="${guide.id}">Preview</button>
                                <button class="approve-guide-btn" data-guide-id="${guide.id}">Approve</button>
                                <button class="reject-guide-btn" data-guide-id="${guide.id}">Reject</button>
                            </div>
                        `;
                        reviewGuidesListContainer.appendChild(item);
                    });
                } else {
                    throw new Error(result.message || 'Failed to load guides for review.');
                }
            } catch (error) {
                console.error('Error fetching guides for review:', error);
                reviewGuidesListContainer.innerHTML = `<p class="error-text">Error loading guides: ${error.message}</p>`;
            }
        }

        if (reviewGuidesListContainer) {
            reviewGuidesListContainer.addEventListener('click', async (event) => {
                const target = event.target;
                const guideId = target.dataset.guideId;

                if (!guideId) return;

                let action = '';
                let confirmMessage = '';

                if (target.classList.contains('approve-guide-btn')) {
                    action = 'approveGuide';
                    confirmMessage = 'Are you sure you want to approve this guide?';
                } else if (target.classList.contains('reject-guide-btn')) {
                    action = 'rejectGuide';
                    confirmMessage = 'Are you sure you want to reject this guide?';
                } else if (target.classList.contains('preview-guide-btn')) {
                    // Handle Preview
                    try {
                        const response = await fetch(`${guideControllerUrl}?action=getGuide&data[guide_id]=${guideId}`);
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const result = await response.json();
                        if (result.status === 'success' && result.data) {
                            guidePreviewTitle.textContent = result.data.title;
                            guidePreviewContent.innerHTML = result.data.content_html; // Assuming content_html is safe
                            guidePreviewModal.style.display = 'block';
                        } else {
                            throw new Error(result.message || 'Failed to fetch guide for preview.');
                        }
                    } catch (error) {
                        console.error('Error previewing guide:', error);
                        showGlobalMessage(`Error previewing guide: ${error.message}`, 'error');
                    }
                    return; // Preview action handled, no further processing needed for this click
                } else {
                    return; // Not a button we care about
                }

                if (!confirm(confirmMessage)) return;

                try {
                    const response = await fetch(guideControllerUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: action, data: { guide_id: guideId } })
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        showGlobalMessage(result.message || `Guide ${action.includes('approve') ? 'approved' : 'rejected'} successfully!`, 'success');
                        fetchGuidesForReview(); // Refresh this list
                        if (typeof fetchUserGuides === 'function') fetchUserGuides(); // Refresh user's list as status might change
                    } else {
                        throw new Error(result.message || `Failed to ${action}.`);
                    }
                } catch (error) {
                    console.error(`Error during ${action}:`, error);
                    showGlobalMessage(`Error: ${error.message}`, 'error');
                }
            });
        }
        
        if (closeGuidePreviewModalBtn) {
            closeGuidePreviewModalBtn.onclick = function() {
                guidePreviewModal.style.display = "none";
            }
        }
        // Close modal if user clicks outside of the modal content
        window.onclick = function(event) {
            if (event.target == guidePreviewModal) {
                guidePreviewModal.style.display = "none";
            }
        }


        // --- Existing Moderator Management JS (Copied for brevity, ensure it's complete) ---
        // Function showModeratorMessage, fetchModerators, and event listeners for addModeratorForm, moderatorListContainer
        // (Make sure these are correctly defined in your actual combined script)
        function showModeratorMessage(message, type = 'success') {
            if(!messageAreaModerator) return;
            messageAreaModerator.textContent = message;
            messageAreaModerator.className = type; // 'success' or 'error'
            messageAreaModerator.style.display = 'block';
            setTimeout(() => {
                messageAreaModerator.style.display = 'none';
            }, 5000);
        }

        async function fetchModerators() {
            if(!moderatorListContainer) return;
            moderatorListContainer.innerHTML = '<p class="loading-text">Loading moderators...</p>';
            try {
                const response = await fetch(`${moderatorControllerUrl}?action=listModerators`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'success' && Array.isArray(result.data)) {
                    moderatorListContainer.innerHTML = '';
                    if (result.data.length === 0) {
                        moderatorListContainer.innerHTML = '<p>No moderators found.</p>'; return;
                    }
                    result.data.forEach(mod => {
                        const item = document.createElement('div'); item.classList.add('moderator-item');
                        const avatar = mod.discord_avatar_url || '/images/logo_green.png';
                        item.innerHTML = `<img src="${avatar}" alt="Avatar"><span>${mod.discord_username} (ID: ${mod.discord_id})</span><div class="actions"><button class="refresh-mod-btn" data-id="${mod.id}">Refresh</button><button class="remove-mod-btn" data-id="${mod.id}">Remove</button></div>`;
                        moderatorListContainer.appendChild(item);
                    });
                } else { throw new Error(result.message || 'Failed to load moderators.'); }
            } catch (error) {
                console.error('Error fetching moderators:', error);
                moderatorListContainer.innerHTML = `<p class="error-text">Error: ${error.message}</p>`;
                showModeratorMessage(`Error: ${error.message}`, 'error');
            }
        }
        if(addModeratorForm) { 
            addModeratorForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const discordId = discordIdInput.value.trim();
                if (!discordId) { showModeratorMessage('Discord ID required.', 'error'); return; }
                try {
                    const response = await fetch(moderatorControllerUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'addModerator', data: { discord_id: discordId } }) });
                    const result = await response.json();
                    if (result.status === 'success') {
                        showModeratorMessage(result.message || 'Moderator added!', 'success');
                        discordIdInput.value = ''; fetchModerators();
                    } else { throw new Error(result.message || 'Failed to add moderator.'); }
                } catch (error) { console.error('Error adding moderator:', error); showModeratorMessage(`Error: ${error.message}`, 'error'); }
            });
        }

        if(moderatorListContainer) {
            moderatorListContainer.addEventListener('click', async (event) => {
                const target = event.target;
                const modId = target.dataset.id;
                if (!modId || (!target.classList.contains('refresh-mod-btn') && !target.classList.contains('remove-mod-btn'))) return;
                const action = target.classList.contains('refresh-mod-btn') ? 'refreshModeratorProfile' : 'removeModerator';
                if (!confirm(`Are you sure you want to ${action === 'removeModerator' ? 'remove' : 'refresh'} this moderator?`)) return;
                try {
                    const response = await fetch(moderatorControllerUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: action, data: { id: modId } }) });
                    const result = await response.json();
                    if (result.status === 'success') {
                        showModeratorMessage(result.message || 'Action successful!', 'success'); fetchModerators();
                    } else { throw new Error(result.message || 'Action failed.'); }
                } catch (error) { console.error(`Error during ${action}:`, error); showModeratorMessage(`Error: ${error.message}`, 'error'); }
            });
        }

        // --- Existing Guide Management JS (Copied for brevity) ---
        function showGlobalMessage(message, type = 'success') {
            if(!messageAreaGlobal) return;
            messageAreaGlobal.textContent = message;
            messageAreaGlobal.className = `message-area ${type}`; 
            messageAreaGlobal.style.display = 'block';
            setTimeout(() => {
                messageAreaGlobal.style.display = 'none';
            }, 5000);
        }
        function initializeEditor(initialValue = '') {
            if (guideMarkdownEditorEl) {
                if (easyMDE) { easyMDE.toTextArea(); easyMDE = null; }
                easyMDE = new EasyMDE({ element: guideMarkdownEditorEl, initialValue: initialValue, spellChecker: false, placeholder: "Start writing your guide here...", minHeight: "200px" });
            }
        }
        if(showCreateGuideFormBtn) {
            showCreateGuideFormBtn.addEventListener('click', () => {
                guideEditorContainer.style.display = 'block';
                guideIdInput.value = ''; guideTitleInput.value = '';
                if(easyMDE) easyMDE.value(''); else initializeEditor();
                guideTitleInput.focus();
                if(submitReviewBtn) submitReviewBtn.style.display = 'none';
            });
        }
        async function saveOrUpdateGuide() {
            const title = guideTitleInput.value.trim();
            const content_markdown = easyMDE.value();
            const guide_id = guideIdInput.value;
            if (!title || !content_markdown) { showGlobalMessage('Title and content are required.', 'error'); return; }
            const action = guide_id ? 'updateGuide' : 'createGuide';
            const payload = { action: action, data: { title: title, content_markdown: content_markdown } };
            if (guide_id) payload.data.guide_id = guide_id;
            try {
                const response = await fetch(guideControllerUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const result = await response.json();
                if (result.status === 'success' && result.data) {
                    guideIdInput.value = result.data.id;
                    showGlobalMessage(result.message || 'Draft saved!', 'success');
                    fetchUserGuides();
                    if(submitReviewBtn) submitReviewBtn.style.display = 'inline-block';
                } else { throw new Error(result.message || 'Failed to save draft.'); }
            } catch (error) { console.error('Error saving draft:', error); showGlobalMessage(`Error: ${error.message}`, 'error'); }
        }
        if(saveDraftBtn) saveDraftBtn.addEventListener('click', saveOrUpdateGuide);

        if(submitReviewBtn) {
            submitReviewBtn.addEventListener('click', async () => {
                const guide_id = guideIdInput.value;
                if (!guide_id) { showGlobalMessage('Save draft before submitting.', 'error'); return; }
                if (!confirm(`Submit this guide for review?`)) return;
                try {
                    const response = await fetch(guideControllerUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'submitForReview', data: { guide_id: guide_id } }) });
                    const result = await response.json();
                    if (result.status === 'success') {
                        showGlobalMessage(result.message || 'Submitted for review!', 'success');
                        fetchUserGuides();
                        guideEditorContainer.style.display = 'none';
                    } else { throw new Error(result.message || 'Failed to submit.'); }
                } catch (error) { console.error('Error submitting:', error); showGlobalMessage(`Error: ${error.message}`, 'error'); }
            });
        }
        async function fetchUserGuides() {
            if(!userGuidesList) return;
            userGuidesList.innerHTML = '<p>Loading your guides...</p>';
            try {
                const response = await fetch(`${guideControllerUrl}?action=listUserGuides`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.status === 'success' && Array.isArray(result.data)) {
                    userGuidesList.innerHTML = '';
                    if (result.data.length === 0) { userGuidesList.innerHTML = '<p>No guides created yet.</p>'; return; }
                    result.data.forEach(guide => {
                        const item = document.createElement('div'); item.classList.add('user-guide-item');
                        item.innerHTML = `<span class="title">${guide.title}</span><span class="status status-${guide.status.toLowerCase().replace(/ /g, '_')}">${guide.status.replace(/_/g, ' ')}</span><div class="actions"><button class="edit-guide-btn" data-guide-id="${guide.id}">Edit</button></div>`;
                        userGuidesList.appendChild(item);
                    });
                } else { throw new Error(result.message || 'Failed to load your guides.'); }
            } catch (error) { console.error('Error fetching user guides:', error); userGuidesList.innerHTML = `<p class="error-text">Error: ${error.message}</p>`; }
        }
        if(userGuidesList) {
            userGuidesList.addEventListener('click', async (event) => {
                if (event.target.classList.contains('edit-guide-btn')) {
                    const guideId = event.target.dataset.guideId;
                    try {
                        const response = await fetch(`${guideControllerUrl}?action=getGuide&data[guide_id]=${guideId}`);
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const result = await response.json();
                        if (result.status === 'success' && result.data) {
                            guideIdInput.value = result.data.id;
                            guideTitleInput.value = result.data.title;
                            if(easyMDE) easyMDE.value(result.data.content_markdown); else initializeEditor(result.data.content_markdown);
                            guideEditorContainer.style.display = 'block';
                            guideTitleInput.focus();
                            if(submitReviewBtn) submitReviewBtn.style.display = (result.data.status === 'draft' || result.data.status === 'rejected') ? 'inline-block' : 'none';
                        } else { throw new Error(result.message || 'Failed to fetch guide.'); }
                    } catch (error) { console.error('Error fetching guide for edit:', error); showGlobalMessage(`Error: ${error.message}`, 'error'); }
                }
            });
        }


        // Initial loads
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof fetchModerators === 'function') fetchModerators();
            if (typeof fetchUserGuides === 'function') fetchUserGuides();
            if (typeof fetchGuidesForReview === 'function') fetchGuidesForReview(); // New function call

            if (guideEditorContainer && submitReviewBtn) { 
                 submitReviewBtn.style.display = 'none';
            }
        });
    </script>
</body>
