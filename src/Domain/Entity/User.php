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
        private ?string $merchandiseImage = null,
        private string $merchandiseImageMode = 'banner',
        private int $merchandiseOverlay = 55,
        private ?string $franchiseImage = null,
        private string $franchiseImageMode = 'banner',
        private int $franchiseOverlay = 55,
        private string $themePreset = 'archive-purple',
        private string $themeAccent = '#7c5cff',
        private string $themeBackground = '#0b0d12',
        private string $themePanel = '#141821',
        private string $themeText = '#f5f7fb',
        private string $layoutDensity = 'spacious',
        private string $profileDisplayName = '',
        private string $profileBio = '',
        private bool $profilePublic = false,
        private ?string $profileImage = null,
    ) {
        $this->updateEmail($this->email);
        $this->updateUsername($this->username);
        $this->updateDashboardAppearance(
            $this->dashboardImage,
            $this->dashboardImageMode,
            $this->dashboardOverlay,
        );
        $this->updateMerchandiseAppearance(
            $this->merchandiseImage,
            $this->merchandiseImageMode,
            $this->merchandiseOverlay,
        );
        $this->updateFranchiseAppearance($this->franchiseImage, $this->franchiseImageMode, $this->franchiseOverlay);
        $this->updateTheme($this->themePreset, $this->themeAccent, $this->themeBackground, $this->themePanel, $this->themeText, $this->layoutDensity);
        $this->updatePublicProfile($this->profileDisplayName, $this->profileBio, $this->profilePublic, $this->profileImage);
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

    /** Returns the collector name shown on the public cabinet. */
    public function profileDisplayName(): string { return $this->profileDisplayName !== '' ? $this->profileDisplayName : $this->displayName(); }

    /** Returns the short biography shown on the public cabinet. */
    public function profileBio(): string { return $this->profileBio; }

    /** Reports whether the shareable cabinet may be viewed without signing in. */
    public function profilePublic(): bool { return $this->profilePublic; }

    /** Returns the private profile-image filename. */
    public function profileImage(): ?string { return $this->profileImage; }

    /** Validates and replaces public profile preferences. */
    public function updatePublicProfile(string $displayName, string $bio, bool $public, ?string $image): void
    {
        $displayName = trim($displayName);
        $bio = trim($bio);
        if (mb_strlen($displayName) > 60) throw new InvalidArgumentException('Display name must be 60 characters or fewer.');
        if (mb_strlen($bio) > 300) throw new InvalidArgumentException('Bio must be 300 characters or fewer.');
        $this->profileDisplayName = $displayName;
        $this->profileBio = $bio;
        $this->profilePublic = $public;
        $this->profileImage = $image;
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

    /** Returns the private merchandise-image filename. */
    public function merchandiseImage(): ?string { return $this->merchandiseImage; }

    /** Returns whether merchandise artwork is a banner or wallpaper. */
    public function merchandiseImageMode(): string { return $this->merchandiseImageMode; }

    /** Returns the merchandise artwork overlay percentage. */
    public function merchandiseOverlay(): int { return $this->merchandiseOverlay; }

    /** Returns the private Franchise Atlas artwork filename. */
    public function franchiseImage(): ?string { return $this->franchiseImage; }

    /** Returns whether Franchise Atlas artwork is a banner or wallpaper. */
    public function franchiseImageMode(): string { return $this->franchiseImageMode; }

    /** Returns the Franchise Atlas artwork overlay percentage. */
    public function franchiseOverlay(): int { return $this->franchiseOverlay; }

    /** Returns the selected named theme or custom mode. */ public function themePreset(): string { return $this->themePreset; }
    /** Returns the theme accent colour. */ public function themeAccent(): string { return $this->themeAccent; }
    /** Returns the page background colour. */ public function themeBackground(): string { return $this->themeBackground; }
    /** Returns the panel background colour. */ public function themePanel(): string { return $this->themePanel; }
    /** Returns the primary text colour. */ public function themeText(): string { return $this->themeText; }
    /** Returns compact or spacious layout density. */ public function layoutDensity(): string { return $this->layoutDensity; }

    /** Validates and replaces persisted theme colours and layout density. */
    public function updateTheme(string $preset, string $accent, string $background, string $panel, string $text, string $density): void
    {
        if (!in_array($preset, ['archive-purple','playstation-blue','xbox-green','nintendo-red','sonic-blue','retro-neon','custom'], true)) throw new InvalidArgumentException('Choose a valid theme preset.');
        if (!in_array($density, ['spacious','compact'], true)) throw new InvalidArgumentException('Choose a valid layout density.');
        foreach ([$accent,$background,$panel,$text] as $colour) if (preg_match('/^#[0-9a-fA-F]{6}$/', $colour) !== 1) throw new InvalidArgumentException('Theme colours must use six-digit hex values.');
        if ($this->contrastRatio($text, $background) < 4.5 || $this->contrastRatio($text, $panel) < 4.5) throw new InvalidArgumentException('Text must have at least 4.5:1 contrast against the background and panels.');
        $this->themePreset=$preset; $this->themeAccent=strtolower($accent); $this->themeBackground=strtolower($background);
        $this->themePanel=strtolower($panel); $this->themeText=strtolower($text); $this->layoutDensity=$density;
    }

    /** Calculates WCAG contrast between two six-digit hex colours. */
    private function contrastRatio(string $first, string $second): float
    {
        $luminance = static function (string $hex): float {
            $parts = [hexdec(substr($hex,1,2))/255,hexdec(substr($hex,3,2))/255,hexdec(substr($hex,5,2))/255];
            $parts = array_map(static fn(float $value): float => $value <= .04045 ? $value/12.92 : (($value+.055)/1.055)**2.4, $parts);
            return .2126*$parts[0]+.7152*$parts[1]+.0722*$parts[2];
        };
        [$light,$dark] = [$luminance($first),$luminance($second)];
        if ($dark > $light) [$light,$dark] = [$dark,$light];
        return ($light+.05)/($dark+.05);
    }

    /** Validates and updates dashboard artwork settings. */
    public function updateDashboardAppearance(?string $image, string $mode, int $overlay): void
    {
        $this->validateAppearance($mode, $overlay);

        $this->dashboardImage = $image;
        $this->dashboardImageMode = $mode;
        $this->dashboardOverlay = $overlay;
    }

    /** Validates and updates merchandise artwork settings. */
    public function updateMerchandiseAppearance(?string $image, string $mode, int $overlay): void
    {
        $this->validateAppearance($mode, $overlay);

        $this->merchandiseImage = $image;
        $this->merchandiseImageMode = $mode;
        $this->merchandiseOverlay = $overlay;
    }

    /** Validates and updates Franchise Atlas artwork settings. */
    public function updateFranchiseAppearance(?string $image, string $mode, int $overlay): void
    {
        $this->validateAppearance($mode, $overlay);
        $this->franchiseImage = $image;
        $this->franchiseImageMode = $mode;
        $this->franchiseOverlay = $overlay;
    }

    /** Validates shared artwork display settings. */
    private function validateAppearance(string $mode, int $overlay): void
    {
        if (!in_array($mode, ['banner', 'wallpaper'], true)) {
            throw new InvalidArgumentException('Choose either banner or wallpaper mode.');
        }

        if ($overlay < 20 || $overlay > 90) {
            throw new InvalidArgumentException('Overlay strength must be between 20 and 90.');
        }

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
