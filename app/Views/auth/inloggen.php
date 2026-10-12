<?php

declare(strict_types=1);

/**
 * Inlogpagina voor beheerders en standhouders.
 *
 * @var string      $foutmelding  Melding bij een mislukte inlogpoging.
 * @var string      $gebruikersnaam De laatst ingevulde gebruikersnaam.
 */
?>
<section class="pagina-kop">
    <div class="container container--smal">
        <p class="pagina-kop__label">Account</p>
        <h1 class="pagina-kop__titel">Inloggen</h1>
        <p class="pagina-kop__tekst">
            Log in met je gebruikersnaam en wachtwoord om bij je eigen account te komen. Beheerders
            beheren de stands, standhouders reserveren een plek voor hun producten.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container container--smal">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'Niet ingelogd',
                'tekst' => $foutmelding,
            ]) ?>
        <?php endif; ?>

        <form class="formulier" method="post" action="<?= e(url('/inloggen')) ?>">
            <div class="formulier__veld">
                <label class="formulier__label" for="gebruikersnaam">Gebruikersnaam</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="gebruikersnaam"
                    name="gebruikersnaam"
                    value="<?= e($gebruikersnaam) ?>"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="wachtwoord">Wachtwoord</label>
                <input
                    class="formulier__invoer"
                    type="password"
                    id="wachtwoord"
                    name="wachtwoord"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Inloggen</button>
            </div>
        </form>

        <p class="formulier__verplicht">
            Nog geen account? Vraag je inloggegevens aan bij
            <a href="mailto:stands@sneakerness.nl">stands@sneakerness.nl</a>.
        </p>
    </div>
</section>
