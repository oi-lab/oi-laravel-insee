<?php

namespace OiLab\OiLaravelInsee\Enums;

/**
 * INSEE workforce ranges (`trancheEffectifs*`), keyed by their SIRENE code.
 */
enum WorkforceRange: string
{
    case NoEmployer = 'NN';
    case Zero = '00';
    case OneToTwo = '01';
    case ThreeToFive = '02';
    case SixToNine = '03';
    case TenToNineteen = '11';
    case TwentyToFortyNine = '12';
    case FiftyToNinetyNine = '21';
    case OneHundredToOneHundredNinetyNine = '22';
    case TwoHundredToTwoHundredFortyNine = '31';
    case TwoHundredFiftyToFourHundredNinetyNine = '32';
    case FiveHundredToNineHundredNinetyNine = '41';
    case OneThousandToOneThousandNineHundredNinetyNine = '42';
    case TwoThousandToFourThousandNineHundredNinetyNine = '51';
    case FiveThousandToNineThousandNineHundredNinetyNine = '52';
    case TenThousandOrMore = '53';

    /**
     * Lowest headcount of the range, null for an employer-less unit (`NN`).
     */
    public function min(): ?int
    {
        return match ($this) {
            self::NoEmployer => null,
            self::Zero => 0,
            self::OneToTwo => 1,
            self::ThreeToFive => 3,
            self::SixToNine => 6,
            self::TenToNineteen => 10,
            self::TwentyToFortyNine => 20,
            self::FiftyToNinetyNine => 50,
            self::OneHundredToOneHundredNinetyNine => 100,
            self::TwoHundredToTwoHundredFortyNine => 200,
            self::TwoHundredFiftyToFourHundredNinetyNine => 250,
            self::FiveHundredToNineHundredNinetyNine => 500,
            self::OneThousandToOneThousandNineHundredNinetyNine => 1000,
            self::TwoThousandToFourThousandNineHundredNinetyNine => 2000,
            self::FiveThousandToNineThousandNineHundredNinetyNine => 5000,
            self::TenThousandOrMore => 10000,
        };
    }

    /**
     * Highest headcount of the range, null for an employer-less unit (`NN`) and
     * for the open-ended last range (`53`).
     */
    public function max(): ?int
    {
        return match ($this) {
            self::NoEmployer, self::TenThousandOrMore => null,
            self::Zero => 0,
            self::OneToTwo => 2,
            self::ThreeToFive => 5,
            self::SixToNine => 9,
            self::TenToNineteen => 19,
            self::TwentyToFortyNine => 49,
            self::FiftyToNinetyNine => 99,
            self::OneHundredToOneHundredNinetyNine => 199,
            self::TwoHundredToTwoHundredFortyNine => 249,
            self::TwoHundredFiftyToFourHundredNinetyNine => 499,
            self::FiveHundredToNineHundredNinetyNine => 999,
            self::OneThousandToOneThousandNineHundredNinetyNine => 1999,
            self::TwoThousandToFourThousandNineHundredNinetyNine => 4999,
            self::FiveThousandToNineThousandNineHundredNinetyNine => 9999,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NoEmployer => 'Unité non employeuse',
            self::Zero => '0 salarié (a employé au cours de l\'année)',
            self::OneToTwo => '1 ou 2 salariés',
            self::ThreeToFive => '3 à 5 salariés',
            self::SixToNine => '6 à 9 salariés',
            self::TenToNineteen => '10 à 19 salariés',
            self::TwentyToFortyNine => '20 à 49 salariés',
            self::FiftyToNinetyNine => '50 à 99 salariés',
            self::OneHundredToOneHundredNinetyNine => '100 à 199 salariés',
            self::TwoHundredToTwoHundredFortyNine => '200 à 249 salariés',
            self::TwoHundredFiftyToFourHundredNinetyNine => '250 à 499 salariés',
            self::FiveHundredToNineHundredNinetyNine => '500 à 999 salariés',
            self::OneThousandToOneThousandNineHundredNinetyNine => '1 000 à 1 999 salariés',
            self::TwoThousandToFourThousandNineHundredNinetyNine => '2 000 à 4 999 salariés',
            self::FiveThousandToNineThousandNineHundredNinetyNine => '5 000 à 9 999 salariés',
            self::TenThousandOrMore => '10 000 salariés et plus',
        };
    }

    /**
     * The ranges entirely contained in [$min, $max] (a null bound is open).
     * Employer-less units (`NN`) have no headcount and are never returned.
     *
     * @return list<self>
     */
    public static function within(?int $min, ?int $max): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $range): bool => $range->min() !== null
                && ($min === null || $range->min() >= $min)
                && ($max === null || ($range->max() !== null && $range->max() <= $max)),
        ));
    }
}
