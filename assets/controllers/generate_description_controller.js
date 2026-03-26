import { Controller } from '@hotwired/stimulus';

const STEPS = [
    { icon: '🔍', label: 'Recherche sur Fragrantica…' },
    { icon: '📖', label: 'Lecture des notes olfactives…' },
    { icon: '✍️', label: 'Rédaction de la description…' },
    { icon: '🏭', label: 'Recherche des infos fabricant…' },
];

export default class extends Controller {
    static values = { url: String };
    static targets = ['button', 'textarea', 'spinner', 'overlay', 'overlayIcon', 'overlayLabel'];

    #stepInterval = null;

    async generate() {
        this.buttonTarget.disabled = true;
        this.spinnerTarget.hidden = false;
        this.overlayTarget.hidden = false;
        this.#startSteps();

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const data = await response.json();

            if (response.status === 429) {
                throw new Error(data.error ?? 'Quota IA dépassé, réessayez plus tard.');
            }

            if (!response.ok) {
                throw new Error(data.error ?? 'Erreur serveur');
            }

            this.textareaTarget.value = data.description;
            this.textareaTarget.dispatchEvent(new Event('input'));
        } catch (err) {
            alert(err.message);
        } finally {
            this.#stopSteps();
            this.buttonTarget.disabled = false;
            this.spinnerTarget.hidden = true;
            this.overlayTarget.hidden = true;
        }
    }

    #startSteps() {
        let i = 0;
        this.#showStep(STEPS[0]);
        this.#stepInterval = setInterval(() => {
            i = (i + 1) % STEPS.length;
            this.#showStep(STEPS[i]);
        }, 3000);
    }

    #showStep({ icon, label }) {
        this.overlayIconTarget.textContent = icon;
        this.overlayLabelTarget.textContent = label;
    }

    #stopSteps() {
        clearInterval(this.#stepInterval);
        this.#stepInterval = null;
    }
}
