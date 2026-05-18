/**
 * Elementor Form Lead Tracker — Frontend Tracker v2.1
 * Respects per-form GA4 and Freshsales toggle states.
 * GA4 events only fire when enableGA4 is true for this form.
 * Freshsales tagging is handled server-side (AJAX) based on the toggle.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        var selectors = [
            '.elementor-button',
            '.elementor-cta__button',
            'a[download]',
            'a[href$=".pdf"]', 'a[href$=".PDF"]',
            'a[href$=".doc"]', 'a[href$=".docx"]',
            'a[href$=".xlsx"]', 'a[href$=".zip"]',
            'a[href$=".pptx"]', 'a[href$=".csv"]',
        ];

        var selectorStr = selectors.join(', ');

        document.querySelectorAll(selectorStr).forEach(function (btn) {
            btn.addEventListener('click', handleClick);
        });

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches(selectorStr)) {
                        node.addEventListener('click', handleClick);
                    }
                    var nested = node.querySelectorAll ? node.querySelectorAll(selectorStr) : [];
                    nested.forEach(function (b) {
                        b.addEventListener('click', handleClick);
                    });
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function handleClick(e) {
        var btn = e.currentTarget;

        var buttonId =
            btn.id ||
            btn.closest('[data-id]')?.getAttribute('data-id') ||
            btn.closest('.elementor-element')?.getAttribute('data-id') ||
            'unknown';

        var buttonLabel = (
            btn.textContent.trim() ||
            btn.getAttribute('aria-label') ||
            btn.getAttribute('title') ||
            'Unnamed Button'
        ).replace(/\s+/g, ' ').substring(0, 100);

        var fileUrl = btn.href || btn.getAttribute('data-href') || '';

        var fileName = '';
        try {
            if (fileUrl) {
                fileName = new URL(fileUrl, window.location.origin).pathname.split('/').pop();
            }
        } catch (err) {
            fileName = fileUrl;
        }

        var formData = new FormData();
        formData.append('action', 'eflt_track_download');
        formData.append('nonce', efltData.nonce);
        formData.append('page_id', efltData.pageId);
        formData.append('button_id', buttonId);
        formData.append('button_label', buttonLabel);
        formData.append('file_url', fileUrl);

        fetch(efltData.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(function () {});

        if (efltData.enableGA4 && typeof gtag === 'function' && efltData.ga4Id) {
            gtag('event', 'file_download', {
                event_category: 'PDF Downloads',
                event_label: buttonLabel,
                file_name: fileName || buttonLabel,
                file_url: fileUrl,
                button_id: buttonId,
                page_id: efltData.pageId,
                send_to: efltData.ga4Id,
            });
        }

        if (efltData.enableGA4 && typeof window.dataLayer !== 'undefined') {
            window.dataLayer.push({
                event: 'dg_pdf_download',
                dg_button_label: buttonLabel,
                dg_file_name: fileName,
                dg_file_url: fileUrl,
                dg_button_id: buttonId,
                dg_page_id: efltData.pageId,
            });
        }
    }
})();
