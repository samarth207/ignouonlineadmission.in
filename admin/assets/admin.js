(function () {
    const app = document.querySelector('#app');
    const page = document.body.dataset.page;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    const date = (value) => value ? new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z')).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
    const api = async (action, data) => {
        const options = data ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action, ...data }) } : {};
        const [name, ...queryParts] = action.split('&');
        const query = new URLSearchParams(queryParts.join('&'));
        query.set('action', name);
        const response = await fetch(`api.php${data ? '' : `?${query.toString()}`}`, options);
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
        return result;
    };
    const toast = (message, type = 'success') => {
        const node = document.createElement('div');
        node.className = `toast toast-${type}`;
        node.textContent = message;
        document.querySelector('.toast-region').append(node);
        setTimeout(() => node.remove(), 3600);
    };
    const showError = (error) => toast(error.message || 'Something went wrong.', 'error');
    const confirmAction = (title, message, label = 'Continue') => new Promise((resolve) => {
        const dialog = document.querySelector('#confirm-dialog');
        document.querySelector('#confirm-title').textContent = title;
        document.querySelector('#confirm-message').textContent = message;
        document.querySelector('#confirm-accept').textContent = label;
        dialog.showModal();
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
    });
    const statusBadge = (status) => `<span class="status-badge status-${esc(status)}">${esc(status)}</span>`;
    const emptyState = (title, detail, action = '') => `<div class="empty-state"><span class="empty-mark">＋</span><h3>${title}</h3><p>${detail}</p>${action}</div>`;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="open-menu"]')) document.body.classList.add('menu-open');
        if (event.target.closest('[data-action="close-menu"]')) document.body.classList.remove('menu-open');
    });

    const dashboard = async () => {
        const data = await api('dashboard');
        const cards = [
            ['Total Blogs', Object.values(data.counts).reduce((sum, count) => sum + Number(count), 0), 'All active stories', 'ink'],
            ['Published', data.counts.published || 0, 'Live on the site', 'green'],
            ['Drafts', data.counts.draft || 0, 'Work in progress', 'blue'],
            ['Scheduled', data.counts.scheduled || 0, 'Queued for release', 'amber'],
            ['Pending Review', data.counts.pending || 0, 'Awaiting approval', 'red'],
            ['Total Views', Number(data.views).toLocaleString(), 'Across published posts', 'teal'],
        ];
        app.innerHTML = `<div class="stats-grid">${cards.map(([label, value, sub, color]) => `<article class="stat-card stat-${color}"><div class="stat-heading"><span>${label}</span><i></i></div><strong>${value}</strong><small>${sub}</small></article>`).join('')}</div>
            <div class="dashboard-grid"><section class="panel"><header class="panel-heading"><div><p class="eyebrow">THE NEWSROOM</p><h2>Recently updated</h2></div><a href="index.php?page=blogs" class="text-link">All blogs <span>→</span></a></header>
                ${data.recent.length ? `<div class="table-wrap"><table><thead><tr><th>Title</th><th>Status</th><th>Views</th><th>Updated</th><th></th></tr></thead><tbody>${data.recent.map((item) => `<tr><td><a class="table-title" href="index.php?page=editor&id=${item.id}">${esc(item.title)}</a><small class="table-sub">/blog/${esc(item.slug)}</small></td><td>${statusBadge(item.status)}</td><td>${Number(item.views).toLocaleString()}</td><td>${date(item.updated_at)}</td><td><a class="icon-button" title="Edit" aria-label="Edit ${esc(item.title)}" href="index.php?page=editor&id=${item.id}">↗</a></td></tr>`).join('')}</tbody></table></div>` : emptyState('No stories yet', 'Your updated posts will appear here.', '<a class="button button-primary" href="index.php?page=editor">Create a post</a>')}</section>
                <div class="dashboard-rail"><section class="panel"><header class="panel-heading"><div><p class="eyebrow">GET MOVING</p><h2>Quick actions</h2></div></header><div class="quick-actions"><a href="index.php?page=editor"><span>＋</span><b>New blog post</b><i>→</i></a><a href="index.php?page=blogs&status=pending"><span>◷</span><b>Review pending</b><i>→</i></a><a href="index.php?page=categories"><span>▦</span><b>Manage categories</b><i>→</i></a><a href="index.php?page=authors"><span>♙</span><b>Manage authors</b><i>→</i></a></div></section>
                <section class="panel scheduled-panel"><header class="panel-heading"><div><p class="eyebrow">ON THE CALENDAR</p><h2>Upcoming posts</h2></div></header>${data.upcoming.length ? `<ul class="upcoming-list">${data.upcoming.map((item) => `<li><time>${date(item.publish_date)}</time><a href="index.php?page=editor&id=${item.id}">${esc(item.title)}</a></li>`).join('')}</ul>` : '<p class="muted-copy">Nothing scheduled right now.</p>'}</section></div></div>`;
    };

    const renderBlogs = async (status = 'all', search = '', requestedPage = 1) => {
        const params = new URLSearchParams({ action: 'blogs', status, search, page: String(requestedPage) });
        const response = await fetch(`api.php?${params}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not load blog posts.');
        const tabs = ['all', 'published', 'draft', 'pending', 'scheduled'];
        app.innerHTML = `<div class="list-heading"><div><p class="eyebrow">EDITORIAL CONTENT</p><h2>All Blogs <span class="count-label">${data.total}</span></h2></div><a class="button button-primary" href="index.php?page=editor"><span>＋</span> New Blog</a></div>
            <div class="status-tabs" role="tablist">${tabs.map((tab) => `<button type="button" class="status-tab ${tab === status ? 'selected' : ''}" data-status="${tab}">${tab === 'all' ? 'All' : tab[0].toUpperCase() + tab.slice(1)} <span>${tab === 'all' ? Object.values(data.counts).reduce((sum, count) => sum + Number(count), 0) : Number(data.counts[tab] || 0)}</span></button>`).join('')}</div>
            <section class="panel blogs-panel"><form class="filter-bar" id="blog-filter"><label class="search-field"><span>⌕</span><input name="search" placeholder="Search titles or slugs" value="${esc(search)}"></label><button class="button button-secondary" type="submit">Search</button><button class="button button-quiet" type="reset">Clear</button><span class="filter-spacer"></span><small>${data.total} ${data.total === 1 ? 'story' : 'stories'}</small></form>
            ${data.items.length ? `<div class="table-wrap"><table><thead><tr><th>Title</th><th>Author</th><th>Status</th><th>Published</th><th>Views</th><th>Updated</th><th>Actions</th></tr></thead><tbody>${data.items.map((item) => `<tr><td><a class="table-title" href="index.php?page=editor&id=${item.id}">${esc(item.title)}</a><small class="table-sub">/blog/${esc(item.slug)} · ${item.read_time} min read</small></td><td>${esc(item.author_name || '—')}</td><td>${statusBadge(item.status)}</td><td>${date(item.publish_date)}</td><td>${Number(item.views).toLocaleString()}</td><td>${date(item.updated_at)}</td><td><div class="row-actions"><a class="icon-button" href="index.php?page=editor&id=${item.id}" title="Edit" aria-label="Edit ${esc(item.title)}">✎</a>${item.status === 'published' ? `<a class="icon-button" href="../blog/${encodeURIComponent(item.slug)}" target="_blank" rel="noopener" title="View live" aria-label="View live ${esc(item.title)}">↗</a>` : ''}<button class="icon-button danger-icon" type="button" data-delete-blog="${item.id}" title="Move to deleted" aria-label="Delete ${esc(item.title)}">×</button></div></td></tr>`).join('')}</tbody></table></div><div class="pagination"><span>Page ${data.page} of ${data.pages}</span><div><button class="button button-quiet" data-page="${Math.max(1, data.page - 1)}" ${data.page <= 1 ? 'disabled' : ''}>← Previous</button><button class="button button-quiet" data-page="${Math.min(data.pages, data.page + 1)}" ${data.page >= data.pages ? 'disabled' : ''}>Next →</button></div></div>` : emptyState('No blogs found', search ? 'Try another search or clear the filters.' : 'Start with a draft and publish when it is ready.', '<a class="button button-primary" href="index.php?page=editor">Write a blog</a>')}
            </section>`;
        app.dataset.status = status;
        app.dataset.search = search;
        app.dataset.currentPage = String(data.page);
        document.querySelector('#blog-filter').addEventListener('submit', (event) => { event.preventDefault(); renderBlogs(status, new FormData(event.currentTarget).get('search').toString()); });
        document.querySelector('#blog-filter').addEventListener('reset', (event) => { event.preventDefault(); renderBlogs(status, ''); });
        app.querySelectorAll('[data-status]').forEach((button) => button.addEventListener('click', () => renderBlogs(button.dataset.status, search)));
        app.querySelectorAll('[data-page]').forEach((button) => button.addEventListener('click', () => renderBlogs(status, search, Number(button.dataset.page))));
        app.querySelectorAll('[data-delete-blog]').forEach((button) => button.addEventListener('click', async () => {
            if (!await confirmAction('Move this post to deleted?', 'The post will be removed from the active list.')) return;
            try { await api('delete_blog', { id: Number(button.dataset.deleteBlog) }); toast('Post moved to deleted.'); renderBlogs(status, search, requestedPage); } catch (error) { showError(error); }
        }));
    };

    const slugify = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    const editorPage = async () => {
        const id = Number(document.body.dataset.blogId || 0);
        const [categoryData, authorData, blogData] = await Promise.all([api('categories'), api('authors'), id ? api(`blog&id=${id}`) : Promise.resolve(null)]);
        const item = blogData ? blogData.item : {};
        const selectedCategories = (item.category_ids || []).map(Number);
        const tags = item.tags || [];
        const publication = item.publish_date ? new Date(String(item.publish_date).replace(' ', 'T') + 'Z') : null;
        const localDate = publication ? `${publication.getFullYear()}-${String(publication.getMonth() + 1).padStart(2, '0')}-${String(publication.getDate()).padStart(2, '0')}` : '';
        const localTime = publication ? `${String(publication.getHours()).padStart(2, '0')}:${String(publication.getMinutes()).padStart(2, '0')}` : '';
        const categories = categoryData.items.filter((category) => Number(category.is_active) === 1);
        const authors = authorData.items.filter((author) => Number(author.is_active) === 1);
        app.innerHTML = `<form id="blog-editor" novalidate>
            <div class="editor-actionbar"><a class="back-link" href="index.php?page=blogs">← All Blogs</a><span class="save-state" id="save-state">${item.updated_at ? `Last saved ${date(item.updated_at)}` : 'Unsaved draft'}</span><span class="action-spacer"></span><button class="button button-quiet" type="button" data-save-status="draft">Save Draft</button><button class="button button-secondary" type="button" data-save-status="pending">Submit for Review</button><button class="button button-primary" type="button" data-save-status="published">Publish</button></div>
            <div class="editor-layout"><div class="editor-main-column">
                <section class="panel editor-panel"><header class="panel-heading"><div><p class="eyebrow">01 / STORY</p><h2>Blog title &amp; excerpt</h2></div></header><div class="panel-body"><label class="field-label" for="blog-title">Blog title <em>Required</em></label><input class="text-input title-input" id="blog-title" name="title" maxlength="70" required value="${esc(item.title)}" placeholder="Write a clear, useful headline"><div class="field-meta"><small>Keep it focused and easy to scan.</small><small><b id="title-count">${String(item.title || '').length}</b> / 70</small></div><label class="field-label" for="blog-excerpt">Short description / excerpt <em>Required</em></label><textarea class="text-input excerpt-input" id="blog-excerpt" name="excerpt" maxlength="250" required placeholder="A concise introduction to the story">${esc(item.excerpt)}</textarea><div class="field-meta"><small>Shown in blog listings and search previews.</small><small><b id="excerpt-count">${String(item.excerpt || '').length}</b> / 250</small></div></div></section>
                <section class="panel editor-panel"><header class="panel-heading"><div><p class="eyebrow">02 / ARTWORK</p><h2>Feature image</h2></div><small>Recommended 1200 × 628</small></header><div class="panel-body"><input type="hidden" name="feature_image" id="feature-image" value="${esc(item.feature_image)}"><div class="image-dropzone ${item.feature_image ? 'has-image' : ''}" id="image-dropzone"><input id="image-file" type="file" accept="image/jpeg,image/png,image/webp" hidden><div class="drop-content"><span class="upload-symbol">↑</span><strong>Drop an image here or <button class="text-button" type="button" id="choose-image">browse files</button></strong><small>JPG, PNG, WebP · Up to 5 MB</small></div><div class="image-preview" id="image-preview">${item.feature_image ? `<img src="${esc(item.feature_image)}" alt=""><button type="button" class="icon-button danger-icon" id="remove-image" aria-label="Remove image">×</button>` : ''}</div></div><div class="field-grid"><label><span class="field-label">Image alt text <em>Required</em></span><input class="text-input" name="feature_image_alt" maxlength="250" required value="${esc(item.feature_image_alt)}" placeholder="Describe the image"></label><label><span class="field-label">Image title</span><input class="text-input" name="feature_image_title" maxlength="250" value="${esc(item.feature_image_title)}" placeholder="Optional"></label></div></div></section>
                <section class="panel editor-panel content-editor-panel"><header class="panel-heading"><div><p class="eyebrow">03 / ARTICLE</p><h2>Blog content</h2></div><span class="heading-count"><b id="heading-count">0</b> headings</span></header><div class="panel-body"><textarea id="blog-content">${item.content || ''}</textarea><div class="editor-footer"><span id="read-time">Estimated read: ${item.read_time || 1} min</span><button class="text-button" type="button" id="preview-content">Preview</button></div></div></section>
            </div><aside class="editor-sidebar">
                <section class="panel sidebar-panel"><header class="panel-heading"><div><p class="eyebrow">DISCOVERABILITY</p><h2>SEO &amp; meta</h2></div></header><div class="panel-body"><label class="field-label" for="meta-title">Meta title <em>Required</em></label><input class="text-input" id="meta-title" name="meta_title" maxlength="70" value="${esc(item.meta_title)}" placeholder="Search result title"><div class="meter-caption"><span>Ideal: around 60 characters</span><span><b id="meta-title-count">${String(item.meta_title || '').length}</b> / 70</span></div><div class="meter-track"><i id="meta-title-meter"></i></div><label class="field-label" for="meta-description">Meta description <em>Required</em></label><textarea class="text-input" id="meta-description" name="meta_description" maxlength="250" placeholder="Search result summary">${esc(item.meta_description)}</textarea><div class="meter-caption"><span>Ideal: 200–250 characters</span><span><b id="meta-description-count">${String(item.meta_description || '').length}</b> / 250</span></div><div class="meter-track"><i id="meta-description-meter"></i></div><label class="field-label" for="focus-keyword">Focus keyword <em>Required</em></label><input class="text-input" id="focus-keyword" name="focus_keyword" value="${esc(item.focus_keyword)}" required placeholder="Primary search phrase"><label class="field-label" for="primary-keyword">Primary keyword</label><input class="text-input" id="primary-keyword" name="primary_keyword" value="${esc(item.primary_keyword)}" placeholder="Optional supporting phrase"></div></section>
                <section class="panel sidebar-panel"><header class="panel-heading"><div><p class="eyebrow">PERMALINK</p><h2>URL slug</h2></div></header><div class="panel-body"><div class="slug-field"><span>/blog/</span><input class="text-input" id="blog-slug" name="slug" value="${esc(item.slug)}" placeholder="story-slug"></div><small class="field-hint">Lowercase words separated by hyphens.</small></div></section>
                <section class="panel sidebar-panel"><header class="panel-heading"><div><p class="eyebrow">PUBLICATION</p><h2>Publish settings</h2></div></header><div class="panel-body"><div class="status-select">${['draft','pending','published','scheduled'].map((status) => `<button type="button" class="${(item.status || 'draft') === status ? 'selected' : ''}" data-editor-status="${status}">${status[0].toUpperCase() + status.slice(1)}</button>`).join('')}</div><input type="hidden" name="status" id="publish-status" value="${esc(item.status || 'draft')}"><input type="hidden" name="timezone" value="${esc(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC')}"><div class="schedule-fields" ${item.status === 'scheduled' ? '' : 'hidden'}><label class="field-label" for="publish-date">Publish date</label><input class="text-input" type="date" id="publish-date" name="publish_date" value="${esc(localDate)}" ${item.status === 'scheduled' ? 'required' : ''}><label class="field-label" for="publish-time">Publish time</label><input class="text-input" type="time" id="publish-time" name="publish_time" value="${esc(localTime)}" ${item.status === 'scheduled' ? 'required' : ''}></div>${item.updated_at ? `<p class="last-updated">Last updated ${date(item.updated_at)}</p>` : ''}</div></section>
                <section class="panel sidebar-panel"><header class="panel-heading"><div><p class="eyebrow">ORGANIZATION</p><h2>Categories</h2></div><a class="small-link" href="index.php?page=categories" target="_blank">Add new</a></header><div class="panel-body"><div class="category-options">${categories.length ? categories.map((category) => `<label class="check-option"><input type="checkbox" name="category_ids" value="${category.id}" ${selectedCategories.includes(Number(category.id)) ? 'checked' : ''}><span>${esc(category.name)}</span></label>`).join('') : '<p class="muted-copy">Add a category to continue.</p>'}</div></div></section>
                <section class="panel sidebar-panel"><header class="panel-heading"><div><p class="eyebrow">KEYWORDS</p><h2>Tags</h2></div></header><div class="panel-body"><div class="tag-field" id="tag-field">${tags.map((tag) => `<span class="tag-chip">${esc(tag)}<button type="button" aria-label="Remove ${esc(tag)}">×</button></span>`).join('')}<input id="tag-input" placeholder="Type and press Enter"></div><input type="hidden" id="tags-json" value="${esc(JSON.stringify(tags))}"><small class="field-hint">Press Enter or comma to add a tag.</small></div></section>
                <section class="panel sidebar-panel"><header class="panel-heading"><div><p class="eyebrow">BYLINE</p><h2>Author</h2></div><a class="small-link" href="index.php?page=authors" target="_blank">Add new</a></header><div class="panel-body"><select class="text-input" name="author_id"><option value="">Select an author</option>${authors.map((author) => `<option value="${author.id}" ${Number(item.author_id) === Number(author.id) ? 'selected' : ''}>${esc(author.name)}</option>`).join('')}</select></div></section>
                <section class="panel sidebar-panel toc-panel"><header class="panel-heading"><div><p class="eyebrow">STRUCTURE</p><h2>Table of contents</h2></div></header><div class="panel-body"><ol id="toc-list">${(item.toc || []).map((heading) => `<li>${esc(heading.text || heading)}</li>`).join('') || '<li class="toc-empty">Add H2–H4 headings in the article.</li>'}</ol></div></section>
                <section class="panel sidebar-panel bottom-actions"><button class="button button-primary" type="button" data-save-status="published">Publish Now</button><button class="button button-secondary" type="button" data-save-status="scheduled">Schedule Post</button><button class="button button-quiet" type="button" data-save-status="draft">Save as Draft</button>${item.status === 'published' ? `<a class="button button-quiet" href="../blog/${encodeURIComponent(item.slug)}" target="_blank" rel="noopener">View Live Post ↗</a>` : ''}</section>
            </aside></div></form>
            <dialog class="block-dialog" id="block-dialog"><form method="dialog" id="block-form"><button class="dialog-close" value="cancel" aria-label="Close">×</button><p class="eyebrow" id="block-eyebrow">EDITORIAL BLOCK</p><h2 id="block-title">Add block</h2><div id="block-fields"></div><div class="dialog-actions"><button class="button button-quiet" value="cancel">Cancel</button><button class="button button-primary" id="insert-block" value="insert">Insert block</button></div></form></dialog>`;
        wireEditor(item);
    };

    function wireEditor(item) {
        const form = document.querySelector('#blog-editor');
        const title = form.elements.title;
        const excerpt = form.elements.excerpt;
        const slug = document.querySelector('#blog-slug');
        const metaTitle = document.querySelector('#meta-title');
        const metaDescription = form.elements.meta_description;
        if (!metaTitle.value && title.value) metaTitle.value = title.value.slice(0, 70);
        if (!metaDescription.value && excerpt.value) metaDescription.value = excerpt.value;
        metaTitle.required = true;
        metaDescription.required = true;
        let slugManual = Boolean(item.slug);
        let metaManual = Boolean(item.meta_title);
        if (item.title && item.meta_title === item.title) metaManual = false;
        title.addEventListener('input', () => {
            document.querySelector('#title-count').textContent = title.value.length;
            if (!slugManual) slug.value = slugify(title.value);
            if (!metaManual) metaTitle.value = title.value.slice(0, 70);
            updateMeters();
        });
        excerpt.addEventListener('input', () => { document.querySelector('#excerpt-count').textContent = excerpt.value.length; if (!metaDescription.dataset.manual) metaDescription.value = excerpt.value; updateMeters(); });
        slug.addEventListener('input', () => { slugManual = true; slug.value = slugify(slug.value); });
        metaTitle.addEventListener('input', () => { metaManual = true; updateMeters(); });
        metaDescription.addEventListener('input', (event) => { event.currentTarget.dataset.manual = 'true'; updateMeters(); });
        updateMeters();

        function updateMeters() {
            for (const [field, counter, meter, ideal] of [['meta_title','meta-title-count','meta-title-meter',60], ['meta_description','meta-description-count','meta-description-meter',200]]) {
                const length = form.elements[field].value.length;
                document.querySelector(`#${counter}`).textContent = length;
                const bar = document.querySelector(`#${meter}`);
                bar.style.width = `${Math.min(length / (field === 'meta_title' ? 70 : 250) * 100, 100)}%`;
                bar.className = length > (field === 'meta_title' ? 70 : 250) ? 'danger' : length < ideal ? 'warning' : 'good';
            }
        }

        const statusInput = document.querySelector('#publish-status');
        form.querySelectorAll('[data-editor-status]').forEach((button) => button.addEventListener('click', () => {
            statusInput.value = button.dataset.editorStatus;
            form.querySelectorAll('[data-editor-status]').forEach((tab) => tab.classList.toggle('selected', tab === button));
            document.querySelector('.schedule-fields').hidden = statusInput.value !== 'scheduled';
            document.querySelector('#publish-date').required = statusInput.value === 'scheduled';
            document.querySelector('#publish-time').required = statusInput.value === 'scheduled';
        }));

        const hiddenImage = document.querySelector('#feature-image');
        const input = document.querySelector('#image-file');
        const zone = document.querySelector('#image-dropzone');
        const upload = async (file) => {
            if (!file) return;
            const body = new FormData();
            body.append('action', 'upload');
            body.append('image', file);
            try {
                const response = await fetch('api.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Image upload failed.');
                hiddenImage.value = result.path;
                document.querySelector('#image-preview').innerHTML = `<img src="${esc(result.path)}" alt=""><button type="button" class="icon-button danger-icon" id="remove-image" aria-label="Remove image">×</button>`;
                zone.classList.add('has-image');
                document.querySelector('#remove-image').addEventListener('click', removeImage);
            } catch (error) { showError(error); }
        };
        const removeImage = () => { hiddenImage.value = ''; document.querySelector('#image-preview').innerHTML = ''; zone.classList.remove('has-image'); input.value = ''; };
        document.querySelector('#choose-image').addEventListener('click', () => input.click());
        input.addEventListener('change', () => upload(input.files[0]));
        zone.addEventListener('dragover', (event) => { event.preventDefault(); zone.classList.add('dragging'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragging'));
        zone.addEventListener('drop', (event) => { event.preventDefault(); zone.classList.remove('dragging'); upload(event.dataTransfer.files[0]); });
        if (document.querySelector('#remove-image')) document.querySelector('#remove-image').addEventListener('click', removeImage);

        let tags = item.tags || [];
        const tagInput = document.querySelector('#tag-input');
        const renderTags = () => {
            document.querySelector('#tags-json').value = JSON.stringify(tags);
            document.querySelectorAll('.tag-chip').forEach((chip) => chip.remove());
            tags.forEach((tag) => {
                const chip = document.createElement('span');
                chip.className = 'tag-chip';
                chip.innerHTML = `${esc(tag)}<button type="button" aria-label="Remove ${esc(tag)}">×</button>`;
                chip.querySelector('button').addEventListener('click', () => { tags = tags.filter((current) => current !== tag); renderTags(); });
                document.querySelector('#tag-field').insertBefore(chip, tagInput);
            });
        };
        tagInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ',') { event.preventDefault(); const value = tagInput.value.replace(/,$/, '').trim(); if (value && !tags.includes(value)) tags.push(value); tagInput.value = ''; renderTags(); }
        });
        tagInput.addEventListener('blur', () => { if (tagInput.value.trim()) { tags.push(tagInput.value.trim()); tagInput.value = ''; renderTags(); } });
        renderTags();

        const updateToc = () => {
            if (!window.tinymce || !tinymce.get('blog-content')) return;
            const content = tinymce.get('blog-content').getContent();
            const doc = new DOMParser().parseFromString(content, 'text/html');
            const headings = [...doc.querySelectorAll('h2,h3,h4')].map((heading) => ({ text: heading.textContent.trim(), level: Number(heading.tagName.slice(1)) })).filter((heading) => heading.text);
            document.querySelector('#heading-count').textContent = headings.length;
            document.querySelector('#toc-list').innerHTML = headings.length ? headings.map((heading) => `<li class="toc-level-${heading.level}">${esc(heading.text)}</li>`).join('') : '<li class="toc-empty">Add H2–H4 headings in the article.</li>';
            const words = (doc.body.textContent.match(/[\p{L}\p{N}]+/gu) || []).length;
            document.querySelector('#read-time').textContent = `Estimated read: ${Math.max(1, Math.ceil(words / 200))} min`;
        };

        const blockDialog = document.querySelector('#block-dialog');
        let blockKind = '';
        const openBlock = (kind) => {
            blockKind = kind;
            document.querySelector('#block-eyebrow').textContent = kind === 'faq' ? 'FAQ BLOCK' : 'LEAD CAPTURE BLOCK';
            document.querySelector('#block-title').textContent = kind === 'faq' ? 'Add a question and answer' : 'Add a lead form block';
            document.querySelector('#block-fields').innerHTML = kind === 'faq' ? '<label class="field-label">Question</label><input class="text-input" name="question" required><label class="field-label">Answer</label><textarea class="text-input" name="answer" required></textarea>' : '<label class="field-label">Headline</label><input class="text-input" name="headline" required><label class="field-label">Subtext</label><textarea class="text-input" name="subtext"></textarea><label class="field-label">Button text</label><input class="text-input" name="button" value="Get in touch" required>';
            blockDialog.showModal();
        };
        if (window.tinymce) {
            tinymce.init({ selector: '#blog-content', height: 680, menubar: 'file edit view insert format tools table', plugins: 'advlist autolink lists link image table code fullscreen preview wordcount', toolbar: 'undo redo | styles | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code fullscreen preview | removeformat | faqblock leadform', content_style: 'body{font-family:Arial,sans-serif;font-size:16px;line-height:1.7} .faq-block,.lead-form{padding:20px;border:1px solid #dce2e9;border-radius:8px;margin:20px 0}', setup(editor) {
                editor.ui.registry.addButton('faqblock', { text: 'FAQ Block', onAction: () => openBlock('faq') });
                editor.ui.registry.addButton('leadform', { text: 'Lead Form', onAction: () => openBlock('lead') });
                editor.on('init', () => { if (item.content) editor.setContent(item.content); updateToc(); });
                editor.on('keyup change SetContent', updateToc);
            } });
        }
        document.querySelector('#block-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const values = new FormData(event.currentTarget);
            const text = (value) => esc(String(value || '').trim());
            let markup = '';
            if (blockKind === 'faq') markup = `<div class="faq-block"><h3 class="faq-question">${text(values.get('question'))}</h3><p class="faq-answer">${text(values.get('answer'))}</p></div>`;
            else markup = `<div class="lead-form"><h3>${text(values.get('headline'))}</h3><p>${text(values.get('subtext'))}</p><p><a href="/contact.html">${text(values.get('button'))}</a></p></div>`;
            if (window.tinymce && tinymce.get('blog-content')) tinymce.get('blog-content').insertContent(markup);
            blockDialog.close();
            updateToc();
        });
        document.querySelector('#preview-content').addEventListener('click', () => {
            const content = window.tinymce && tinymce.get('blog-content') ? tinymce.get('blog-content').getContent() : '';
            const preview = window.open('', '_blank', 'noopener,noreferrer,width=900,height=750');
            if (preview) { preview.document.write(`<title>Article preview</title><style>body{max-width:760px;margin:60px auto;padding:0 24px;font:18px/1.7 Georgia,serif}img{max-width:100%}</style>${content}`); preview.document.close(); }
        });

        form.querySelectorAll('[data-save-status]').forEach((button) => button.addEventListener('click', async () => {
            const targetStatus = button.dataset.saveStatus;
            if (targetStatus === 'scheduled') {
                statusInput.value = 'scheduled';
                document.querySelector('.schedule-fields').hidden = false;
                document.querySelector('#publish-date').required = true;
                document.querySelector('#publish-time').required = true;
            } else {
                statusInput.value = targetStatus;
                document.querySelector('#publish-date').required = false;
                document.querySelector('#publish-time').required = false;
            }
            const data = Object.fromEntries(new FormData(form).entries());
            data.id = Number(document.body.dataset.blogId || 0);
            data.category_ids = [...form.querySelectorAll('[name="category_ids"]:checked')].map((checkbox) => Number(checkbox.value));
            data.tags = tags;
            data.toc = window.tinymce && tinymce.get('blog-content') ? [...new DOMParser().parseFromString(tinymce.get('blog-content').getContent(), 'text/html').querySelectorAll('h2,h3,h4')].map((heading) => ({ text: heading.textContent.trim(), level: Number(heading.tagName.slice(1)) })) : [];
            data.content = window.tinymce && tinymce.get('blog-content') ? tinymce.get('blog-content').getContent() : '';
            if (data.status === 'published' && !data.meta_title) data.meta_title = data.title;
            if (!form.reportValidity()) return;
            button.disabled = true;
            try {
                const result = await api('save_blog', data);
                document.body.dataset.blogId = result.id;
                history.replaceState({}, '', `index.php?page=editor&id=${result.id}`);
                document.querySelector('#save-state').textContent = `Saved just now · ${new Date(result.updated_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
                toast(result.message);
                if (result.slug) document.querySelector('#blog-slug').value = result.slug;
            } catch (error) { showError(error); } finally { button.disabled = false; }
        }));
    }

    const categoriesPage = async () => {
        const data = await api('categories');
        app.innerHTML = `<div class="list-heading"><div><p class="eyebrow">TAXONOMY</p><h2>Categories</h2></div></div><div class="manage-grid"><section class="panel"><header class="panel-heading"><div><p class="eyebrow">ORGANIZE STORIES</p><h2>All categories</h2></div></header>${data.items.length ? `<div class="table-wrap"><table><thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead><tbody>${data.items.map((item) => `<tr><td><b>${esc(item.name)}</b></td><td><code>${esc(item.slug)}</code></td><td>${esc(item.description || '—')}</td><td><span class="status-badge ${Number(item.is_active) ? 'status-published' : 'status-deleted'}">${Number(item.is_active) ? 'Active' : 'Inactive'}</span></td><td><button class="icon-button" data-edit-category="${item.id}" title="Edit">✎</button><button class="icon-button" data-toggle-category="${item.id}" data-active="${item.is_active}" title="Activate or deactivate">${Number(item.is_active) ? '−' : '+'}</button></td></tr>`).join('')}</tbody></table></div>` : emptyState('No categories yet', 'Create your first category to organize posts.')}</section>
            <section class="panel form-panel"><header class="panel-heading"><div><p class="eyebrow">CATEGORY DETAILS</p><h2 id="category-form-title">Add category</h2></div></header><form id="category-form" class="panel-body"><input type="hidden" name="id"><label class="field-label">Name <em>Required</em></label><input class="text-input" name="name" required maxlength="120"><label class="field-label">Slug</label><input class="text-input" name="slug" placeholder="Generated from name"><label class="field-label">Description</label><textarea class="text-input" name="description" rows="4"></textarea><div class="form-actions"><button type="button" class="button button-quiet" id="reset-category">Clear</button><button class="button button-primary">Save category</button></div></form></section></div>`;
        const form = document.querySelector('#category-form');
        const refresh = () => { form.reset(); form.elements.id.value = ''; document.querySelector('#category-form-title').textContent = 'Add category'; };
        document.querySelector('#reset-category').addEventListener('click', refresh);
        form.elements.name.addEventListener('input', () => { if (!form.elements.id.value || !form.elements.slug.dataset.manual) form.elements.slug.value = slugify(form.elements.name.value); });
        form.elements.slug.addEventListener('input', () => { form.elements.slug.dataset.manual = 'true'; form.elements.slug.value = slugify(form.elements.slug.value); });
        form.addEventListener('submit', async (event) => { event.preventDefault(); try { await api('save_category', Object.fromEntries(new FormData(form))); toast('Category saved.'); categoriesPage(); } catch (error) { showError(error); } });
        app.querySelectorAll('[data-edit-category]').forEach((button) => button.addEventListener('click', () => {
            const item = data.items.find((category) => Number(category.id) === Number(button.dataset.editCategory));
            form.elements.id.value = item.id; form.elements.name.value = item.name; form.elements.slug.value = item.slug; form.elements.slug.dataset.manual = 'true'; form.elements.description.value = item.description || ''; document.querySelector('#category-form-title').textContent = 'Edit category';
        }));
        app.querySelectorAll('[data-toggle-category]').forEach((button) => button.addEventListener('click', async () => {
            const action = button.dataset.active === '1' ? 'deactivate' : 'activate';
            if (!await confirmAction(`${action[0].toUpperCase() + action.slice(1)} category?`, 'Existing stories remain assigned to this category.')) return;
            try { await api('toggle_category', { id: Number(button.dataset.toggleCategory) }); toast('Category status updated.'); categoriesPage(); } catch (error) { showError(error); }
        }));
    };

    const authorsPage = async () => {
        const data = await api('authors');
        app.innerHTML = `<div class="list-heading"><div><p class="eyebrow">CONTRIBUTORS</p><h2>Authors</h2></div></div><div class="manage-grid"><section class="panel"><header class="panel-heading"><div><p class="eyebrow">BYLINES</p><h2>All authors</h2></div></header>${data.items.length ? `<div class="table-wrap"><table><thead><tr><th>Author</th><th>Bio</th><th>Status</th><th>Actions</th></tr></thead><tbody>${data.items.map((item) => `<tr><td><div class="author-cell">${item.image ? `<img src="${esc(item.image)}" alt="">` : `<span>${esc(item.name.charAt(0).toUpperCase())}</span>`}<b>${esc(item.name)}</b></div></td><td>${esc((item.bio || '—').slice(0, 90))}</td><td><span class="status-badge ${Number(item.is_active) ? 'status-published' : 'status-deleted'}">${Number(item.is_active) ? 'Active' : 'Inactive'}</span></td><td><button class="icon-button" data-edit-author="${item.id}" title="Edit">✎</button><button class="icon-button" data-toggle-author="${item.id}" data-active="${item.is_active}" title="Activate or deactivate">${Number(item.is_active) ? '−' : '+'}</button></td></tr>`).join('')}</tbody></table></div>` : emptyState('No authors yet', 'Add contributors to your editorial desk.')}</section>
            <section class="panel form-panel"><header class="panel-heading"><div><p class="eyebrow">AUTHOR DETAILS</p><h2 id="author-form-title">Add author</h2></div></header><form id="author-form" class="panel-body"><input type="hidden" name="id"><label class="field-label">Author name <em>Required</em></label><input class="text-input" name="name" required maxlength="160"><label class="field-label">Bio</label><textarea class="text-input" name="bio" rows="4"></textarea><label class="field-label">Author image URL</label><input class="text-input" name="image" placeholder="https:// or /uploads/blog/…"><label class="field-label">Author page URL</label><input class="text-input" name="page_url" type="url" placeholder="https://…"><div class="form-actions"><button type="button" class="button button-quiet" id="reset-author">Clear</button><button class="button button-primary">Save author</button></div></form></section></div>`;
        const form = document.querySelector('#author-form');
        const imageInput = form.elements.image;
        imageInput.insertAdjacentHTML('afterend', '<div class="author-upload"><label class="button button-quiet">Upload image<input id="author-image-file" type="file" accept="image/jpeg,image/png,image/webp" hidden></label><small>JPG, PNG, WebP · Up to 5 MB</small><div id="author-image-preview"></div></div>');
        const imagePreview = document.querySelector('#author-image-preview');
        const renderAuthorImage = () => { imagePreview.innerHTML = imageInput.value ? `<img src="${esc(imageInput.value)}" alt="Author preview">` : ''; };
        imageInput.addEventListener('input', renderAuthorImage);
        document.querySelector('#author-image-file').addEventListener('change', async (event) => {
            const file = event.currentTarget.files[0];
            if (!file) return;
            const body = new FormData(); body.append('action', 'upload'); body.append('image', file);
            try {
                const response = await fetch('api.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Image upload failed.');
                imageInput.value = result.path; renderAuthorImage();
            } catch (error) { showError(error); }
        });
        const refresh = () => { form.reset(); form.elements.id.value = ''; renderAuthorImage(); document.querySelector('#author-form-title').textContent = 'Add author'; };
        document.querySelector('#reset-author').addEventListener('click', refresh);
        form.addEventListener('submit', async (event) => { event.preventDefault(); try { await api('save_author', Object.fromEntries(new FormData(form))); toast('Author saved.'); authorsPage(); } catch (error) { showError(error); } });
        app.querySelectorAll('[data-edit-author]').forEach((button) => button.addEventListener('click', () => {
            const item = data.items.find((author) => Number(author.id) === Number(button.dataset.editAuthor));
            Object.entries(item).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value || ''; });
            renderAuthorImage();
            document.querySelector('#author-form-title').textContent = 'Edit author';
        }));
        app.querySelectorAll('[data-toggle-author]').forEach((button) => button.addEventListener('click', async () => {
            if (!await confirmAction(button.dataset.active === '1' ? 'Deactivate author?' : 'Activate author?', 'This changes whether the author can be selected for new posts.')) return;
            try { await api('toggle_author', { id: Number(button.dataset.toggleAuthor) }); toast('Author status updated.'); authorsPage(); } catch (error) { showError(error); }
        }));
    };

    const settingsPage = async () => {
        const data = await api('settings');
        const admin = data.item;
        app.innerHTML = `<div class="list-heading"><div><p class="eyebrow">ACCOUNT &amp; ACCESS</p><h2>Settings</h2></div></div><div class="settings-grid"><section class="panel"><header class="panel-heading"><div><p class="eyebrow">CURRENT ACCOUNT</p><h2>Account information</h2></div></header><div class="panel-body account-details"><div><small>Full name</small><strong>${esc(admin.full_name)}</strong></div><div><small>Username</small><strong>${esc(admin.username)}</strong></div><div><small>Role</small><strong>${esc(admin.role)}</strong></div></div></section><section class="panel"><header class="panel-heading"><div><p class="eyebrow">SECURITY</p><h2>Change password</h2></div></header><form id="password-form" class="panel-body password-form"><label class="field-label">Current password</label><input class="text-input" name="current_password" type="password" autocomplete="current-password" required><label class="field-label">New password</label><input class="text-input" name="new_password" type="password" minlength="12" autocomplete="new-password" required><small class="field-hint">Use at least 12 characters.</small><label class="field-label">Confirm new password</label><input class="text-input" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required><button class="button button-primary">Update password</button></form></section><section class="panel"><header class="panel-heading"><div><p class="eyebrow">EDITOR</p><h2>TinyMCE</h2></div></header><div class="panel-body"><p class="muted-copy">The editor is loaded from Tiny Cloud's public no-key endpoint. For production, configure an API key or host a reviewed TinyMCE build locally.</p></div></section></div>`;
        document.querySelector('#password-form').addEventListener('submit', async (event) => { event.preventDefault(); const form = event.currentTarget; try { const result = await api('change_password', Object.fromEntries(new FormData(form))); form.reset(); toast(result.message); } catch (error) { showError(error); } });
    };

    const render = async () => {
        try {
            if (page === 'dashboard') await dashboard();
            else if (page === 'blogs') await renderBlogs(new URLSearchParams(location.search).get('status') || 'all');
            else if (page === 'editor') await editorPage();
            else if (page === 'categories') await categoriesPage();
            else if (page === 'authors') await authorsPage();
            else if (page === 'settings') await settingsPage();
        } catch (error) {
            app.innerHTML = emptyState('Could not load this view', esc(error.message), '<a class="button button-secondary" href="index.php">Return to dashboard</a>');
        }
    };
    render();
})();