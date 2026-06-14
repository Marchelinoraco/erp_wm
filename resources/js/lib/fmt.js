export function fmtNum(val) {
    return Number(val ?? 0).toLocaleString('id-ID')
}

export function fmtRp(val) {
    return 'Rp ' + fmtNum(val)
}
