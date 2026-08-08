<?php

declare(strict_types=1);

/** Builds the signed-in Collection HQ overview. */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;

/** Combines collection services into a concise, user-scoped home summary. */
final readonly class CollectionDashboard
{
    /** Creates the dashboard from existing collection services. */
    public function __construct(
        private GameLibrary $games,
        private MerchandiseCollection $merchandise,
        private FranchiseAtlas $franchises,
    ) {}

    /** Returns statistics, active games, recent records, and collection insights. @return array<string,mixed> */
    public function overview(): array
    {
        $games = $this->games->collection();
        $merchandise = $this->merchandise->collection();
        $franchises = $this->franchises->index();
        $counts = $this->games->viewCounts();
        $ownedMerchandise = array_filter($merchandise, static fn($item): bool => $item->collectionType() === CollectionType::Owned);
        $wishlistMerchandise = array_filter($merchandise, static fn($item): bool => $item->collectionType() === CollectionType::Wishlist);
        $playing = array_values(array_filter($games, static fn($game): bool => $game->status() === GameStatus::Playing));
        $completedOwned = count(array_filter($games, static fn($game): bool => $game->collectionType() === CollectionType::Owned && $game->status() === GameStatus::Completed));
        usort($playing, static fn($a,$b): int => $b->progress() <=> $a->progress());
        $recentGames = $games; usort($recentGames, static fn($a,$b): int => $b->id() <=> $a->id());
        $recentMerchandise = $merchandise; usort($recentMerchandise, static fn($a,$b): int => $b->id() <=> $a->id());
        $platforms=[]; foreach($games as $game) $platforms[$game->platform()]=($platforms[$game->platform()]??0)+1;
        arsort($platforms); usort($franchises, static fn(array $a,array $b): int => $b['total'] <=> $a['total']);

        return [
            'gameCounts'=>$counts,
            'merchandiseCount'=>array_sum(array_map(static fn($item): int=>$item->quantity(),$ownedMerchandise)),
            'franchiseCount'=>count($franchises),
            'wishlistCount'=>$counts['wishlist']+count($wishlistMerchandise),
            'completionRate'=>$counts['owned']===0?0:(int)round($completedOwned/$counts['owned']*100),
            'playing'=>array_slice($playing,0,4),
            'recentGames'=>array_slice($recentGames,0,4),
            'recentMerchandise'=>array_slice($recentMerchandise,0,4),
            'favouritePlatform'=>$platforms===[]?null:(string)array_key_first($platforms),
            'largestFranchise'=>$franchises[0]??null,
        ];
    }
}
