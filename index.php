<?php
/**
 * SOURIANE — Point d’entrée unique
 *
 * Toutes les requêtes sont réécrites vers ce fichier par .htaccess
 * (ou par le routeur du serveur PHP intégré en développement).
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use Souriane\Core\Request;
use Souriane\Core\Router;

$routeur  = new Router();
$requete  = Request::capture();

require __DIR__ . '/app/routes.php';

$reponse = $routeur->dispatch($requete);
$reponse->envoyer();
