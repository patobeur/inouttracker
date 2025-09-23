<?php

/**
 * Proxy minimal vers le backend.
 * Ce fichier est le seul point d'entrée PHP dans le docroot public.
 * Il charge le véritable point d'entrée de l'API qui se trouve hors du docroot.
 *
 * Avantages:
 * - Le code source du backend (logique métier, identifiants DB) est protégé.
 * - L'URL de l'API est propre (/api?action=...)
 */

require __DIR__ . '/../../backend/index.php';
