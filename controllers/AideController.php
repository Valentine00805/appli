<?php
declare(strict_types=1);

/**
 * Les pages d'aide : des modes d'emploi pas à pas, illustrés, pour ce qui se fait hors de l'application.
 *
 * « aide/gemini » explique comment obtenir une clé API Gemini ; elle s'ouvre en fenêtre depuis la carte « Clé API Gemini » de
 * « Mon compte ».
 */
final class AideController
{
    public function gemini(): void
    {
        Auth::exiger();
        if (Vue::enFenetre()) {
            Vue::fragment('aide/gemini', []);
            return;
        }
        Vue::afficher('aide/gemini', [], t('aide.gemini.titre_page'));
    }
}
