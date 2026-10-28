import { Controller } from '@hotwired/stimulus';

/*
 * "Translate with DeepL" buttons of the recipe form: sends one column to the server,
 * then fills the other column with the translation so the author can review it
 * before saving. Nothing is saved here.
 */
export default class extends Controller {
    static targets = ['status'];
    static values = { url: String, token: String, loading: String, done: String, incomplete: String, failed: String };

    async run(event) {
        const button = event.currentTarget;
        const { source, target } = event.params;
        const texts = Object.fromEntries(['title', 'ingredients', 'content'].map(field => [field, this.field(field, source).value]));

        if (Object.values(texts).every(text => text.trim() === '')) {
            return this.show(this.incompleteValue, 'danger');
        }

        button.disabled = true;
        this.show(this.loadingValue, 'muted');

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ _token: this.tokenValue, source, texts }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || this.failedValue);
            }

            for (const [field, text] of Object.entries(data.texts)) {
                const input = this.field(field, target);
                input.value = text;
                // Refresh the character counters.
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.show(this.doneValue, 'success');
            this.field('title', target).focus();
        } catch (error) {
            this.show(error.message || this.failedValue, 'danger');
        } finally {
            button.disabled = false;
        }
    }

    field(name, locale) {
        return this.element.querySelector(`#recipe_form_${name}${locale === 'en' ? 'En' : ''}`);
    }

    show(message, tone) {
        this.statusTarget.textContent = message;
        this.statusTarget.className = `translate-status small text-${tone}`;
    }
}
