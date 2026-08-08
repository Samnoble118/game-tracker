<?php

declare(strict_types=1);

/** Verifies safe CSV collection preview, import, duplicate detection, and export. */

namespace GameTracker\Tests\Unit;

use GameTracker\Application\Service\CollectionCsvTransfer;
use GameTracker\Application\Service\CollectionDetails;
use GameTracker\Application\Service\GameLibrary;
use GameTracker\Application\Service\MerchandiseCollection;
use GameTracker\Infrastructure\Persistence\SqliteCollectionMetadataRepository;
use GameTracker\Infrastructure\Persistence\SqliteGameRepository;
use GameTracker\Infrastructure\Persistence\SqliteImportHistoryRepository;
use GameTracker\Infrastructure\Persistence\SqliteMerchandiseRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/** Covers the owner-scoped spreadsheet transfer service and audit history. */
final class CollectionCsvTransferTest extends TestCase
{
    private PDO $connection;
    private CollectionCsvTransfer $transfer;

    /** Creates isolated in-memory persistence for every test. */
    protected function setUp(): void
    {
        $this->connection=new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $games=new GameLibrary(new SqliteGameRepository($this->connection),7);
        $merchandise=new MerchandiseCollection(new SqliteMerchandiseRepository($this->connection),7);
        $details=new CollectionDetails($games,$merchandise,new SqliteCollectionMetadataRepository($this->connection),7);
        $this->transfer=new CollectionCsvTransfer($games,$merchandise,$details);
    }

    /** Confirms mapped rows are validated, imported, de-duplicated, and privately exported. */
    public function testGameImportPreviewAndExportRoundTrip(): void
    {
        $path=tempnam(sys_get_temp_dir(),'archivexp-csv-');
        self::assertIsString($path);
        file_put_contents($path,"Game Title,Console,Status,Progress,Barcode,Purchase Price,Private Notes\nHades,PS5,playing,45,123456789012,19.99,Receipt stored\nHades,PS5,completed,100,,,Duplicate\nBroken,,backlog,0,,,\n");
        try { $upload=$this->transfer->parsePath($path); } finally { unlink($path); }
        $mapping=$this->transfer->suggestedMapping('games',$upload['headers']);
        $preview=$this->transfer->preview('games',$upload['headers'],$upload['rows'],$mapping);

        self::assertSame(['ready','duplicate','error'],array_column($preview,'status'));
        self::assertSame(['added'=>1,'skipped'=>2,'failed'=>0],$this->transfer->import('games',$preview));
        $export=$this->transfer->exportRows('games');
        self::assertCount(1,$export);
        self::assertSame('Hades',$export[0]['title']);
        self::assertSame('19.99',$export[0]['purchase_price']);
        self::assertSame('Receipt stored',$export[0]['private_notes']);

        $again=$this->transfer->preview('games',$upload['headers'],[$upload['rows'][0]],$mapping);
        self::assertSame('duplicate',$again[0]['status']);
    }

    /** Confirms invalid required mappings stop before any row can be imported. */
    public function testRequiredColumnsMustBeMapped(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->transfer->preview('games',['Title'],[['Hades']],['title'=>'0','platform'=>'']);
    }

    /** Confirms import history is isolated to its authenticated owner. */
    public function testImportHistoryIsUserScoped(): void
    {
        $history=new SqliteImportHistoryRepository($this->connection);
        $history->record(7,'games','games.csv',['added'=>4,'skipped'=>1,'failed'=>0]);
        $history->record(8,'merchandise','items.csv',['added'=>2,'skipped'=>0,'failed'=>1]);

        self::assertCount(1,$history->recent(7));
        self::assertSame('games.csv',$history->recent(7)[0]['filename']);
        self::assertSame([],$history->recent(99));
    }
}
