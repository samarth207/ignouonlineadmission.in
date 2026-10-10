(function () {
    if (document.getElementById('programme-enquiry')) return;
    const siteRoot = new URL('../', document.currentScript.src);
    const endpoint = new URL('api/enquiries.php', siteRoot);
    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = new URL('css/enquiry.css', siteRoot).href;
    document.head.appendChild(stylesheet);
    const coursePage = /\/programme\/p\d+\.html$/.test(location.pathname);
    const courseName = coursePage ? document.querySelector('.header-title')?.textContent.trim() : '';
    const dialog = document.createElement('dialog');
    dialog.id = 'programme-enquiry';
    dialog.setAttribute('aria-labelledby', 'enquiry-title');
    dialog.innerHTML = `
        <button class="enquiry-close" type="button" aria-label="Close enquiry" title="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        <h2 id="enquiry-title">Programme Enquiry</h2>
        <form id="enquiry-form">
            <label for="enquiry-name">Full name</label>
            <input id="enquiry-name" name="name" autocomplete="name" maxlength="120" required>
            <label for="enquiry-email">Email</label>
            <input id="enquiry-email" name="email" type="email" autocomplete="email" maxlength="254" required>
            <label for="enquiry-phone">Phone</label>
            <input id="enquiry-phone" name="phone" type="tel" autocomplete="tel" maxlength="30" pattern="[+0-9\\(\\) .\\-]{7,30}" required>
            <label for="enquiry-course">Programme</label>
            <select id="enquiry-course" name="course" required><option value="">Select a programme</option></select>
            <div class="enquiry-trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <label class="enquiry-consent"><input name="consent" type="checkbox" value="1" required><span>I agree to be contacted about my programme enquiry.</span></label>
            <button class="enquiry-submit" type="submit">Submit Enquiry</button>
        </form>
        <p class="enquiry-status" role="status" aria-live="polite"></p>`;
    document.body.appendChild(dialog);
    const form = dialog.querySelector('form');
    const status = dialog.querySelector('.enquiry-status');
    const submit = dialog.querySelector('.enquiry-submit');
    const courseSelect = form.elements.course;
    if (courseName) courseSelect.add(new Option(courseName, courseName, true, true));
    let catalogueLoading;
    function loadCourses() {
        if (catalogueLoading) return catalogueLoading;
        catalogueLoading = Promise.all([0, 1, 2, 3].map(async (index) => {
            const response = await fetch(new URL(`programme/${index}.html`, siteRoot));
            if (!response.ok) throw new Error('Unable to load programmes');
            const markup = new DOMParser().parseFromString(await response.text(), 'text/html');
            return Array.from(markup.querySelectorAll('.stat-text .count a[href]'))
                .filter(link => /(?:^|\/)p\d+\.html$/.test(link.getAttribute('href')))
                .map(link => link.textContent.replace(/\s+/g, ' ').trim());
        })).then((pages) => {
            const courses = [...new Set(pages.flat().filter(Boolean))].sort((first, second) => first.localeCompare(second));
            if (!courses.length) throw new Error('No programmes found');
            const selected = courseSelect.value;
            if (selected && !courses.includes(selected)) courses.push(selected);
            courseSelect.replaceChildren(new Option('Select a programme', ''));
            courses.forEach(course => courseSelect.add(new Option(course, course)));
            courseSelect.value = selected;
        }).catch(() => {
            catalogueLoading = undefined;
            status.textContent = 'Unable to load programmes. Please close the form and try again.';
        });
        return catalogueLoading;
    }
    function openEnquiry() {
        if (dialog.open || document.querySelector('.modal.show')) return;
        if (form.hidden) {
            form.reset();
            courseSelect.value = courseName || '';
            form.hidden = false;
        }
        status.textContent = '';
        dialog.showModal();
        loadCourses();
    }
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('[data-enquiry-open]')) return;
        event.preventDefault();
        event.stopPropagation();
        clearTimeout(popupTimer);
        openEnquiry();
    }, true);
    dialog.querySelector('.enquiry-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            dialog.close();
        }
    });
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity() || submit.disabled) return;
        submit.disabled = true;
        submit.textContent = 'Submitting...';
        status.textContent = '';
        try {
            const tokenResponse = await fetch(endpoint, {credentials: 'same-origin', cache: 'no-store'});
            if (!tokenResponse.ok) throw new Error('Service unavailable');
            const token = await tokenResponse.json();
            const data = new URLSearchParams(new FormData(form));
            data.set('csrf', token.csrf);
            data.set('page', location.pathname);
            const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', body: data});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Unable to submit. Please try again.');
            form.hidden = true;
            status.textContent = 'Thank you. Your enquiry has been received.';
        } catch (error) {
            const unavailable = error instanceof SyntaxError || error.message === 'Failed to fetch' || error.message === 'Service unavailable';
            status.textContent = location.protocol === 'file:' ? 'Submissions require the hosted website. Please contact coe@ignou.ac.in.' : (unavailable ? 'Enquiries are temporarily unavailable. Please try again later.' : error.message);
        } finally {
            submit.disabled = false;
            submit.textContent = 'Submit Enquiry';
        }
    });
    const popupTimer = setTimeout(openEnquiry, coursePage ? 0 : 5000);
})();