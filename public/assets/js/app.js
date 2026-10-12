/**
 * Kleine laag interactie voor de website.
 *
 * 1. Mobiel menu openen en sluiten (inclusief toegankelijkheidsattributen).
 * 2. De eventkiezer op de ticket- en standpagina direct versturen bij wijziging.
 */

(function () {
    'use strict';

    /** Mobiel menu. */
    var menuKnop = document.querySelector('.menu-knop');
    var hoofdmenu = document.getElementById('hoofdmenu');

    if (menuKnop && hoofdmenu) {
        menuKnop.addEventListener('click', function () {
            var isOpen = hoofdmenu.classList.toggle('is-open');

            menuKnop.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            menuKnop.querySelector('.visueel-verborgen').textContent = isOpen
                ? 'Menu sluiten'
                : 'Menu openen';
        });

        hoofdmenu.addEventListener('click', function (gebeurtenis) {
            if (gebeurtenis.target.closest('a') && window.innerWidth < 1100) {
                hoofdmenu.classList.remove('is-open');
                menuKnop.setAttribute('aria-expanded', 'false');
            }
        });

        /* Sluit het menu wanneer het scherm groot genoeg is voor de balkweergave. */
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1100) {
                hoofdmenu.classList.remove('is-open');
                menuKnop.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /** Eventkiezer: direct verversen zodra een andere editie gekozen wordt. */
    var kiezers = document.querySelectorAll('.kiezer__veld');

    Array.prototype.forEach.call(kiezers, function (kiezer) {
        kiezer.addEventListener('change', function () {
            if (kiezer.form) {
                kiezer.form.submit();
            }
        });
    });

    /**
     * Vergrendelt de verzendknoppen zodra een formulier verzonden wordt, zodat
     * een dubbele klik niet twee keer hetzelfde opslaat (bijvoorbeeld twee
     * identieke verkopers).
     *
     * De vergrendeling gebeurt in een timeout ná de submit zelf: zo gaat de
     * knop nog wél met naam en waarde het formulier in, maar kan er tijdens het
     * wachten op de server niet nog een keer geklikt worden.
     */
    document.addEventListener('submit', function (gebeurtenis) {
        var formulier = gebeurtenis.target;
        var knoppen = formulier.querySelectorAll('button[type="submit"]');

        window.setTimeout(function () {
            Array.prototype.forEach.call(knoppen, function (knop) {
                knop.disabled = true;
                knop.setAttribute('aria-busy', 'true');
            });
        }, 0);
    });
})();
