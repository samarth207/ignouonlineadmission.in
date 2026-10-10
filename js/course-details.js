(function () {
    const detailIds = ['Eligibility', 'Duration', 'JobFuture', 'Coordinators'];
    detailIds.forEach((detailId) => {
        const modal = document.getElementById(detailId);
        if (!modal || !modal.classList.contains('modal')) return;
        const body = modal.querySelector('.modal-body');
        const title = modal.querySelector('.modal-title');
        if (!body || !title) return;
        const section = document.createElement('section');
        section.id = detailId;
        section.className = 'programme-detail-section';
        const heading = document.createElement('h4');
        heading.id = `programme-detail-${detailId}`;
        heading.textContent = title.textContent.trim();
        section.setAttribute('aria-labelledby', heading.id);
        section.appendChild(heading);
        body.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
            const panelId = tab.getAttribute('href');
            const panel = panelId && panelId.startsWith('#') ? body.querySelector(panelId) : null;
            if (!panel) return;
            const feeHeading = document.createElement('h5');
            feeHeading.id = `${panel.id}-heading`;
            feeHeading.textContent = tab.textContent.trim();
            panel.prepend(feeHeading);
            panel.classList.remove('tab-pane', 'fade', 'show', 'active');
            panel.classList.add('programme-fee-group');
            panel.removeAttribute('role');
            panel.setAttribute('aria-labelledby', feeHeading.id);
        });
        body.querySelectorAll('[role="tablist"]').forEach((tabs) => tabs.remove());
        body.querySelectorAll('.tab-content').forEach((content) => content.classList.remove('tab-content'));
        section.append(...Array.from(body.childNodes));
        modal.replaceWith(section);
        document.querySelectorAll(`[data-bs-target="#${detailId}"], [data-target="#${detailId}"]`).forEach((button) => {
            const wrapper = button.closest('.linescol');
            if (wrapper && wrapper.children.length === 1) wrapper.remove();
            else button.remove();
        });
    });
})();