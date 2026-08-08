<?php

declare(strict_types=1);

/**
 * Validates and stores private dashboard images and appearance preferences.
 */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use InvalidArgumentException;

/** Manages per-user dashboard artwork and display preferences. */
final readonly class DashboardCustomizer
{
    public const PRESETS = [
        'archive-purple'=>['label'=>'Archive Purple','accent'=>'#7c5cff','background'=>'#0b0d12','panel'=>'#141821','text'=>'#f5f7fb'],
        'playstation-blue'=>['label'=>'PlayStation Blue','accent'=>'#3975f6','background'=>'#080d18','panel'=>'#111b2d','text'=>'#f4f7ff'],
        'xbox-green'=>['label'=>'Xbox Green','accent'=>'#56b847','background'=>'#08110b','panel'=>'#121d15','text'=>'#f4fff5'],
        'nintendo-red'=>['label'=>'Nintendo Red','accent'=>'#e60012','background'=>'#14090b','panel'=>'#241317','text'=>'#fff5f6'],
        'sonic-blue'=>['label'=>'Sonic Blue','accent'=>'#2788f5','background'=>'#07111f','panel'=>'#102239','text'=>'#f2f8ff'],
        'retro-neon'=>['label'=>'Retro Neon','accent'=>'#ff3fd1','background'=>'#090711','panel'=>'#171126','text'=>'#f9f2ff'],
    ];
    private const MAX_FILE_SIZE = 5_242_880;
    private const MAX_IMAGE_DIMENSION = 8000;
    private const MAX_IMAGE_PIXELS = 40_000_000;

    /** Creates the service with user persistence and private upload storage. */
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
            $filename = $this->storeUpload($user, $upload, 'dashboard');
        }

        $user->updateDashboardAppearance($filename, $mode, $overlay);
        $this->users->save($user);
    }

    /** Removes custom artwork and restores default appearance settings. */
    public function remove(User $user): void
    {
        $this->deleteExisting($user);
        $user->updateDashboardAppearance(null, 'banner', 55);
        $this->users->save($user);
    }

    /** Updates merchandise artwork and safely stores an optional replacement image. @param array<string, mixed>|null $upload */
    public function updateMerchandise(User $user, string $mode, int $overlay, ?array $upload): void
    {
        $filename = $user->merchandiseImage();
        if ($upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $filename = $this->storeUpload($user, $upload, 'merchandise');
        }
        $user->updateMerchandiseAppearance($filename, $mode, $overlay);
        $this->users->save($user);
    }

    /** Removes custom merchandise artwork and restores its defaults. */
    public function removeMerchandise(User $user): void
    {
        $this->deleteMerchandiseExisting($user);
        $user->updateMerchandiseAppearance(null, 'banner', 55);
        $this->users->save($user);
    }

    /** Updates Franchise Atlas artwork and safely stores an optional replacement image. @param array<string, mixed>|null $upload */
    public function updateFranchise(User $user, string $mode, int $overlay, ?array $upload): void
    {
        $filename = $user->franchiseImage();
        if ($upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $filename = $this->storeUpload($user, $upload, 'franchise');
        }
        $user->updateFranchiseAppearance($filename, $mode, $overlay);
        $this->users->save($user);
    }

    /** Removes custom Franchise Atlas artwork and restores its defaults. */
    public function removeFranchise(User $user): void
    {
        $this->deleteFranchiseExisting($user);
        $user->updateFranchiseAppearance(null, 'banner', 55);
        $this->users->save($user);
    }

    /** Applies a preset or validated custom colour palette. */
    public function updateTheme(User $user, string $preset, string $accent, string $background, string $panel, string $text, string $density): void
    {
        if ($preset !== 'custom') {
            if (!isset(self::PRESETS[$preset])) throw new InvalidArgumentException('Choose a valid theme preset.');
            ['accent'=>$accent,'background'=>$background,'panel'=>$panel,'text'=>$text] = self::PRESETS[$preset];
        }
        $user->updateTheme($preset,$accent,$background,$panel,$text,$density);
        $this->users->save($user);
    }

    /** Restores the default ArchiveXP colour palette and spacing. */
    public function resetTheme(User $user): void
    {
        $preset = self::PRESETS['archive-purple'];
        $user->updateTheme('archive-purple',$preset['accent'],$preset['background'],$preset['panel'],$preset['text'],'spacious');
        $this->users->save($user);
    }

    /** Resolves a safe private artwork path for the supplied user. */
    public function pathFor(User $user): ?string
    {
        return $this->pathForFilename($user->dashboardImage());
    }

    /** Resolves the private merchandise artwork path for the supplied user. */
    public function merchandisePathFor(User $user): ?string
    {
        return $this->pathForFilename($user->merchandiseImage());
    }

    /** Resolves the private Franchise Atlas artwork path for the supplied user. */
    public function franchisePathFor(User $user): ?string
    {
        return $this->pathForFilename($user->franchiseImage());
    }

    /** Resolves a safe private path for a stored artwork filename. */
    private function pathForFilename(?string $filename): ?string
    {
        if ($filename === null || basename($filename) !== $filename) {
            return null;
        }

        $path = $this->uploadPath . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    /** @param array<string, mixed> $upload */
    private function storeUpload(User $user, array $upload, string $target): string
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
        $dimensions = getimagesize($temporaryPath);
        if (!is_string($mime) || !isset($extensions[$mime]) || $dimensions === false) {
            throw new InvalidArgumentException('Only JPEG, PNG, and WebP images are supported.');
        }
        if ($dimensions[0] > self::MAX_IMAGE_DIMENSION
            || $dimensions[1] > self::MAX_IMAGE_DIMENSION
            || $dimensions[0] * $dimensions[1] > self::MAX_IMAGE_PIXELS) {
            throw new InvalidArgumentException('The image dimensions are too large.');
        }

        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0750, true) && !is_dir($this->uploadPath)) {
            throw new InvalidArgumentException('The upload directory is unavailable.');
        }

        match ($target) {
            'merchandise' => $this->deleteMerchandiseExisting($user),
            'franchise' => $this->deleteFranchiseExisting($user),
            default => $this->deleteExisting($user),
        };
        $filename = sprintf('user-%d-%s-%s.%s', $user->id(), $target, bin2hex(random_bytes(12)), $extensions[$mime]);
        if (!move_uploaded_file($temporaryPath, $this->uploadPath . '/' . $filename)) {
            throw new InvalidArgumentException('The image could not be saved.');
        }

        return $filename;
    }

    /** Deletes the user's previous artwork file when it exists. */
    private function deleteExisting(User $user): void
    {
        $path = $this->pathFor($user);
        if ($path !== null) {
            unlink($path);
        }
    }

    /** Deletes the user's previous merchandise artwork file when it exists. */
    private function deleteMerchandiseExisting(User $user): void
    {
        $path = $this->merchandisePathFor($user);
        if ($path !== null) {
            unlink($path);
        }
    }

    /** Deletes the user's previous Franchise Atlas artwork file when it exists. */
    private function deleteFranchiseExisting(User $user): void
    {
        $path = $this->franchisePathFor($user);
        if ($path !== null) {
            unlink($path);
        }
    }
}
