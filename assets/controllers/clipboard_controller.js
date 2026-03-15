import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['button']
    static values = { text: String }

    copy() {
        navigator.clipboard.writeText(this.textValue).then(() => {
            const btn = this.buttonTarget
            const original = btn.innerHTML
            btn.innerHTML = `<svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Copié !`
            btn.disabled = true
            setTimeout(() => {
                btn.innerHTML = original
                btn.disabled = false
            }, 2000)
        })
    }
}
