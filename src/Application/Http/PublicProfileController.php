<?php

declare(strict_types=1);

/** Handles public cabinet settings and the shareable read-only collection page. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\PublicProfileManager;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Repository\GameRepository;
use GameTracker\Domain\Repository\MerchandiseRepository;
use GameTracker\Domain\Repository\UserRepository;
use InvalidArgumentException;

/** Publishes only deliberately selected profile and safe catalogue fields. */
final readonly class PublicProfileController
{
    /** Creates the controller with profile, collection, and rendering dependencies. */
    public function __construct(private UserRepository $users, private GameRepository $games, private MerchandiseRepository $merchandise, private PublicProfileManager $profiles, private CsrfToken $csrf, private string $settingsTemplate, private string $publicTemplate) {}

    /** Processes the signed-in owner's cabinet preferences and photograph upload. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input @param array<string,mixed> $files */
    public function settings(User $user, array $server, array $query, array $input, array $files): void
    {
        $errors=[];
        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string)$input['_token'] : null)) $errors[]='Your session expired. Refresh and try again.';
            else try {
                if (($input['profile_action'] ?? 'save') === 'remove-image') $this->profiles->removeImage($user);
                else $this->profiles->update($user,(string)($input['display_name'] ?? ''),(string)($input['bio'] ?? ''),isset($input['profile_public']),isset($files['profile_image'])&&is_array($files['profile_image'])?$files['profile_image']:null);
            } catch (InvalidArgumentException $exception) { $errors[]=$exception->getMessage(); }
            if ($errors===[]) { header('Location: /?route=profile-settings&saved=1',true,303); return; }
        }
        $csrfToken=$this->csrf->value(); $saved=isset($query['saved']);
        require $this->settingsTemplate;
    }

    /** Renders an opt-in public cabinet or a privacy-safe unavailable response. @param array<string,mixed> $query */
    public function cabinet(array $query): void
    {
        $username=trim((string)($query['user'] ?? ''));
        $user=$username === '' ? null : $this->users->findByUsername($username);
        if ($user === null || !$user->profilePublic()) { http_response_code(404); require $this->publicTemplate; return; }
        $search=mb_strtolower(trim((string)($query['q'] ?? ''))); $section=(string)($query['section'] ?? 'all');
        if (!in_array($section,['all','games','merchandise'],true)) $section='all';
        $games=array_values(array_filter($this->games->all((int)$user->id()),static fn($game): bool=>$search===''||str_contains(mb_strtolower($game->title().' '.$game->platform()),$search)));
        $merchandise=array_values(array_filter($this->merchandise->all((int)$user->id()),static fn($item): bool=>$search===''||str_contains(mb_strtolower($item->name().' '.$item->franchise().' '.$item->category()->label()),$search)));
        $completed=count(array_filter($games,static fn($game): bool=>$game->progress()===100));
        require $this->publicTemplate;
    }

    /** Streams a photograph when the owner is public or currently signed in. */
    public function image(User $owner, ?User $viewer): void
    {
        if (!$owner->profilePublic() && ($viewer === null || $viewer->id() !== $owner->id())) { http_response_code(404); return; }
        $path=$this->profiles->pathFor($owner);
        if ($path===null) { http_response_code(404); return; }
        header('Content-Type: '.(new \finfo(FILEINFO_MIME_TYPE))->file($path)); header('Cache-Control: public, max-age=3600'); header('X-Content-Type-Options: nosniff'); readfile($path);
    }
}
