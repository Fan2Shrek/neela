import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'content'];

    open(event) {
        event.preventDefault();

        this.contentTarget.innerHTML = '';
        this.dialogTarget.showModal();
        this.load(event.currentTarget.href);
    }

    close() {
        this.dialogTarget.close();
    }

    backdropClose(event) {
        if (event.target === this.dialogTarget) {
            this.dialogTarget.close();
        }
    }

    async load(url) {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await response.text();

        this.contentTarget.innerHTML = new DOMParser().parseFromString(html, 'text/html').body.innerHTML;
    }
}
