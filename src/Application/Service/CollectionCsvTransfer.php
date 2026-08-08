<?php

declare(strict_types=1);

/** Parses, validates, imports, and exports user-owned collection CSV data. */

namespace GameTracker\Application\Service;

use DateTimeImmutable;
use GameTracker\Domain\Enum\CollectionItemType;
use GameTracker\Domain\Enum\CollectionType;
use GameTracker\Domain\Enum\GameStatus;
use GameTracker\Domain\Enum\ItemCondition;
use GameTracker\Domain\Enum\ItemPackaging;
use GameTracker\Domain\Enum\MerchandiseCategory;
use GameTracker\Domain\Enum\MerchandisePackaging;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Coordinates safe spreadsheet transfers for games and merchandise. */
final readonly class CollectionCsvTransfer
{
    public const MAX_BYTES = 2_000_000;
    public const MAX_ROWS = 1_000;

    /** Creates the transfer service for one authenticated user's collections. */
    public function __construct(
        private GameLibrary $games,
        private MerchandiseCollection $merchandise,
        private CollectionDetails $details,
    ) {}

    /** Returns supported CSV fields and labels for a collection type. @return array<string,string> */
    public function fields(string $type): array
    {
        $shared = [
            'collection_type'=>'Collection (owned/wishlist)', 'barcode'=>'Barcode', 'location'=>'Storage location',
            'condition'=>'Condition', 'purchase_price'=>'Purchase price', 'currency'=>'Currency',
            'purchased_on'=>'Purchased on', 'retailer'=>'Retailer or seller', 'serial_number'=>'Serial number',
            'receipt_reference'=>'Receipt reference', 'private_notes'=>'Private notes',
        ];
        if ($type === 'games') {
            return ['title'=>'Game title','platform'=>'Platform','status'=>'Play status','progress'=>'Progress (%)','franchise'=>'Franchise','characters'=>'Characters','packaging'=>'Packaging'] + $shared;
        }
        if ($type === 'merchandise') {
            return ['name'=>'Item name','franchise'=>'Franchise','category'=>'Category','packaging'=>'Packaging','quantity'=>'Quantity','notes'=>'Collection notes','characters'=>'Characters'] + $shared;
        }
        throw new InvalidArgumentException('Choose games or merchandise.');
    }

    /** Returns fields required to create a record. @return list<string> */
    public function requiredFields(string $type): array
    {
        return $type === 'games' ? ['title','platform'] : ($type === 'merchandise' ? ['name','category'] : throw new InvalidArgumentException('Choose games or merchandise.'));
    }

    /** Reads a bounded CSV upload into serialisable headers and rows. @param array<string,mixed> $file @return array{headers:list<string>,rows:list<list<string>>} */
    public function parseUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Choose a CSV file to upload.');
        if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > self::MAX_BYTES) throw new InvalidArgumentException('CSV files must be no larger than 2 MB.');
        $path = (string)($file['tmp_name'] ?? '');
        if ($path === '' || !is_file($path)) throw new InvalidArgumentException('The uploaded CSV could not be read.');
        return $this->parsePath($path);
    }

    /** Parses a local CSV path for web uploads and automated tests. @return array{headers:list<string>,rows:list<list<string>>} */
    public function parsePath(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('The CSV could not be opened.');
        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) throw new InvalidArgumentException('The CSV is empty.');
            $delimiter = $this->delimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($headers === false) throw new InvalidArgumentException('The CSV header row could not be read.');
            $headers = array_map(static fn(mixed $value): string => trim((string)$value, " \t\n\r\0\x0B\xEF\xBB\xBF"), $headers);
            if ($headers === [] || count(array_filter($headers, static fn(string $value): bool => $value !== '')) === 0) throw new InvalidArgumentException('The CSV needs a header row.');
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if (count($rows) >= self::MAX_ROWS) throw new InvalidArgumentException('A single import can contain at most 1,000 rows.');
                $row = array_map(static fn(mixed $value): string => trim((string)$value), $row);
                if (count(array_filter($row, static fn(string $value): bool => $value !== '')) > 0) $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }
        if ($rows === []) throw new InvalidArgumentException('The CSV does not contain any data rows.');
        return ['headers'=>$headers,'rows'=>$rows];
    }

    /** Suggests column mappings by comparing normalised field and header names. @param list<string> $headers @return array<string,string> */
    public function suggestedMapping(string $type, array $headers): array
    {
        $aliases = ['game'=>'title','game_title'=>'title','item'=>'name','item_name'=>'name','console'=>'platform','collection'=>'collection_type','play_status'=>'status','progress_percentage'=>'progress','storage_location'=>'location','price'=>'purchase_price','purchase_date'=>'purchased_on'];
        $available = [];
        foreach ($headers as $index=>$header) $available[$this->headerKey($header)] = (string)$index;
        $mapping = [];
        foreach ($this->fields($type) as $field=>$label) {
            $mapping[$field] = $available[$field] ?? '';
            if ($mapping[$field] === '') {
                foreach ($aliases as $alias=>$target) if ($target === $field && isset($available[$alias])) $mapping[$field] = $available[$alias];
            }
        }
        return $mapping;
    }

    /** Validates mapped rows and labels duplicates before any database write. @param list<string> $headers @param list<list<string>> $rows @param array<string,string> $mapping @return list<array{line:int,status:string,message:string,data:array<string,string>}> */
    public function preview(string $type, array $headers, array $rows, array $mapping): array
    {
        foreach ($this->requiredFields($type) as $field) if (!isset($mapping[$field]) || $mapping[$field] === '') throw new InvalidArgumentException('Map every required column before previewing the import.');
        $existing = $this->duplicateKeys($type);
        $seen = []; $seenBarcodes=[];
        $preview = [];
        foreach ($rows as $offset=>$row) {
            $data = [];
            foreach ($this->fields($type) as $field=>$label) {
                $index = isset($mapping[$field]) && $mapping[$field] !== '' ? (int)$mapping[$field] : -1;
                $data[$field] = $index >= 0 ? trim((string)($row[$index] ?? '')) : '';
            }
            try {
                $data = $this->normalise($type, $data);
                $key = $this->duplicateKey($type, $data);
                $barcode=$data['barcode']??'';
                $barcodeDuplicate=$barcode!==''&&($this->details->duplicates($barcode,$type==='games'?CollectionItemType::Game:CollectionItemType::Merchandise,0)!==[]||isset($seenBarcodes[$barcode]));
                $duplicate = isset($existing[$key]) || isset($seen[$key]) || $barcodeDuplicate;
                $seen[$key] = true;
                if($barcode!=='') $seenBarcodes[$barcode]=true;
                $preview[] = ['line'=>$offset+2,'status'=>$duplicate?'duplicate':'ready','message'=>$duplicate?'Already in this collection; it will be skipped.':'Ready to import.','data'=>$data];
            } catch (InvalidArgumentException $exception) {
                $preview[] = ['line'=>$offset+2,'status'=>'error','message'=>$exception->getMessage(),'data'=>$data];
            }
        }
        return $preview;
    }

    /** Imports only rows which passed preview validation. @param list<array{line:int,status:string,message:string,data:array<string,string>}> $rows @return array{added:int,skipped:int,failed:int} */
    public function import(string $type, array $rows): array
    {
        $summary = ['added'=>0,'skipped'=>0,'failed'=>0];
        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'ready') { $summary['skipped']++; continue; }
            try {
                $data = $row['data'];
                if ($type === 'games') {
                    $item = $this->games->add($data['title'],$data['platform'],GameStatus::from($data['status']),(int)$data['progress'],CollectionType::from($data['collection_type']));
                    $itemType = CollectionItemType::Game;
                } else {
                    $item = $this->merchandise->add($data['name'],$data['franchise'],MerchandiseCategory::from($data['category']),MerchandisePackaging::from($data['packaging']),CollectionType::from($data['collection_type']),(int)$data['quantity'],$data['notes']);
                    $itemType = CollectionItemType::Merchandise;
                }
                $this->details->save($itemType,(int)$item->id(),$this->metadataInput($data));
                $summary['added']++;
            } catch (Throwable) {
                $summary['failed']++;
            }
        }
        return $summary;
    }

    /** Builds owner-only rows, including private ownership data, for CSV download. @return list<array<string,string>> */
    public function exportRows(string $type): array
    {
        $rows = [];
        $items = $type === 'games' ? $this->games->collection() : ($type === 'merchandise' ? $this->merchandise->collection() : throw new InvalidArgumentException('Choose games or merchandise.'));
        foreach ($items as $item) {
            $itemType = $type === 'games' ? CollectionItemType::Game : CollectionItemType::Merchandise;
            $metadata = $this->details->details($itemType,(int)$item->id());
            $row = $type === 'games' ? [
                'title'=>$item->title(),'platform'=>$item->platform(),'status'=>$item->status()->value,'progress'=>(string)$item->progress(),
                'franchise'=>$metadata->franchise(),'characters'=>$metadata->characters(),'packaging'=>$metadata->packaging()->value,'collection_type'=>$item->collectionType()->value,
            ] : [
                'name'=>$item->name(),'franchise'=>$item->franchise(),'category'=>$item->category()->value,'packaging'=>$item->packaging()->value,
                'quantity'=>(string)$item->quantity(),'notes'=>$item->notes(),'characters'=>$metadata->characters(),'collection_type'=>$item->collectionType()->value,
            ];
            $rows[] = $row + $this->metadataExport($metadata);
        }
        return $rows;
    }

    /** Converts and validates a mapped record using domain-compatible values. @param array<string,string> $data @return array<string,string> */
    private function normalise(string $type, array $data): array
    {
        foreach ($data as $field=>$value) $data[$field] = trim($value);
        $data['collection_type'] = strtolower($data['collection_type'] ?: 'owned');
        if (CollectionType::tryFrom($data['collection_type']) === null) throw new InvalidArgumentException('Collection must be owned or wishlist.');
        if ($type === 'games') {
            if ($data['title'] === '') throw new InvalidArgumentException('Game title is required.');
            if ($data['platform'] === '') throw new InvalidArgumentException('Platform is required.');
            $data['status'] = strtolower($data['status'] ?: 'backlog');
            if (GameStatus::tryFrom($data['status']) === null) throw new InvalidArgumentException('Play status is not recognised.');
            $data['progress'] = $data['progress'] === '' ? '0' : $data['progress'];
            if (filter_var($data['progress'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100]]) === false) throw new InvalidArgumentException('Progress must be a whole number from 0 to 100.');
            $data['packaging'] = strtolower($data['packaging'] ?: 'unspecified');
            if (ItemPackaging::tryFrom($data['packaging']) === null) throw new InvalidArgumentException('Packaging is not recognised.');
        } else {
            if ($data['name'] === '') throw new InvalidArgumentException('Item name is required.');
            $data['category'] = $this->enumValue($data['category'], ['action_figures'=>'action-figure','action_figure'=>'action-figure','pop_vinyls'=>'pop-vinyl','pop_vinyl'=>'pop-vinyl']);
            if (MerchandiseCategory::tryFrom($data['category']) === null) throw new InvalidArgumentException('Merchandise category is not recognised.');
            $data['packaging'] = $this->enumValue($data['packaging'] ?: 'loose');
            if (MerchandisePackaging::tryFrom($data['packaging']) === null) throw new InvalidArgumentException('Packaging is not recognised.');
            $data['quantity'] = $data['quantity'] === '' ? '1' : $data['quantity'];
            if (filter_var($data['quantity'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999]]) === false) throw new InvalidArgumentException('Quantity must be between 1 and 999.');
        }
        $barcode = preg_replace('/\D+/', '', $data['barcode'] ?? '') ?? '';
        if ($barcode !== '' && preg_match('/^\d{8,14}$/',$barcode)!==1) throw new InvalidArgumentException('Barcode must contain 8 to 14 digits.');
        $data['barcode']=$barcode;
        $data['condition']=$this->enumValue(($data['condition'] ?? '') ?: 'unspecified');
        if (ItemCondition::tryFrom($data['condition']) === null) throw new InvalidArgumentException('Condition is not recognised.');
        $data['currency']=strtoupper($data['currency'] ?: 'GBP');
        if (preg_match('/^[A-Z]{3}$/',$data['currency'])!==1) throw new InvalidArgumentException('Currency must use a three-letter code.');
        if (($data['purchase_price']??'')!=='' && preg_match('/^\d{1,7}(?:\.\d{1,2})?$/',$data['purchase_price'])!==1) throw new InvalidArgumentException('Purchase price is not valid.');
        if (($data['purchased_on']??'')!=='') {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$data['purchased_on']);
            if ($date===false||$date->format('Y-m-d')!==$data['purchased_on']) throw new InvalidArgumentException('Purchase date must use YYYY-MM-DD.');
        }
        foreach(['franchise','characters','location','retailer','serial_number','receipt_reference'] as $field) if(strlen($data[$field]??'')>255) throw new InvalidArgumentException('A collection detail exceeds 255 characters.');
        if(strlen($data['private_notes']??'')>4000) throw new InvalidArgumentException('Private notes exceed 4,000 characters.');
        return $data;
    }

    /** Returns user-scoped duplicate keys already present in the selected collection. @return array<string,true> */
    private function duplicateKeys(string $type): array
    {
        $keys=[];
        if ($type==='games') foreach($this->games->collection() as $item) $keys[$this->key($item->title(),$item->platform())]=true;
        else foreach($this->merchandise->collection() as $item) $keys[$this->key($item->name(),$item->category()->value)]=true;
        return $keys;
    }

    /** Returns a stable duplicate key for one normalised row. @param array<string,string> $data */
    private function duplicateKey(string $type,array $data): string { return $type==='games'?$this->key($data['title'],$data['platform']):$this->key($data['name'],$data['category']); }

    /** Normalises two identity fields for case-insensitive duplicate matching. */
    private function key(string $first,string $second): string { return strtolower(trim($first))."\0".strtolower(trim($second)); }

    /** Normalises spreadsheet enum labels into stored enum values. @param array<string,string> $aliases */
    private function enumValue(string $value,array $aliases=[]): string { $key=str_replace([' ','-'], '_', strtolower(trim($value))); return $aliases[$key] ?? str_replace('_','-',$key); }

    /** Converts an uploaded header into a comparable field key. */
    private function headerKey(string $header): string { return trim(preg_replace('/[^a-z0-9]+/','_',strtolower($header)) ?? '', '_'); }

    /** Detects the most likely delimiter from the header line. */
    private function delimiter(string $line): string { $counts=[","=>substr_count($line,','),";"=>substr_count($line,';'),"\t"=>substr_count($line,"\t")]; arsort($counts); return (string)array_key_first($counts); }

    /** Builds collection-detail input from one validated CSV row. @param array<string,string> $data @return array<string,string> */
    private function metadataInput(array $data): array
    {
        return ['franchise'=>$data['franchise']??'','characters'=>$data['characters']??'','barcode'=>$data['barcode']??'','location'=>$data['location']??'','condition'=>$data['condition']??'unspecified','packaging'=>$data['packaging']??'unspecified','purchase_price'=>$data['purchase_price']??'','currency'=>$data['currency']??'GBP','purchased_on'=>$data['purchased_on']??'','retailer'=>$data['retailer']??'','serial_number'=>$data['serial_number']??'','receipt_reference'=>$data['receipt_reference']??'','private_notes'=>$data['private_notes']??''];
    }

    /** Serialises public and owner-private metadata into export columns. @return array<string,string> */
    private function metadataExport(object $metadata): array
    {
        return ['barcode'=>$metadata->barcode(),'location'=>$metadata->location(),'condition'=>$metadata->condition()->value,'purchase_price'=>$metadata->purchasePricePence()===null?'':number_format($metadata->purchasePricePence()/100,2,'.',''),'currency'=>$metadata->currency(),'purchased_on'=>$metadata->purchasedOn()?->format('Y-m-d')??'','retailer'=>$metadata->retailer(),'serial_number'=>$metadata->serialNumber(),'receipt_reference'=>$metadata->receiptReference(),'private_notes'=>$metadata->privateNotes()];
    }
}
