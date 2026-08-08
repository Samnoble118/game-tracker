<?php

declare(strict_types=1);

/** Builds connected franchise views from games, merchandise, and metadata. */

namespace GameTracker\Application\Service;

use GameTracker\Domain\Entity\FranchiseGoal;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Repository\CollectionMetadataRepository;
use GameTracker\Domain\Repository\FranchiseGoalRepository;

/** Produces franchise summaries while keeping owner-private metadata out of views. */
final readonly class FranchiseAtlas
{
    /** Creates the atlas for one user's connected collection. */
    public function __construct(private GameLibrary $games,private MerchandiseCollection $merchandise,private CollectionMetadataRepository $metadata,private FranchiseGoalRepository $goals,private int $userId) {}

    /** Returns all detected franchises with collection statistics. @return list<array<string,mixed>> */
    public function index(string $search=''): array
    {
        [$metadata,$games,$merchandise]=$this->dataset();$names=[];
        foreach($metadata as $details) if($details->franchise()!=='') $names[mb_strtolower($details->franchise())]=$details->franchise();
        foreach($merchandise as $item) if($item->franchise()!=='') $names[mb_strtolower($item->franchise())]=$item->franchise();
        natcasesort($names); $result=[];
        foreach($names as $name){if($search!==''&&!str_contains(mb_strtolower($name),mb_strtolower($search)))continue;$result[]=$this->summary($name,$metadata,$games,$merchandise,false);}
        return $result;
    }

    /** Returns a complete safe franchise view or null when no records match. @return array<string,mixed>|null */
    public function franchise(string $name): ?array
    {
        [$metadata,$games,$merchandise]=$this->dataset();$summary=$this->summary(trim($name),$metadata,$games,$merchandise);
        return $summary['total']===0?null:$summary;
    }

    /** Adds a measurable franchise goal for the current user. */
    public function addGoal(string $franchise,string $title,string $itemType,int $targetCount): void
    {
        if($this->franchise($franchise)===null) throw new \InvalidArgumentException('Choose a franchise from your collection.');
        $this->goals->save(new FranchiseGoal($franchise,$title,$itemType,$targetCount,$this->userId));
    }

    /** Deletes a current-user goal. */
    public function deleteGoal(int $id): void {$this->goals->delete($id,$this->userId);}

    /** Aggregates collection, character, platform, wishlist, and goal statistics. @return array<string,mixed> */
    private function summary(string $name,array $metadataRecords,array $allGames,array $allMerchandise,bool $includeGoals=true): array
    {
        $metadata=[]; foreach($metadataRecords as $details)$metadata[$details->itemType()->value.':'.$details->itemId()]=$details;
        $games=array_values(array_filter($allGames,static fn($game): bool=>strcasecmp(($metadata['game:'.$game->id()]??null)?->franchise()??'',$name)===0));
        $merchandise=array_values(array_filter($allMerchandise,static fn($item): bool=>strcasecmp($item->franchise(),$name)===0));
        $characters=[];
        foreach($metadata as $details){if(strcasecmp($details->franchise(),$name)!==0)continue;foreach(preg_split('/\s*,\s*/',$details->characters(),-1,PREG_SPLIT_NO_EMPTY)?:[] as $character)$characters[mb_strtolower($character)]=$character;}
        natcasesort($characters);
        $ownedGames=count(array_filter($games,static fn($game): bool=>$game->collectionType()===CollectionType::Owned));
        $ownedMerchandise=count(array_filter($merchandise,static fn($item): bool=>$item->collectionType()===CollectionType::Owned));
        $wishlistGames=array_values(array_filter($games,static fn($game): bool=>$game->collectionType()===CollectionType::Wishlist));
        $wishlistMerchandise=array_values(array_filter($merchandise,static fn($item): bool=>$item->collectionType()===CollectionType::Wishlist));
        $goals=[];
        if($includeGoals)foreach($this->goals->forFranchise($name,$this->userId) as $goal){$current=match($goal->itemType()){'games'=>$ownedGames,'merchandise'=>$ownedMerchandise,default=>$ownedGames+$ownedMerchandise};$goals[]=['goal'=>$goal,'current'=>$current,'percentage'=>min(100,(int)floor($current/$goal->targetCount()*100))];}
        $platforms=[];foreach($games as $game)$platforms[mb_strtolower($game->platform())]=$game->platform();natcasesort($platforms);
        return ['name'=>$name,'games'=>$games,'merchandise'=>$merchandise,'total'=>count($games)+count($merchandise),'owned'=>$ownedGames+$ownedMerchandise,'completed'=>count(array_filter($games,static fn($game): bool=>$game->progress()===100)),'characters'=>array_values($characters),'platforms'=>array_values($platforms),'wishlistGames'=>$wishlistGames,'wishlistMerchandise'=>$wishlistMerchandise,'goals'=>$goals];
    }

    /** Loads each user-scoped collection source once for an atlas request. @return array{array,array,array} */
    private function dataset(): array { return [$this->metadata->all($this->userId),$this->games->collection(),$this->merchandise->collection()]; }
}
