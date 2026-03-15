import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['grid', 'search', 'sentinel', 'count', 'spinner']
    static values = { url: String }

    connect() {
        this.page = 1
        this.query = this.searchTarget.value
        this.loading = false
        this.hasMore = this.element.dataset.hasMore === 'true'

        this._debounceTimer = null

        this._observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && !this.loading && this.hasMore) {
                this._loadMore()
            }
        }, { rootMargin: '200px' })

        if (this.hasMore) {
            this._observer.observe(this.sentinelTarget)
        }
    }

    disconnect() {
        this._observer.disconnect()
        clearTimeout(this._debounceTimer)
    }

    onSearch() {
        clearTimeout(this._debounceTimer)
        this._debounceTimer = setTimeout(() => {
            this.query = this.searchTarget.value
            this.page = 1
            this.hasMore = true
            this.gridTarget.innerHTML = ''
            this._observer.observe(this.sentinelTarget)
            this._loadMore()
        }, 200)
    }

    async _loadMore() {
        if (this.loading || !this.hasMore) return
        this.loading = true
        this.spinnerTarget.classList.add('is-loading')

        const params = new URLSearchParams({ page: this.page })
        if (this.query) params.set('q', this.query)

        try {
            const res = await fetch(`${this.urlValue}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            const data = await res.json()

            this.gridTarget.insertAdjacentHTML('beforeend', data.html)
            this.hasMore = data.hasMore
            this.page++

            if (!this.hasMore) {
                this._observer.unobserve(this.sentinelTarget)
            }

            if (this.hasCountTarget) {
                this.countTarget.textContent = `${data.total} produit${data.total !== 1 ? 's' : ''}`
            }
        } catch (e) {
            console.error('Erreur chargement produits', e)
        } finally {
            this.loading = false
            this.spinnerTarget.classList.remove('is-loading')
        }
    }
}
