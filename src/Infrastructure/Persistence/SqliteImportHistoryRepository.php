<?php

declare(strict_types=1);

/** Stores a private audit trail of completed collection imports. */

namespace GameTracker\Infrastructure\Persistence;

use PDO;

/** Persists and retrieves user-scoped CSV import summaries. */
final readonly class SqliteImportHistoryRepository
{
    /** Creates the import-history table and user/date index. */
    public function __construct(private PDO $connection)
    {
        $this->connection->exec("CREATE TABLE IF NOT EXISTS import_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, collection_type TEXT NOT NULL,
            filename TEXT NOT NULL, added INTEGER NOT NULL, skipped INTEGER NOT NULL, failed INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_import_history_user_date ON import_history(user_id, created_at DESC)');
    }

    /** Records one completed import summary for its authenticated owner. */
    public function record(int $userId,string $type,string $filename,array $summary): void
    {
        $statement=$this->connection->prepare('INSERT INTO import_history (user_id,collection_type,filename,added,skipped,failed) VALUES (:user_id,:collection_type,:filename,:added,:skipped,:failed)');
        $statement->execute(['user_id'=>$userId,'collection_type'=>$type,'filename'=>substr($filename,0,180),'added'=>(int)$summary['added'],'skipped'=>(int)$summary['skipped'],'failed'=>(int)$summary['failed']]);
    }

    /** Returns the ten most recent imports without exposing another user's activity. @return list<array<string,int|string>> */
    public function recent(int $userId): array
    {
        $statement=$this->connection->prepare('SELECT collection_type,filename,added,skipped,failed,created_at FROM import_history WHERE user_id=:user_id ORDER BY id DESC LIMIT 10');
        $statement->execute(['user_id'=>$userId]);
        return $statement->fetchAll();
    }
}
