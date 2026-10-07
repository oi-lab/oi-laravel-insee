<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * One page of a cursor-paginated `/siret` search. Persist `$nextCursor` to
 * resume the search later.
 */
class SiretSearchPage extends Data
{
    public function __construct(
        /** @var Etablissement[] */
        public array $etablissements,
        public string $cursor,
        public string $nextCursor,
        public int $total = 0,
    ) {}

    /**
     * Whether the search has no further page.
     */
    public function isLast(): bool
    {
        return $this->etablissements === [] || $this->nextCursor === $this->cursor;
    }
}
