(function (window, document) {
    'use strict';

    const escapeHtml = value => {
        const node = document.createElement('div');
        node.textContent = String(value || '');
        return node.innerHTML;
    };
    const value = (data, key, fallback = '') => String(data[key] || fallback).trim();
    const postalLabel = country => {
        const code = String(country || '').trim().toUpperCase();
        if (code === 'BR' || code === 'BRA' || code === 'BRASIL' || code === 'BRAZIL') return 'CEP';
        if (code === 'US' || code === 'USA' || code === 'UNITED STATES') return 'ZIP CODE';
        return 'POSTAL CODE';
    };
    const sortCode = postalCode => String(postalCode || '').replace(/[^a-z0-9]/gi, '').slice(0, 3).toUpperCase() || '---';
    const addressLine = (data, prefix) => [value(data, `${prefix}City`), value(data, `${prefix}State`), value(data, `${prefix}Postal`)].filter(Boolean).join(' · ');

    function render(preview, rawData, labels) {
        const data = { ...rawData };
        const destinationPostalLabel = postalLabel(data.destinationCountry);
        const destinationSortCode = sortCode(data.destinationPostal);
        const senderRegion = addressLine(data, 'sender');
        const destinationRegion = addressLine(data, 'destination');
        const instructions = value(data, 'instructions');
        preview.innerHTML = `
            <article class="ophyra-shipping-label">
                <header class="label-brand">
                    <span>${escapeHtml(value(data, 'platformName', 'OPHYRA'))}</span>
                    <strong>${escapeHtml(value(data, 'carrierName', labels.logistics))}</strong>
                </header>
                <section class="label-block label-sender">
                    <div class="label-caption">${escapeHtml(labels.sender)}</div>
                    <strong>${escapeHtml(value(data, 'senderName', 'Ophyra'))}</strong>
                    <div>${escapeHtml(value(data, 'senderAddress'))}</div>
                    <div>${escapeHtml(senderRegion)}</div>
                    <div>${escapeHtml(postalLabel(data.senderCountry))}: ${escapeHtml(value(data, 'senderPostal'))} · ${escapeHtml(value(data, 'senderCountry'))}</div>
                </section>
                <section class="label-order text-center">
                    <strong>${escapeHtml(value(data, 'channelName', 'OPHYRA STORE'))}</strong>
                    <div class="label-order-number">${escapeHtml(labels.orderNumber)}: ${escapeHtml(value(data, 'trackingCode'))}</div>
                    <div>${escapeHtml(labels.orderDate)}: ${escapeHtml(value(data, 'orderDate'))}</div>
                </section>
                <section class="label-machine-row">
                    <div>
                        <div class="label-qr" data-label-qr></div>
                        <div class="label-human-code">${escapeHtml(value(data, 'trackingCode'))}</div>
                    </div>
                    <div class="label-sort-box">
                        <strong>${escapeHtml(destinationSortCode)}</strong>
                        <span>${escapeHtml(destinationPostalLabel)}: ${escapeHtml(value(data, 'destinationPostal'))}</span>
                    </div>
                </section>
                <section class="label-block label-recipient">
                    <div class="label-caption">${escapeHtml(labels.recipient)}</div>
                    <strong>${escapeHtml(value(data, 'recipientName', labels.customer))}</strong>
                    <div>${escapeHtml(value(data, 'destinationAddress'))}</div>
                    ${value(data, 'destinationAddress2') ? `<div>${escapeHtml(value(data, 'destinationAddress2'))}</div>` : ''}
                    <div>${escapeHtml(destinationRegion)}</div>
                    <div>${escapeHtml(destinationPostalLabel)}: ${escapeHtml(value(data, 'destinationPostal'))} · ${escapeHtml(value(data, 'destinationCountry'))}</div>
                    ${instructions ? `<div class="label-instructions"><strong>${escapeHtml(labels.instructions)}:</strong> ${escapeHtml(instructions)}</div>` : ''}
                </section>
                <footer>${escapeHtml(labels.packageHandledBy)} ${escapeHtml(value(data, 'carrierName', labels.logistics))}</footer>
            </article>`;
        const qr = preview.querySelector('[data-label-qr]');
        if (qr && window.QRCode) {
            new window.QRCode(qr, { text: value(data, 'url'), width: 172, height: 172, correctLevel: window.QRCode.CorrectLevel.H });
        }
    }

    function print(preview, title) {
        const clone = preview.cloneNode(true);
        const sourceCanvas = preview.querySelector('[data-label-qr] canvas');
        const targetQr = clone.querySelector('[data-label-qr]');
        if (sourceCanvas && targetQr) targetQr.innerHTML = `<img src="${sourceCanvas.toDataURL('image/png')}" width="172" height="172" alt="QR">`;
        const popup = window.open('', '_blank', 'width=620,height=900');
        if (!popup) return false;
        popup.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title><style>${styles(true)}</style></head><body>${clone.innerHTML}</body></html>`);
        popup.document.close();
        popup.onload = function () { popup.focus(); popup.print(); popup.close(); };
        return true;
    }

    function styles(printMode) {
        return `
            ${printMode ? '@page{size:4in 6in;margin:0.12in}body{margin:0;padding:0}' : ''}
            .ophyra-shipping-label{box-sizing:border-box;width:100%;max-width:4in;min-height:5.7in;margin:auto;border:2px dashed #111;background:#fff;color:#111;font-family:Arial,sans-serif;font-size:13px;line-height:1.25}
            .ophyra-shipping-label *{box-sizing:border-box}.label-brand{background:#111;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;font-size:16px;letter-spacing:.04em}
            .label-brand span{font-size:20px;font-weight:800}.label-block{padding:12px 16px}.label-caption{font-size:14px;font-weight:800;margin-bottom:3px;text-transform:uppercase}.label-sender{min-height:98px}
            .label-order{border-top:2px solid #111;border-bottom:2px solid #111;padding:12px;font-size:15px}.text-center{text-align:center}.label-order-number{font-size:18px;font-weight:900;margin-top:3px}
            .label-machine-row{display:grid;grid-template-columns:1fr 1fr;align-items:center;gap:14px;padding:14px 16px;border-bottom:2px solid #111}.label-qr{width:172px;height:172px}.label-human-code{font-weight:800;text-align:center;margin-top:4px;overflow-wrap:anywhere}
            .label-sort-box{border:4px solid #111;text-align:center}.label-sort-box strong{display:block;background:#111;color:#fff;font-size:38px;padding:16px 4px}.label-sort-box span{display:block;font-size:16px;font-weight:900;padding:9px 4px}
            .label-recipient{min-height:132px;font-size:14px}.label-recipient>strong{font-size:17px}.label-instructions{border-top:1px solid #777;margin-top:7px;padding-top:6px}.ophyra-shipping-label footer{border-top:2px solid #111;text-align:center;padding:10px 12px;font-size:11px;text-transform:uppercase}
            @media(max-width:430px){.label-machine-row{grid-template-columns:1fr}.label-qr{margin:auto}}
        `;
    }

    if (!document.getElementById('ophyraShippingLabelStyles')) {
        const style = document.createElement('style');
        style.id = 'ophyraShippingLabelStyles';
        style.textContent = styles(false);
        document.head.appendChild(style);
    }
    window.OphyraShippingLabel = { render, print, styles };
})(window, document);
