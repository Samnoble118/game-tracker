<?php

declare(strict_types=1);

/** Handles private and shareable Franchise Collection Atlas pages. */

namespace GameTracker\Application\Http;

use GameTracker\Application\Service\FranchiseAtlas;
use GameTracker\Core\Http\CsrfToken;
use GameTracker\Domain\Entity\User;
use InvalidArgumentException;

/** Renders franchise indexes, dashboards, and owner-managed collection goals. */
final readonly class FranchiseAtlasController
{
    /** Creates the controller with atlas, CSRF, user, and template dependencies. */
    public function __construct(private FranchiseAtlas $atlas,private CsrfToken $csrf,private User $owner,private string $indexTemplate,private string $detailsTemplate) {}

    /** Renders the authenticated franchise index. @param array<string,mixed> $query */
    public function index(array $query): void
    {
        $search=trim((string)($query['q']??''));$franchises=$this->atlas->index($search);$currentUser=$this->owner;$csrfToken=$this->csrf->value();
        require $this->indexTemplate;
    }

    /** Processes goals and renders one private franchise dashboard. @param array<string,mixed> $server @param array<string,mixed> $query @param array<string,mixed> $input */
    public function details(array $server,array $query,array $input): void
    {
        $name=trim((string)($query['name']??$input['franchise']??''));$errors=[];
        if(($server['REQUEST_METHOD']??'GET')==='POST'){
            if(!$this->csrf->isValid(isset($input['_token'])?(string)$input['_token']:null))$errors[]='Your session expired. Refresh and try again.';
            else try{if(($input['goal_action']??'add')==='delete')$this->atlas->deleteGoal((int)($input['goal_id']??0));else $this->atlas->addGoal($name,(string)($input['goal_title']??''),(string)($input['goal_type']??'all'),(int)($input['target_count']??0));}catch(InvalidArgumentException $exception){$errors[]=$exception->getMessage();}
            if($errors===[]){header('Location: /?'.http_build_query(['route'=>'franchise','name'=>$name,'saved'=>1]),true,303);return;}
        }
        $franchise=$this->atlas->franchise($name);if($franchise===null){http_response_code(404);}
        $currentUser=$this->owner;$csrfToken=$this->csrf->value();$isPublic=false;$saved=isset($query['saved']);
        require $this->detailsTemplate;
    }

    /** Renders one privacy-safe franchise dashboard for a public profile. @param array<string,mixed> $query */
    public function publicDetails(array $query): void
    {
        $franchise=$this->owner->profilePublic()?$this->atlas->franchise(trim((string)($query['name']??''))):null;
        if($franchise===null)http_response_code(404);
        $currentUser=$this->owner;$csrfToken='';$isPublic=true;$errors=[];$saved=false;
        require $this->detailsTemplate;
    }
}
