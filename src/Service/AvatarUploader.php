<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre les photos de profil dans public/uploads/avatars/.
 *
 * Securite :
 *  - le type est verifie AVANT (contrainte Image du formulaire : vrai contenu du fichier, pas son nom) ;
 *  - le nom est aleatoire (impossible de deviner ou d'ecraser le fichier d'un autre) ;
 *  - l'image est RE-DESSINEE avec GD en JPEG 512x512 : ca supprime les donnees cachees
 *    (position GPS de la photo, script deguise en image...) et ca allege le fichier.
 *    Sans l'extension GD, on garde le fichier tel quel avec l'extension deduite de son vrai type.
 *  - on ne supprime QUE des fichiers de ce dossier (jamais un chemin envoye par l'utilisateur).
 */
final class AvatarUploader
{
    private const PUBLIC_PREFIX = '/uploads/avatars/';
    private const SIZE = 512;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        private readonly string $directory,
    ) {
    }

    /** @return string chemin public, ex : "/uploads/avatars/3f9c...e1.jpg" */
    public function upload(UploadedFile $file): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Impossible de creer le dossier des photos.');
        }

        $name = bin2hex(random_bytes(16));

        if (\function_exists('imagecreatefromstring')) {
            $source = @imagecreatefromstring((string) file_get_contents($file->getPathname()));
            if ($source === false) {
                throw new \InvalidArgumentException('Image illisible.');
            }

            // Recadrage carre au centre, puis redimensionnement en 512 x 512
            $w = imagesx($source);
            $h = imagesy($source);
            $side = min($w, $h);
            $out = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // fond blanc pour les PNG transparents
            imagecopyresampled($out, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
            imagejpeg($out, $this->directory . '/' . $name . '.jpg', 85);
            imagedestroy($source);
            imagedestroy($out);

            return self::PUBLIC_PREFIX . $name . '.jpg';
        }

        $extension = $file->guessExtension() ?: 'jpg'; // deduite du VRAI contenu (finfo), pas du nom envoye
        $file->move($this->directory, $name . '.' . $extension);

        return self::PUBLIC_PREFIX . $name . '.' . $extension;
    }

    /** Supprime une ancienne photo... seulement si c'est bien un fichier de notre dossier. */
    public function remove(?string $publicPath): void
    {
        if ($publicPath === null || !preg_match('#^/uploads/avatars/([a-f0-9]{32}\.(?:jpg|jpeg|png|webp))$#', $publicPath, $m)) {
            return; // ancien lien externe (https://...) ou valeur inattendue : on ne touche a rien
        }

        $path = $this->directory . '/' . $m[1];
        if (is_file($path)) {
            unlink($path);
        }
    }
}
