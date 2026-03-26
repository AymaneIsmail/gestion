import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'includeCheck', 'body',
        'updateSection', 'createSection',
        'matchedId',
        'pickerTrigger', 'pickerPanel', 'pickerSearch', 'pickerResults',
    ]

    connect() {
        this.catalog = JSON.parse(
            this.element.closest('form')?.dataset.importCatalog ?? '{}'
        )
        this._onOutside = (e) => {
            if (!this.element.contains(e.target)) this.closePicker()
        }
        // Render initial selection if matchedId already has a value
        if (this.hasMatchedIdTarget && this.matchedIdTarget.value) {
            this._renderTrigger(this.matchedIdTarget.value)
        }
    }

    disconnect() {
        document.removeEventListener('click', this._onOutside)
    }

    // ── Include / exclude ──────────────────────────────────────────
    toggleInclude() {
        const on = this.includeCheckTarget.checked
        this.bodyTarget.classList.toggle('import-row--excluded', !on)
        this.bodyTarget.querySelectorAll(
            'input:not([type=checkbox]):not([type=radio]), select, button[type=button]'
        ).forEach(el => { el.disabled = !on })
    }

    // ── Action toggle ──────────────────────────────────────────────
    selectUpdate() {
        this.updateSectionTarget.hidden = false
        this.createSectionTarget.hidden = true
    }

    selectCreate() {
        this.updateSectionTarget.hidden = true
        this.createSectionTarget.hidden = false
    }

    // ── Picker open / close ────────────────────────────────────────
    openPicker() {
        this.pickerPanelTarget.hidden = false
        this.pickerSearchTarget.value = ''
        this._renderResults(Object.keys(this.catalog))
        this.pickerSearchTarget.focus()
        document.addEventListener('click', this._onOutside)
    }

    closePicker() {
        if (!this.hasPickerPanelTarget) return
        this.pickerPanelTarget.hidden = true
        document.removeEventListener('click', this._onOutside)
    }

    filterProducts() {
        const q = this.pickerSearchTarget.value.toLowerCase()
        const ids = Object.keys(this.catalog).filter(id => {
            const p = this.catalog[id]
            return (
                p.name.toLowerCase().includes(q) ||
                (p.reference ?? '').toLowerCase().includes(q)
            )
        })
        this._renderResults(ids)
    }

    selectProduct(event) {
        const id = event.currentTarget.dataset.productId
        this.matchedIdTarget.value = id
        this._renderTrigger(id)
        this.closePicker()
    }

    // ── Private helpers ────────────────────────────────────────────
    _renderTrigger(id) {
        const p = this.catalog[id]
        if (!p || !this.hasPickerTriggerTarget) return

        const img = p.imageUrl
            ? `<img src="${p.imageUrl}" alt="" class="ppicker__thumb-img">`
            : `<span class="ppicker__thumb-empty">📦</span>`

        const ref   = p.reference ? `<span>${this._esc(p.reference)}</span>` : ''
        const stock = `<span>${p.stock} en stock</span>`
        const price = p.sellingPrice
            ? `<span class="ppicker__price">${p.sellingPrice.toFixed(2).replace('.', ',')} €</span>`
            : ''

        this.pickerTriggerTarget.innerHTML = `
            <div class="ppicker__selected">
                <div class="ppicker__thumb">${img}</div>
                <div class="ppicker__info">
                    <div class="ppicker__name">${this._esc(p.name)}</div>
                    <div class="ppicker__meta">${[ref, stock, price].filter(Boolean).join(' · ')}</div>
                </div>
                <span class="ppicker__change-hint">Changer</span>
            </div>`
    }

    _renderResults(ids) {
        if (ids.length === 0) {
            this.pickerResultsTarget.innerHTML =
                '<p class="ppicker__empty">Aucun résultat</p>'
            return
        }

        this.pickerResultsTarget.innerHTML = ids.map(id => {
            const p   = this.catalog[id]
            const img = p.imageUrl
                ? `<img src="${p.imageUrl}" alt="" class="ppicker__thumb-img">`
                : `<span class="ppicker__thumb-empty">📦</span>`
            const ref   = p.reference ? `<span>${this._esc(p.reference)}</span>` : ''
            const stock = `<span>${p.stock} en stock</span>`
            const price = p.sellingPrice
                ? `<span class="ppicker__price">${p.sellingPrice.toFixed(2).replace('.', ',')} €</span>`
                : ''

            return `<button type="button" class="ppicker__result"
                        data-product-id="${id}"
                        data-action="click->import-row#selectProduct">
                        <div class="ppicker__thumb">${img}</div>
                        <div class="ppicker__info">
                            <div class="ppicker__name">${this._esc(p.name)}</div>
                            <div class="ppicker__meta">${[ref, stock, price].filter(Boolean).join(' · ')}</div>
                        </div>
                    </button>`
        }).join('')
    }

    _esc(str) {
        return str.replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]))
    }
}
