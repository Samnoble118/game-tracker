<?php

declare(strict_types=1);

/**
 * Validates and stores private dashboard images and appearance preferences.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use InvalidArgumentException;

final readonly class DashboardCustomizer
{
    private const MAX_FILE_SIZE = 5_242_880;

    public function __construct(
        private UserRepository $users,
        private string $uploadPath,
    ) {
    }

    /** @param array<string, mixed>|null $upload */
    public function update(User $user, string $mode, int $overlay, ?array $upload): void
    {
        $filename = $user->dashboardImage();

        if ($upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $filename = $this->storeUpload($user, $upload);
        }

        $user->updateDashboardAppearance($filename, $mode, $overlay);
        $this->users->save($user);
    }

    public function remove(User $user): void
    {
        $this->deleteExisting($user);
        $user->updateDashboardAppearance(null, 'banner', 55);
        $this->users->save($user);
    }

    public function pathFor(User $user): ?string
    {
        $filename = $user->dashboardImage();
        if ($filename === null || basename($filename) !== $filename) {
            return null;
        }

        $path = $this->uploadPath . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    /** @param array<string, mixed> $upload */
    private function storeUpload(User $user, array $upload): string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The image could not be uploaded. Please try again.');
        }

        $size = (int) ($upload['size'] ?? 0);
        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        if ($size < 1 || $size > self::MAX_FILE_SIZE || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Choose a valid image no larger than 5 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime]) || getimagesize($temporaryPath) === false) {
            throw new InvalidArgumentException('Only JPEG, PNG, and WebP images are supported.');
        }

        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0750, true) && !is_dir($this->uploadPath)) {
            throw new InvalidArgumentException('The upload directory is unavailable.');
        }

        $this->deleteExisting($user);
        $filename = sprintf('user-%d-%s.%s', $user->id(), bin2hex(random_bytes(12)), $extensions[$mime]);
        if (!move_uploaded_file($temporaryPath, $this->uploadPath . '/' . $filename)) {
            throw new InvalidArgumentException('The image could not be saved.');
        }

        return $filename;
    }

    private function deleteExisting(User $user): void
    {
        $path = $this->pathFor($user);
        if ($path !== null) {
            unlink($path);
        }
    }
}
