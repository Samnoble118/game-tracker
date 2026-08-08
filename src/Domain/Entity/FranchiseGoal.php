<?php

declare(strict_types=1);

/** Contains one measurable collection goal within a franchise. */

namespace GameTracker\Domain\Entity;

use InvalidArgumentException;

/** Represents an owner-created target for games, merchandise, or both. */
final class FranchiseGoal
{
    /** Creates a validated goal, optionally restored with a persisted ID. */
    public function __construct(private string $franchise,private string $title,private string $itemType,private int $targetCount,private readonly int $userId,private ?int $id=null)
    {
        $this->update($franchise,$title,$itemType,$targetCount);
    }

    /** Returns the persisted identifier. */ public function id(): ?int { return $this->id; }
    /** Returns the related franchise. */ public function franchise(): string { return $this->franchise; }
    /** Returns the collector-written goal. */ public function title(): string { return $this->title; }
    /** Returns games, merchandise, or all. */ public function itemType(): string { return $this->itemType; }
    /** Returns the desired number of matching owned records. */ public function targetCount(): int { return $this->targetCount; }
    /** Returns the owning user. */ public function userId(): int { return $this->userId; }

    /** Validates and replaces editable goal details. */
    public function update(string $franchise,string $title,string $itemType,int $targetCount): void
    {
        $franchise=trim($franchise); $title=trim($title);
        if ($franchise==='' || mb_strlen($franchise)>100) throw new InvalidArgumentException('Choose a franchise no longer than 100 characters.');
        if ($title==='' || mb_strlen($title)>120) throw new InvalidArgumentException('Enter a goal no longer than 120 characters.');
        if (!in_array($itemType,['all','games','merchandise'],true)) throw new InvalidArgumentException('Choose what this goal should count.');
        if ($targetCount<1 || $targetCount>10000) throw new InvalidArgumentException('Goal targets must be between 1 and 10,000.');
        $this->franchise=$franchise; $this->title=$title; $this->itemType=$itemType; $this->targetCount=$targetCount;
    }

    /** Assigns the database identifier after insertion. */
    public function assignId(int $id): void { if($this->id!==null) throw new InvalidArgumentException('The goal already has an ID.'); $this->id=$id; }
}
