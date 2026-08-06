<?php

declare(strict_types=1);

/**
 * Validates and stores private cover artwork for library entries.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\Game;
use GameTracker\Domain\Repository\GameRepository;
use InvalidArgumentException;

final readonly class GameCoverManager
{
    private const MAX_FILE_SIZE = 5_242_880;

    public function __construct(private GameRepository $games, private string $uploadPath)
    {
    }

    /** @param array<string, mixed> $upload */
    public function upload(Game $game, array $upload): void
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The cover could not be uploaded. Please try again.');
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_FILE_SIZE || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Choose a valid cover image no larger than 5 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime]) || getimagesize($temporaryPath) === false) {
            throw new InvalidArgumentException('Cover images must be JPEG, PNG, or WebP files.');
        }

        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0750, true) && !is_dir($this->uploadPath)) {
            throw new InvalidArgumentException('The cover upload directory is unavailable.');
        }

        $this->deleteFile($game);
        $filename = sprintf('game-%d-user-%d-%s.%s', $game->id(), $game->userId(), bin2hex(random_bytes(12)), $extensions[$mime]);
        if (!move_uploaded_file($temporaryPath, $this->uploadPath . '/' . $filename)) {
            throw new InvalidArgumentException('The cover image could not be saved.');
        }

        $game->updateCoverImage($filename);
        $this->games->save($game);
    }

    public function remove(Game $game): void
    {
        $this->deleteFile($game);
        $game->updateCoverImage(null);
        $this->games->save($game);
    }

    public function pathFor(Game $game): ?string
    {
        $filename = $game->coverImage();
        if ($filename === null || basename($filename) !== $filename) {
            return null;
        }

        $path = $this->uploadPath . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    private function deleteFile(Game $game): void
    {
        $path = $this->pathFor($game);
        if ($path !== null) {
            unlink($path);
        }
    }
}
