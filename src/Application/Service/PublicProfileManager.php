<?php

declare(strict_types=1);

/** Validates public cabinet settings and securely stores profile photographs. */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\UserRepository;
use InvalidArgumentException;

/** Manages opt-in public profile data and private image storage. */
final readonly class PublicProfileManager
{
    private const MAX_FILE_SIZE = 5_242_880;

    /** Creates the manager with user persistence and non-public upload storage. */
    public function __construct(private UserRepository $users, private string $uploadPath) {}

    /** Updates profile text, visibility, and an optional replacement photograph. @param array<string,mixed>|null $upload */
    public function update(User $user, string $displayName, string $bio, bool $public, ?array $upload): void
    {
        $filename = $user->profileImage();
        if ($upload !== null && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $filename = $this->store($user, $upload);
        $user->updatePublicProfile($displayName, $bio, $public, $filename);
        $this->users->save($user);
    }

    /** Removes the stored photograph while retaining the other profile settings. */
    public function removeImage(User $user): void
    {
        $path = $this->pathFor($user);
        if ($path !== null && is_file($path)) unlink($path);
        $user->updatePublicProfile($user->profileDisplayName(), $user->profileBio(), $user->profilePublic(), null);
        $this->users->save($user);
    }

    /** Resolves the validated private path of a user's profile photograph. */
    public function pathFor(User $user): ?string
    {
        $filename = $user->profileImage();
        if ($filename === null || basename($filename) !== $filename) return null;
        $path = $this->uploadPath . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    /** Validates and moves an uploaded photograph into private storage. @param array<string,mixed> $upload */
    private function store(User $user, array $upload): string
    {
        if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('The profile photograph could not be uploaded.');
        $temporary = (string)($upload['tmp_name'] ?? '');
        if (!is_uploaded_file($temporary) || (int)($upload['size'] ?? 0) > self::MAX_FILE_SIZE) throw new InvalidArgumentException('Choose a profile photograph no larger than 5 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $dimensions = @getimagesize($temporary);
        if (!isset($extensions[$mime]) || $dimensions === false || $dimensions[0] > 8000 || $dimensions[1] > 8000 || $dimensions[0] * $dimensions[1] > 40_000_000) throw new InvalidArgumentException('Choose a valid JPEG, PNG, or WebP photograph.');
        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0700, true) && !is_dir($this->uploadPath)) throw new InvalidArgumentException('Profile image storage is unavailable.');
        $filename = 'profile-' . $user->id() . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($temporary, $this->uploadPath . '/' . $filename)) throw new InvalidArgumentException('The profile photograph could not be stored.');
        $old = $this->pathFor($user);
        if ($old !== null) unlink($old);
        return $filename;
    }
}
