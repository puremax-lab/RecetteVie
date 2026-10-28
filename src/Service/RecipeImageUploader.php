<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Stores recipe photos in public/uploads/recipes under a unique, safe file name.
 */
final class RecipeImageUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/recipes')]
        private readonly string $targetDirectory,
        private readonly SluggerInterface $slugger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string the stored file name, to save on the Recipe
     */
    public function upload(UploadedFile $file): string
    {
        $baseName = $this->slugger->slug(pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME))->lower()->truncate(40);
        $fileName = \sprintf('%s-%s.%s', $baseName, bin2hex(random_bytes(6)), $file->guessExtension() ?? 'jpg');

        $file->move($this->targetDirectory, $fileName);

        return $fileName;
    }

    public function remove(?string $fileName): void
    {
        if (null === $fileName || '' === $fileName) {
            return;
        }

        // basename() guarantees we never delete outside the upload folder.
        $this->filesystem->remove($this->targetDirectory.'/'.basename($fileName));
    }
}
