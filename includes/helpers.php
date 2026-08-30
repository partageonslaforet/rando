<?php

/**
 * Fonction de sécurisation des chaînes de caractères pour l'affichage HTML
 * 
 * @param string|null $string La chaîne à sécuriser
 * @return string La chaîne sécurisée
 */
function h($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
