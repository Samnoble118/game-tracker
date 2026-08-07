<?php

declare(strict_types=1);

/**
 * Handles listing, filtering, creating, and editing physical merchandise.
 */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\MerchandiseItem;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\MerchandiseCategory;
use InvalidArgumentException;
use ValueError;

/** Translates merchandise web input into collection operations. */
final readonly class MerchandiseController
{
    /** Creates the controller with collection, security, and template dependencies. */
    public function __construct(private MerchandiseCollection $collection, private CsrfToken $csrf, private string $templatePath) {}

    /** Processes merchandise requests and renders the physical collection. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input */
    public function handle(array $server, array $query, array $input): void
    {
        $errors = [];
        $form = $this->emptyForm();
        if (($server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$errors, $form] = $this->save($input);
            if ($errors === []) {
                header('Location: /?route=merchandise&saved=1', true, 303);
                return;
            }
        } elseif (isset($query['edit'])) {
            $item = $this->collection->find((int) $query['edit']);
            if ($item !== null) {
                $form = $this->formFor($item);
            }
        }

        $allItems = $this->collection->collection();
        $search = trim((string) ($query['q'] ?? ''));
        $activeCategory = (string) ($query['category'] ?? 'all');
        $activeCollection = (string) ($query['collection'] ?? 'all');
        $validCategories = array_map(static fn (MerchandiseCategory $category): string => $category->value, MerchandiseCategory::cases());
        if ($activeCategory !== 'all' && !in_array($activeCategory, $validCategories, true)) $activeCategory = 'all';
        if (!in_array($activeCollection, ['all', 'owned', 'wishlist'], true)) $activeCollection = 'all';
        $items = array_values(array_filter($allItems, static fn (MerchandiseItem $item): bool =>
            ($search === '' || stripos($item->name(), $search) !== false || stripos($item->franchise(), $search) !== false)
            && ($activeCategory === 'all' || $item->category()->value === $activeCategory)
            && ($activeCollection === 'all' || $item->collectionType()->value === $activeCollection)
        ));
        $categories = MerchandiseCategory::cases();
        $collectionTypes = CollectionType::cases();
        $counts = ['all' => count($allItems), 'owned' => 0, 'wishlist' => 0];
        $categoryCounts = ['all' => count($allItems)];
        foreach ($allItems as $item) {
            $counts[$item->collectionType()->value]++;
            $categoryCounts[$item->category()->value] = ($categoryCounts[$item->category()->value] ?? 0) + 1;
        }
        $csrfToken = $this->csrf->value();
        $saved = isset($query['saved']);
        require $this->templatePath;
    }

    /** Validates form input and creates or updates an item. @param array<string,mixed> $input @return array{list<string>,array<string,string>} */
    private function save(array $input): array
    {
        $form = [
            'id' => trim((string) ($input['id'] ?? '')), 'name' => trim((string) ($input['name'] ?? '')),
            'franchise' => trim((string) ($input['franchise'] ?? '')), 'category' => trim((string) ($input['category'] ?? 'other')),
            'collection_type' => trim((string) ($input['collection_type'] ?? 'owned')),
            'quantity' => trim((string) ($input['quantity'] ?? '1')), 'notes' => trim((string) ($input['notes'] ?? '')),
        ];
        if (!$this->csrf->isValid(isset($input['_token']) ? (string) $input['_token'] : null)) return [['Your session expired. Refresh and try again.'], $form];
        try {
            $quantity = filter_var($form['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
            if ($quantity === false) throw new InvalidArgumentException('Quantity must be between 1 and 999.');
            $arguments = [$form['name'], $form['franchise'], MerchandiseCategory::from($form['category']), CollectionType::from($form['collection_type']), $quantity, $form['notes']];
            $item = $form['id'] === '' ? $this->collection->add(...$arguments) : $this->collection->update((int) $form['id'], ...$arguments);
            if ($item === null) throw new InvalidArgumentException('That merchandise item could not be found.');
        } catch (InvalidArgumentException|ValueError $exception) {
            return [[$exception->getMessage()], $form];
        }
        return [[], $form];
    }

    /** Returns blank merchandise form values. @return array<string,string> */
    private function emptyForm(): array { return ['id'=>'','name'=>'','franchise'=>'','category'=>'action-figure','collection_type'=>'owned','quantity'=>'1','notes'=>'']; }

    /** Converts an existing item into editable form values. @return array<string,string> */
    private function formFor(MerchandiseItem $item): array
    {
        return ['id'=>(string)$item->id(),'name'=>$item->name(),'franchise'=>$item->franchise(),'category'=>$item->category()->value,'collection_type'=>$item->collectionType()->value,'quantity'=>(string)$item->quantity(),'notes'=>$item->notes()];
    }
}
