const openDialogs = new Set<HTMLDialogElement>()
let originalOverflow = ''
export function lockDialog(dialog: HTMLDialogElement) {
  if (!openDialogs.size) { originalOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden' }
  openDialogs.add(dialog)
}
export function unlockDialog(dialog: HTMLDialogElement) {
  openDialogs.delete(dialog)
  if (!openDialogs.size) document.body.style.overflow = originalOverflow
}
