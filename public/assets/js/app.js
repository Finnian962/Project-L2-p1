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
    /**
     * Client-side controle van het formulier Event toevoegen: lege verplichte
     * velden en een ongeldige datum of een ongeldig aantal.
     */
    var eventFormulier = document.getElementById('event-toevoegen-formulier');

    if (eventFormulier) {
        eventFormulier.addEventListener('submit', function (gebeurtenis) {
            var velden = {
                naam: 'Vul een naam in.',
                datum: 'Vul een datum in.',
                locatie: 'Vul een locatie in.',
                aantal_tickets_per_tijdslot: 'Vul het aantal tickets per tijdslot in.',
                beschikbare_stands: 'Vul het aantal beschikbare stands in.'
            };
            var fouten = {};
            var sleutel;
            var invoer;
            var waarde;
            var datumPatroon = /^\d{4}-\d{2}-\d{2}$/;
            var getalPatroon = /^\d+$/;
            var heeftVerplicht = false;
            var heeftOngeldig = false;

            for (sleutel in velden) {
                if (!Object.prototype.hasOwnProperty.call(velden, sleutel)) {
                    continue;
                }

                invoer = eventFormulier.querySelector('[name="' + sleutel + '"]');
                waarde = invoer ? invoer.value.trim() : '';

                if (waarde === '') {
                    fouten[sleutel] = velden[sleutel];
                    heeftVerplicht = true;
                }
            }

            invoer = eventFormulier.querySelector('[name="datum"]');
            waarde = invoer ? invoer.value.trim() : '';

            if (waarde !== '') {
                if (!datumPatroon.test(waarde) || isNaN(Date.parse(waarde + 'T00:00:00'))) {
                    fouten.datum = 'Vul een geldige datum in, bijvoorbeeld 2026-11-14.';
                    heeftOngeldig = true;
                } else {
                    var delen = waarde.split('-');
                    var geparsed = new Date(Number(delen[0]), Number(delen[1]) - 1, Number(delen[2]));

                    if (
                        geparsed.getFullYear() !== Number(delen[0])
                        || geparsed.getMonth() !== Number(delen[1]) - 1
                        || geparsed.getDate() !== Number(delen[2])
                    ) {
                        fouten.datum = 'Vul een geldige datum in, bijvoorbeeld 2026-11-14.';
                        heeftOngeldig = true;
                    }
                }
            }

            ['aantal_tickets_per_tijdslot', 'beschikbare_stands'].forEach(function (naam) {
                invoer = eventFormulier.querySelector('[name="' + naam + '"]');
                waarde = invoer ? invoer.value.trim() : '';

                if (waarde !== '' && !getalPatroon.test(waarde)) {
                    fouten[naam] = naam === 'beschikbare_stands'
                        ? 'Vul een geldig aantal stands in, bijvoorbeeld 30.'
                        : 'Vul een geldig aantal tickets in, bijvoorbeeld 250.';
                    heeftOngeldig = true;
                }
            });

            if (Object.keys(fouten).length === 0) {
                return;
            }

            gebeurtenis.preventDefault();
            toonFormulierFouten(eventFormulier, fouten, heeftVerplicht
                ? 'Vul de gemarkeerde verplichte velden in.'
                : 'Controleer de gemarkeerde velden. Er is een ongeldige waarde ingevuld.');
        });
    }

    function toonFormulierFouten(formulier, fouten, kopTekst) {
        var bestaande = formulier.parentNode.querySelector('.melding--fout');
        var melding;
        var sleutel;
        var veld;
        var foutEl;
        var id;

        if (bestaande) {
            bestaande.remove();
        }

        melding = document.createElement('div');
        melding.className = 'melding melding--fout';
        melding.setAttribute('role', 'alert');
        melding.innerHTML = '<span class="melding__icoon" aria-hidden="true">×</span>'
            + '<div class="melding__inhoud">'
            + '<p class="melding__kop">Het event is niet opgeslagen</p>'
            + '<p class="melding__tekst">' + kopTekst + '</p>'
            + '</div>';
        formulier.parentNode.insertBefore(melding, formulier);

        Array.prototype.forEach.call(formulier.querySelectorAll('[aria-invalid]'), function (invoer) {
            invoer.removeAttribute('aria-invalid');
            invoer.removeAttribute('aria-describedby');
        });
        Array.prototype.forEach.call(formulier.querySelectorAll('.formulier__fout'), function (el) {
            el.remove();
        });

        for (sleutel in fouten) {
            if (!Object.prototype.hasOwnProperty.call(fouten, sleutel)) {
                continue;
            }

            veld = formulier.querySelector('[name="' + sleutel + '"]');

            if (!veld) {
                continue;
            }

            id = sleutel + '-fout';
            veld.setAttribute('aria-invalid', 'true');
            veld.setAttribute('aria-describedby', id);
            foutEl = document.createElement('p');
            foutEl.className = 'formulier__fout';
            foutEl.id = id;
            foutEl.setAttribute('role', 'alert');
            foutEl.textContent = fouten[sleutel];
            veld.parentNode.appendChild(foutEl);
        }

        melding.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.addEventListener('submit', function (gebeurtenis) {
        if (gebeurtenis.defaultPrevented) {
            return;
        }

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
