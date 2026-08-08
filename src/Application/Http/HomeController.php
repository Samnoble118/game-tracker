<?php

declare(strict_types=1);

/** Handles the authenticated Collection HQ landing page. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\CollectionDashboard;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;

/** Renders a personalised overview before users enter a collection section. */
final readonly class HomeController
{
    /** Creates the controller with overview, security, user, and template dependencies. */
    public function __construct(private CollectionDashboard $dashboard,private CsrfToken $csrf,private User $user,private string $templatePath) {}

    /** Renders the signed-in home dashboard. */
    public function index(): void
    {
        $overview=$this->dashboard->overview();$csrfToken=$this->csrf->value();$currentUser=$this->user;
        require $this->templatePath;
    }
}
