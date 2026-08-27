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
        const doc = new DOMParser().parseFromString(html, 'text/html');

        // Pages render inside the sidebar layout's <main class="app__content">; grabbing
        // <body> as a whole would pull the sidebar into the modal along with it.
        const content = doc.querySelector('.app__content');

        this.contentTarget.innerHTML = (content ?? doc.body).innerHTML;
    }
}
