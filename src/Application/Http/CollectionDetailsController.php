<?php

declare(strict_types=1);

/** Handles private collection-detail pages for games and merchandise. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\CollectionDetails;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\ItemCondition;
use GameTracker\Domain\Enum\ItemPackaging;
use InvalidArgumentException;
use ValueError;

/** Renders and updates user-scoped catalogue and ownership information. */
final readonly class CollectionDetailsController
{
    /** Creates the controller with application, security, and view dependencies. */
    public function __construct(private CollectionDetails $details, private CsrfToken $csrf, private User $currentUser, private string $templatePath) {}

    /** Processes a details request. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input */
    public function handle(array $server, array $query, array $input): void
    {
        try {
            $type = CollectionItemType::from((string)($input['type'] ?? $query['type'] ?? ''));
        } catch (ValueError) {
            http_response_code(404); echo 'Collection item not found.'; return;
        }
        $itemId = (int)($input['item_id'] ?? $query['id'] ?? 0);
        $subject = $this->details->subject($type, $itemId);
        if ($subject === null) { http_response_code(404); echo 'Collection item not found.'; return; }
        $errors = [];
        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string)$input['_token'] : null)) {
                $errors[] = 'Your session expired. Refresh the page and try again.';
            } else {
                try { $this->details->save($type, $itemId, $input); }
                catch (InvalidArgumentException|ValueError $exception) { $errors[] = $exception->getMessage(); }
                if ($errors === []) { header('Location: /?route=collection-details&type='.$type->value.'&id='.$itemId.'&saved=1', true, 303); return; }
            }
        }
        $metadata = $this->details->details($type, $itemId);
        $related = $this->details->related($metadata->franchise(), $type, $itemId);
        $relatedItems = [];
        foreach ($related['games'] as $game) {
            $relatedItems[] = ['type'=>'Game','name'=>$game->title(),'subtitle'=>$game->platform(),'url'=>'/?route=collection-details&type=game&id='.$game->id()];
        }
        foreach ($related['merchandise'] as $item) {
            $relatedItems[] = ['type'=>'Merchandise','name'=>$item->name(),'subtitle'=>$item->category()->label(),'url'=>'/?route=collection-details&type=merchandise&id='.$item->id()];
        }
        usort($relatedItems, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));
        $relatedPerPage = 6;
        $relatedTotal = count($relatedItems);
        $relatedTotalPages = max(1, (int)ceil($relatedTotal / $relatedPerPage));
        $relatedPage = min(max(1, (int)($query['related_page'] ?? 1)), $relatedTotalPages);
        $relatedItems = array_slice($relatedItems, ($relatedPage - 1) * $relatedPerPage, $relatedPerPage);
        $conditions = ItemCondition::cases();
        $packagingOptions = ItemPackaging::cases();
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);
        $currentUser = $this->currentUser;
        require $this->templatePath;
    }
}
