import { jsPDF } from 'jspdf';

function downscaleDataUrl(dataUrl, maxDim, quality) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const ratio = Math.min(1, maxDim / Math.max(img.naturalWidth, img.naturalHeight));
            const w = Math.round(img.naturalWidth * ratio);
            const h = Math.round(img.naturalHeight * ratio);
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            resolve({ dataUrl: canvas.toDataURL('image/jpeg', quality), width: w, height: h });
        };
        img.onerror = reject;
        img.src = dataUrl;
    });
}

export function imageFromFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = async (ev) => {
            try {
                resolve(await downscaleDataUrl(ev.target.result, 1600, 0.78));
            } catch (err) {
                reject(err);
            }
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

export async function buildPdf(pages) {
    const pdf = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait', compress: true });
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    pages.forEach((p, i) => {
        if (i > 0) pdf.addPage();
        const ratio = Math.min(pageW / p.width, pageH / p.height);
        const w = p.width * ratio;
        const h = p.height * ratio;
        const x = (pageW - w) / 2;
        const y = (pageH - h) / 2;
        pdf.addImage(p.dataUrl, 'JPEG', x, y, w, h, undefined, 'FAST');
    });
    return pdf.output('blob');
}
