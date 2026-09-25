// Pembantu format tampilan Indonesia (rupiah & tanggal) untuk seluruh halaman React.

export const rupiah = (n) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);

export const fmtDate = (d) => (d ? String(d).slice(0, 10).split('-').reverse().join('/') : '—');

export const fmtDateTime = (d) => (d ? String(d).slice(0, 16).replace('T', ' ') : '—');
