export function fmtNum(val) {
    return Number(val ?? 0).toLocaleString('id-ID')
}

export function fmtRp(val) {
    return 'Rp ' + fmtNum(val)
}

/** Format nilai dengan kode mata uang, mis. fmtCur(5012, 'EUR') → "EUR 5.012". */
export function fmtCur(val, currency = 'IDR') {
    return (currency || 'IDR') + ' ' + fmtNum(val)
}
