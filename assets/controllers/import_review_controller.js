import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['row', 'checkbox']

    toggle(event) {
        const checkbox = event.target
        const row = checkbox.closest('tr')
        row.classList.toggle('import-table__row--disabled', !checkbox.checked)
        row.querySelectorAll('input:not([type=checkbox]), select').forEach(el => {
            el.disabled = !checkbox.checked
        })
    }

    toggleAll(event) {
        const checked = event.target.checked
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = checked
            checkbox.dispatchEvent(new Event('change', { bubbles: true }))
        })
    }
}
