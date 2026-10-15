<?php
/**
 * Partial — Messages flash issus de la session.
 *
 * Variable facultative :
 *   $messages array<{type,message}> — sinon les messages en attente sont consommés
 */

declare(strict_types=1);

$messages = $messages ?? \Souriane\Core\Flash::recuperer();

if ($messages === []) {
    return;
}

$icones = [
    'succes'    => 'coche-cercle',
    'erreur'    => 'alerte',
    'info'      => 'info',
    'attention' => 'alerte',
];
?>
<div class="messages" role="status" aria-live="polite">
    <?php foreach ($messages as $message): ?>
        <?php
        $type = in_array($message['type'] ?? '', ['succes', 'erreur', 'info', 'attention'], true)
            ? $message['type']
            : 'info';
        ?>
        <div class="message message--<?= e($type) ?>">
            <?= \Souriane\Core\Icone::svg($icones[$type] ?? 'info') ?>
            <div><?= e((string) ($message['message'] ?? '')) ?></div>
        </div>
    <?php endforeach; ?>
</div>
