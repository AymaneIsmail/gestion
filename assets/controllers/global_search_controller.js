import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['input', 'dropdown', 'results', 'drawerInput', 'drawer', 'drawerResults']
    static values = { url: String }

    connect() {
        this._debounceTimer = null
        this._closeHandler = this._onClickOutside.bind(this)
    }

    disconnect() {
        clearTimeout(this._debounceTimer)
        document.removeEventListener('click', this._closeHandler)
        document.body.classList.remove('no-scroll')
    }

    // ── Desktop ────────────────────────────────────────────

    onInput() {
        clearTimeout(this._debounceTimer)
        const q = this.inputTarget.value.trim()

        if (q.length < 2) {
            this._hide()
            return
        }

        this._debounceTimer = setTimeout(() => this._searchDesktop(q), 300)
    }

    onKeydown(e) {
        if (e.key === 'Escape') {
            this._hide()
            this.inputTarget.blur()
        }
    }

    async _searchDesktop(q) {
        try {
            const res = await fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`)
            const products = await res.json()
            this.resultsTarget.innerHTML = this._renderCards(products, q)
            this._show()
        } catch (e) {
            console.error('Erreur recherche globale', e)
        }
    }

    _show() {
        this.dropdownTarget.hidden = false
        document.addEventListener('click', this._closeHandler)
    }

    _hide() {
        this.dropdownTarget.hidden = true
        document.removeEventListener('click', this._closeHandler)
    }

    _onClickOutside(e) {
        if (!this.element.contains(e.target)) {
            this._hide()
        }
    }

    // ── Drawer mobile ──────────────────────────────────────

    openDrawer() {
        this.drawerTarget.classList.add('is-open')
        this.drawerTarget.setAttribute('aria-hidden', 'false')
        document.body.classList.add('no-scroll')
        this.drawerInputTarget.value = ''
        this.drawerResultsTarget.innerHTML = '<p class="search-drawer__hint">Tapez au moins 2 caractères pour rechercher…</p>'
        // Focus après l'animation
        setTimeout(() => this.drawerInputTarget.focus(), 50)
    }

    closeDrawer() {
        this.drawerTarget.classList.remove('is-open')
        this.drawerTarget.setAttribute('aria-hidden', 'true')
        document.body.classList.remove('no-scroll')
    }

    onDrawerInput() {
        clearTimeout(this._debounceTimer)
        const q = this.drawerInputTarget.value.trim()

        if (q.length < 2) {
            this.drawerResultsTarget.innerHTML = '<p class="search-drawer__hint">Tapez au moins 2 caractères pour rechercher…</p>'
            return
        }

        this._debounceTimer = setTimeout(() => this._searchDrawer(q), 300)
    }

    onDrawerKeydown(e) {
        if (e.key === 'Escape') this.closeDrawer()
    }

    async _searchDrawer(q) {
        this.drawerResultsTarget.innerHTML = '<p class="search-drawer__hint search-drawer__hint--loading">Recherche en cours…</p>'
        try {
            const res = await fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`)
            const products = await res.json()

            if (products.length === 0) {
                this.drawerResultsTarget.innerHTML = `<p class="search-drawer__hint">Aucun résultat pour « ${this._esc(q)} »</p>`
                return
            }

            this.drawerResultsTarget.innerHTML = `<div class="search-drawer__cards">${this._renderCards(products, q)}</div>`
        } catch (e) {
            console.error('Erreur recherche drawer', e)
            this.drawerResultsTarget.innerHTML = '<p class="search-drawer__hint">Une erreur est survenue.</p>'
        }
    }

    // ── Shared ─────────────────────────────────────────────

    _renderCards(products, q) {
        if (products.length === 0) {
            return `<p class="global-search__empty">Aucun résultat pour « ${this._esc(q)} »</p>`
        }

        return products.map(p => `
            <a href="${p.url}" class="search-result-card">
                <div class="search-result-card__img-wrap">
                    ${p.imageUrl
                        ? `<img src="${p.imageUrl}" alt="${this._esc(p.name)}" class="search-result-card__img" loading="lazy">`
                        : `<span class="search-result-card__no-img">📷</span>`
                    }
                </div>
                <div class="search-result-card__body">
                    <span class="search-result-card__name">${this._esc(p.name)}</span>
                    ${p.price ? `<span class="search-result-card__price">${this._esc(p.price)}</span>` : ''}
                </div>
            </a>
        `).join('')
    }

    _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
    }
}
