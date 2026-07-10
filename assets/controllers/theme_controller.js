import { Controller } from '@hotwired/stimulus';

/*
 * Bascule clair/sombre. La détection initiale (avant tout rendu, pour éviter
 * un flash du mauvais thème) se fait par un script inline dans base.html.twig,
 * synchronisé avec la même clé localStorage — ce contrôleur ne gère que le
 * clic sur le bouton une fois la page chargée.
 */
export default class extends Controller {
    static targets = ['label'];

    connect() {
        this.updateLabel(document.documentElement.getAttribute('data-theme'));
    }

    toggle() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('mecatips-theme', next);
        document.documentElement.setAttribute('data-theme', next);
        this.updateLabel(next);
    }

    updateLabel(theme) {
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = theme === 'dark' ? 'Mode clair' : 'Mode sombre';
        }
    }
}
