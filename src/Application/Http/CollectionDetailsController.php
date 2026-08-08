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
        $duplicateMatches = [];
        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$this->csrf->isValid(isset($input['_token']) ? (string)$input['_token'] : null)) {
                $errors[] = 'Your session expired. Refresh the page and try again.';
            } else {
                try {
                    $duplicateMatches = $this->details->duplicates((string)($input['barcode'] ?? ''), $type, $itemId);
                    if ($duplicateMatches !== [] && !isset($input['allow_duplicate'])) {
                        throw new InvalidArgumentException('This barcode already exists in your collection. Review the matches and confirm if this is intentional.');
                    }
                    $this->details->save($type, $itemId, $input);
                }
                catch (InvalidArgumentException|ValueError $exception) { $errors[] = $exception->getMessage(); }
                if ($errors === []) { header('Location: /?route=collection-details&type='.$type->value.'&id='.$itemId.'&saved=1', true, 303); return; }
            }
        }
        $metadata = $this->details->details($type, $itemId);
        $form = [
            'franchise'=>$metadata->franchise(),'characters'=>$metadata->characters(),'barcode'=>$metadata->barcode(),
            'location'=>$metadata->location(),'condition'=>$metadata->condition()->value,'packaging'=>$metadata->packaging()->value,
            'purchase_price'=>$metadata->purchasePricePence() === null ? '' : number_format($metadata->purchasePricePence()/100,2,'.',''),
            'currency'=>$metadata->currency(),'purchased_on'=>$metadata->purchasedOn()?->format('Y-m-d') ?? '',
            'retailer'=>$metadata->retailer(),'serial_number'=>$metadata->serialNumber(),
            'receipt_reference'=>$metadata->receiptReference(),'private_notes'=>$metadata->privateNotes(),
        ];
        if ($errors !== []) {
            foreach (array_keys($form) as $field) if (array_key_exists($field, $input)) $form[$field] = trim((string)$input[$field]);
        }
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
