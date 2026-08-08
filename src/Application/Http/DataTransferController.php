<?php

declare(strict_types=1);

/** Handles authenticated CSV import previews, confirmation, export, and history. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\CollectionCsvTransfer;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use GameTracker\Infrastructure\Persistence\SqliteImportHistoryRepository;
use InvalidArgumentException;
use Throwable;

/** Orchestrates the private collection data-transfer screen. */
final readonly class DataTransferController
{
    /** Creates the controller with transfer, audit, security, and template dependencies. */
    public function __construct(private CollectionCsvTransfer $transfer,private SqliteImportHistoryRepository $history,private CsrfToken $csrf,private string $templatePath) {}

    /** Dispatches CSV downloads and the three-stage import workflow. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input @param array<string,mixed> $files */
    public function handle(User $user,array $server,array $query,array $input,array $files=[]): void
    {
        $type=$this->type((string)($input['collection_type'] ?? $query['type'] ?? 'games'));
        if (($query['action'] ?? '')==='export') { $this->export($type); return; }
        $errors=[]; $stage='upload'; $upload=null; $mapping=[]; $preview=[]; $summary=null;
        if (($server['REQUEST_METHOD'] ?? 'GET')==='POST') {
            if (!$this->csrf->isValid(isset($input['_token'])?(string)$input['_token']:null)) $errors[]='Your session expired. Refresh and try again.';
            else {
                $action=(string)($input['action'] ?? 'upload');
                try {
                    if ($action==='upload') {
                        $upload=$this->transfer->parseUpload(isset($files['csv_file'])&&is_array($files['csv_file'])?$files['csv_file']:[]);
                        $mapping=$this->transfer->suggestedMapping($type,$upload['headers']);
                        $_SESSION['csv_transfer']=['type'=>$type,'filename'=>basename((string)($files['csv_file']['name']??'collection.csv')),'upload'=>$upload];
                        $stage='map';
                    } elseif ($action==='preview') {
                        $stored=$this->stored($type); $upload=$stored['upload'];
                        $mapping=is_array($input['mapping']??null)?array_map('strval',$input['mapping']):[];
                        $preview=$this->transfer->preview($type,$upload['headers'],$upload['rows'],$mapping);
                        $_SESSION['csv_transfer']['mapping']=$mapping; $_SESSION['csv_transfer']['preview']=$preview;
                        $stage='preview';
                    } elseif ($action==='confirm') {
                        $stored=$this->stored($type); $preview=is_array($stored['preview']??null)?$stored['preview']:throw new InvalidArgumentException('Preview the CSV before importing it.');
                        $summary=$this->transfer->import($type,$preview);
                        $this->history->record($user->id(),$type,(string)$stored['filename'],$summary);
                        unset($_SESSION['csv_transfer']); $stage='complete';
                    } elseif ($action==='cancel') unset($_SESSION['csv_transfer']);
                } catch (InvalidArgumentException $exception) { $errors[]=$exception->getMessage(); }
                catch (Throwable) { $errors[]='The CSV could not be processed. Check its contents and try again.'; }
            }
        }
        $fields=$this->transfer->fields($type); $required=$this->transfer->requiredFields($type); $history=$this->history->recent($user->id()); $csrfToken=$this->csrf->value();
        require $this->templatePath;
    }

    /** Streams an owner-only CSV file containing collection and private ownership fields. */
    private function export(string $type): void
    {
        $fields=$this->transfer->fields($type); $rows=$this->transfer->exportRows($type);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="archivexp-'.$type.'-'.gmdate('Y-m-d').'.csv"');
        $output=fopen('php://output','wb');
        if ($output===false) throw new InvalidArgumentException('The export could not be created.');
        fputcsv($output,array_keys($fields),',','"','');
        foreach($rows as $row) fputcsv($output,array_map(static function(string $field) use($row):string {
            $value=(string)($row[$field]??'');
            return preg_match('/^[=+\-@]/',$value)===1 ? "'".$value : $value;
        },array_keys($fields)),',','"','');
        fclose($output);
    }

    /** Resolves the only supported collection transfer types. */
    private function type(string $type): string { return in_array($type,['games','merchandise'],true)?$type:'games'; }

    /** Returns the matching session upload or rejects stale/cross-type confirmation. @return array<string,mixed> */
    private function stored(string $type): array
    {
        $stored=$_SESSION['csv_transfer']??null;
        if (!is_array($stored)||($stored['type']??null)!==$type) throw new InvalidArgumentException('Upload the CSV again before continuing.');
        return $stored;
    }
}
