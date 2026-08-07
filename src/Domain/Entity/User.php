<?php

declare(strict_types=1);

/**
 * Contains the authenticated user identity.
 */

namespace GameTracker\Domain\Entity;

use InvalidArgumentException;

/** Represents a registered user's identity and dashboard preferences. */
final class User
{
    /** Creates a user, optionally restored with persisted account settings. */
    public function __construct(
        private string $email,
        private string $passwordHash,
        private ?int $id = null,
        private ?string $username = null,
        private ?string $dashboardImage = null,
        private string $dashboardImageMode = 'banner',
        private int $dashboardOverlay = 55,
    ) {
        $this->updateEmail($this->email);
        $this->updateUsername($this->username);
        $this->updateDashboardAppearance(
            $this->dashboardImage,
            $this->dashboardImageMode,
            $this->dashboardOverlay,
        );
    }

    /** Returns the persisted identifier or null for a new account. */
    public function id(): ?int
    {
        return $this->id;
    }

    /** Returns the normalized email address. */
    public function email(): string
    {
        return $this->email;
    }

    /** Returns the securely hashed password. */
    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /** Returns the optional public username. */
    public function username(): ?string
    {
        return $this->username;
    }

    /** Returns the username, falling back to the email address. */
    public function displayName(): string
    {
        return $this->username ?? $this->email;
    }

    /** Returns the private dashboard-image filename. */
    public function dashboardImage(): ?string
    {
        return $this->dashboardImage;
    }

    /** Returns whether artwork is displayed as a banner or wallpaper. */
    public function dashboardImageMode(): string
    {
        return $this->dashboardImageMode;
    }

    /** Returns the dashboard artwork overlay percentage. */
    public function dashboardOverlay(): int
    {
        return $this->dashboardOverlay;
    }

    /** Validates and updates dashboard artwork settings. */
    public function updateDashboardAppearance(?string $image, string $mode, int $overlay): void
    {
        if (!in_array($mode, ['banner', 'wallpaper'], true)) {
            throw new InvalidArgumentException('Choose either banner or wallpaper mode.');
        }

        if ($overlay < 20 || $overlay > 90) {
            throw new InvalidArgumentException('Overlay strength must be between 20 and 90.');
        }

        $this->dashboardImage = $image;
        $this->dashboardImageMode = $mode;
        $this->dashboardOverlay = $overlay;
    }

    /** Validates and stores a normalized email address. */
    public function updateEmail(string $email): void
    {
        $email = strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        $this->email = $email;
    }

    /** Validates and stores an optional username. */
    public function updateUsername(?string $username): void
    {
        $username = $username === null ? null : trim($username);

        if ($username === '') {
            $username = null;
        }

        if ($username !== null && preg_match('/^[A-Za-z0-9_-]{3,30}$/', $username) !== 1) {
            throw new InvalidArgumentException(
                'The username must be 3–30 characters using letters, numbers, underscores, or hyphens.'
            );
        }

        $this->username = $username;
    }

    /** Replaces the stored password hash. */
    public function updatePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    /** Assigns the persisted identifier once. */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('The user already has an ID.');
        }

        $this->id = $id;
    }
}
