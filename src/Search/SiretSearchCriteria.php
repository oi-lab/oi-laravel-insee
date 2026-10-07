<?php

namespace OiLab\OiLaravelInsee\Search;

use InvalidArgumentException;
use OiLab\OiLaravelInsee\Enums\WorkforceRange;

/**
 * Typed criteria of a multicriteria search on `/siret`; `toQuery()` builds the
 * `q` parameter so callers never write Sirene syntax. Immutable: every setter
 * returns a modified copy.
 *
 * Every filter is on codes, never on labels. The zone filters (communes, postal
 * codes, departments) are alternatives of one another: an establishment matches
 * when it is in any of them.
 */
final class SiretSearchCriteria
{
    /** Maximum number of terms in one parenthesised group (API limit: 1 000 connectors). */
    public const MAX_GROUP_SIZE = 1000;

    private bool $headquartersOnly = false;

    private bool $activeOnly = false;

    private bool $publicDiffusionOnly = false;

    /** @var list<string> */
    private array $workforceRanges = [];

    /** @var list<string> */
    private array $communeCodes = [];

    /** @var list<string> */
    private array $postalCodes = [];

    /** @var list<string> */
    private array $departmentCodes = [];

    /** @var list<string> */
    private array $nafCodes = [];

    /** @var list<string> */
    private array $fields = [];

    public static function make(): self
    {
        return new self;
    }

    /** Only headquarters (`etablissementSiege:true`). */
    public function headquartersOnly(bool $enabled = true): self
    {
        return $this->with('headquartersOnly', $enabled);
    }

    /** Only establishments currently active (current period of `etatAdministratifEtablissement`). */
    public function activeOnly(bool $enabled = true): self
    {
        return $this->with('activeOnly', $enabled);
    }

    /** Only establishments whose data is publicly diffused (`statutDiffusionEtablissement:O`). */
    public function publicDiffusionOnly(bool $enabled = true): self
    {
        return $this->with('publicDiffusionOnly', $enabled);
    }

    /**
     * Workforce ranges of the legal unit (`trancheEffectifsUniteLegale`).
     *
     * @param  array<int, WorkforceRange|string>  $codes
     */
    public function workforceRanges(array $codes): self
    {
        return $this->with('workforceRanges', array_map(
            fn (WorkforceRange|string $code): string => ($code instanceof WorkforceRange ? $code : WorkforceRange::tryFrom(strtoupper($code))
                ?? throw new InvalidArgumentException("Invalid workforce range [{$code}]."))->value,
            $codes,
        ));
    }

    /**
     * INSEE commune codes (5 characters, `2A`/`2B` for Corsica).
     *
     * @param  array<int, string>  $codes
     */
    public function communeCodes(array $codes): self
    {
        return $this->with('communeCodes', $this->validated($codes, '/^(\d{5}|2[AB]\d{3})$/', 'commune code'));
    }

    /**
     * @param  array<int, string>  $codes
     */
    public function postalCodes(array $codes): self
    {
        return $this->with('postalCodes', $this->validated($codes, '/^\d{5}$/', 'postal code'));
    }

    /**
     * Department codes, matched as a prefix of the commune code (`34*`). Mainland
     * (`01`-`95`), Corsica (`2A`, `2B`) and overseas (`971`-`976`, `98x`).
     *
     * @param  array<int, string>  $codes
     */
    public function departmentCodes(array $codes): self
    {
        return $this->with('departmentCodes', $this->validated($codes, '/^(0[1-9]|1\d|2[AB]|2[1-9]|[3-8]\d|9[0-5]|97[1-6]|98\d)$/', 'department code'));
    }

    /**
     * NAF codes of the establishment's current main activity (`62.01Z`).
     *
     * @param  array<int, string>  $codes
     */
    public function nafCodes(array $codes): self
    {
        return $this->with('nafCodes', $this->validated($codes, '/^\d{2}\.\d{2}[A-Z]$/', 'NAF code'));
    }

    /**
     * The variables to return (`champs`); all of them when empty.
     *
     * @param  array<int, string>  $fields
     */
    public function fields(array $fields): self
    {
        return $this->with('fields', array_values(array_unique($fields)));
    }

    /**
     * @return list<string>
     */
    public function selectedFields(): array
    {
        return $this->fields;
    }

    /**
     * The `q` parameter, or an empty string when no filter is set.
     */
    public function toQuery(): string
    {
        $parts = [];

        if ($this->headquartersOnly) {
            $parts[] = 'etablissementSiege:true';
        }

        if ($this->activeOnly) {
            $parts[] = 'periode(etatAdministratifEtablissement:A AND -dateFin:*)';
        }

        if ($this->publicDiffusionOnly) {
            $parts[] = 'statutDiffusionEtablissement:O';
        }

        $parts[] = $this->anyOf(array_map(fn (string $code): string => "trancheEffectifsUniteLegale:{$code}", $this->workforceRanges));

        $parts[] = $this->anyOf([
            ...array_map(fn (string $code): string => "codeCommuneEtablissement:{$code}", $this->communeCodes),
            ...array_map(fn (string $code): string => "codePostalEtablissement:{$code}", $this->postalCodes),
            ...array_map(fn (string $code): string => "codeCommuneEtablissement:{$code}*", $this->departmentCodes),
        ]);

        $parts[] = $this->anyOf(array_map(fn (string $code): string => "periode(activitePrincipaleEtablissement:{$code} AND -dateFin:*)", $this->nafCodes));

        return implode(' AND ', array_values(array_filter($parts, fn (?string $part): bool => $part !== null && $part !== '')));
    }

    /**
     * Join alternatives with OR, in parenthesised groups of at most
     * MAX_GROUP_SIZE terms (the API refuses more than 1 000 connectors).
     *
     * @param  list<string>  $terms
     */
    private function anyOf(array $terms): ?string
    {
        if ($terms === []) {
            return null;
        }

        if (count($terms) === 1) {
            return $terms[0];
        }

        $groups = array_map(
            fn (array $chunk): string => '('.implode(' OR ', $chunk).')',
            array_chunk($terms, self::MAX_GROUP_SIZE),
        );

        return count($groups) === 1 ? $groups[0] : '('.implode(' OR ', $groups).')';
    }

    /**
     * @param  array<int, string>  $codes
     * @return list<string>
     */
    private function validated(array $codes, string $pattern, string $label): array
    {
        $normalised = [];

        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));

            if (preg_match($pattern, $code) !== 1) {
                throw new InvalidArgumentException("Invalid {$label} [{$code}].");
            }

            $normalised[$code] = $code;
        }

        return array_values($normalised);
    }

    private function with(string $property, mixed $value): self
    {
        $copy = clone $this;
        $copy->{$property} = is_array($value) ? array_values(array_unique($value)) : $value;

        return $copy;
    }
}
