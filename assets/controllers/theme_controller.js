import { Controller } from '@hotwired/stimulus';

/*
 * Bascule clair/sombre. La détection initiale (avant tout rendu, pour éviter
 * un flash du mauvais thème) se fait par un script inline dans base.html.twig,
 * synchronisé avec la même clé localStorage — ce contrôleur ne gère que le
 * clic sur le bouton une fois la page chargée.
 */
export default class extends Controller {
    static targets = ['icon'];

    connect() {
        this.updateIcon(document.documentElement.getAttribute('data-theme'));
    }

    toggle() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('mecatips-theme', next);
        document.documentElement.setAttribute('data-theme', next);
        this.updateIcon(next);
    }

    // Le thème actif détermine l'icône (celle du thème vers lequel on
    // basculerait), et le libellé accessible qui va avec puisque le bouton
    // n'a plus de texte visible.
    updateIcon(theme) {
        if (this.hasIconTarget) {
            this.iconTarget.setAttribute('href', theme === 'dark' ? '#icon-sun' : '#icon-moon');
        }
        this.element.setAttribute('aria-label', theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre');
    }
}
