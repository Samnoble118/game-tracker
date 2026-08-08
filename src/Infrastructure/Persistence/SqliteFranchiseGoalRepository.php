<?php

declare(strict_types=1);

/** Implements franchise collection-goal persistence using SQLite. */

namespace GameTracker\Infrastructure\Persistence;

use GameTracker\Domain\Entity\FranchiseGoal;
use GameTracker\Domain\Repository\FranchiseGoalRepository;
use PDO;

/** Stores and retrieves goals without crossing user boundaries. */
final readonly class SqliteFranchiseGoalRepository implements FranchiseGoalRepository
{
    /** Creates the goal table and lookup index. */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec("CREATE TABLE IF NOT EXISTS franchise_goals (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,franchise TEXT NOT NULL COLLATE NOCASE,title TEXT NOT NULL,item_type TEXT NOT NULL DEFAULT 'all',target_count INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_franchise_goals_user_franchise ON franchise_goals(user_id,franchise)');
    }

    /** Inserts a new goal or updates a user-owned goal. */
    public function save(FranchiseGoal $goal): void
    {
        $parameters=['user_id'=>$goal->userId(),'franchise'=>$goal->franchise(),'title'=>$goal->title(),'item_type'=>$goal->itemType(),'target_count'=>$goal->targetCount()];
        if($goal->id()===null){$statement=$this->connection->prepare('INSERT INTO franchise_goals(user_id,franchise,title,item_type,target_count) VALUES(:user_id,:franchise,:title,:item_type,:target_count)');$statement->execute($parameters);$goal->assignId((int)$this->connection->lastInsertId());return;}
        $statement=$this->connection->prepare('UPDATE franchise_goals SET franchise=:franchise,title=:title,item_type=:item_type,target_count=:target_count WHERE id=:id AND user_id=:user_id');$statement->execute([...$parameters,'id'=>$goal->id()]);
    }

    /** Returns alphabetical goals for one user and franchise. */
    public function forFranchise(string $franchise,int $userId): array
    {
        $statement=$this->connection->prepare('SELECT * FROM franchise_goals WHERE user_id=:user_id AND franchise=:franchise COLLATE NOCASE ORDER BY title');$statement->execute(['user_id'=>$userId,'franchise'=>$franchise]);
        return array_map(static fn(array $row): FranchiseGoal=>new FranchiseGoal((string)$row['franchise'],(string)$row['title'],(string)$row['item_type'],(int)$row['target_count'],(int)$row['user_id'],(int)$row['id']),$statement->fetchAll());
    }

    /** Deletes a goal only for its owning user. */
    public function delete(int $id,int $userId): void {$statement=$this->connection->prepare('DELETE FROM franchise_goals WHERE id=:id AND user_id=:user_id');$statement->execute(['id'=>$id,'user_id'=>$userId]);}
}
