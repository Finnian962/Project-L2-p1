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
})();
