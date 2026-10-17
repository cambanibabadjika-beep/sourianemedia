<?php
/**
 * SOURIANE — Service de gestion des téléversements (photos de pose, médias)
 */

declare(strict_types=1);

namespace Souriane\Services;

final class UploadService
{
    /** Extensions et types MIME autorisés pour les photos de pose */
    private const EXTENSIONS_IMAGES = ['jpg', 'jpeg', 'png', 'webp'];
    private const MIMES_IMAGES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** Taille maximale autorisée : 12 Mo */
    private const TAILLE_MAX = 12 * 1024 * 1024;

    /**
     * Enregistre une photo de preuve de pose téléversée.
     *
     * @param array $file Structure issue de $_FILES
     * @return string Chemin relatif enregistré pour la base (ex. storage/uploads/poses/pose-xxx.jpg)
     * @throws \InvalidArgumentException Si le fichier est invalide
     */
    public static function enregistrerPhotoPose(array $file): string
    {
        $estFichierUpload = is_uploaded_file((string) ($file['tmp_name'] ?? ''))
            || (php_sapi_name() === 'cli' && is_file((string) ($file['tmp_name'] ?? '')));

        if (empty($file['tmp_name']) || !$estFichierUpload) {
            throw new \InvalidArgumentException('Aucun fichier valide n’a été téléversé.');
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Erreur lors du transfert du fichier (code ' . ($file['error'] ?? 0) . ').');
        }

        if ((int) ($file['size'] ?? 0) > self::TAILLE_MAX) {
            throw new \InvalidArgumentException('La photo dépasse la taille maximale autorisée (12 Mo).');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        if (!in_array($extension, self::EXTENSIONS_IMAGES, true)) {
            throw new \InvalidArgumentException('Format de photo non autorisé. Formats acceptés : JPG, PNG, WEBP.');
        }

        // Vérification du type MIME réel
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, (string) $file['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$mime || !in_array($mime, self::MIMES_IMAGES, true)) {
            throw new \InvalidArgumentException('Le contenu du fichier n’est pas une image valide.');
        }

        $dossier = UPLOAD_PATH . '/poses';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $nomFichier = sprintf(
            'pose-%s-%s.%s',
            date('Ymd-His'),
            bin2hex(random_bytes(4)),
            $extension
        );

        $destination = $dossier . '/' . $nomFichier;

        $succes = move_uploaded_file((string) $file['tmp_name'], $destination)
            || (php_sapi_name() === 'cli' && copy((string) $file['tmp_name'], $destination));

        if (!$succes) {
            throw new \RuntimeException('Impossible de sauvegarder la photo sur le serveur.');
        }

        return 'storage/uploads/poses/' . $nomFichier;
    }
}
