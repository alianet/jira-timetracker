<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Calendar;

use App\Kernel\Exception\ApplicationConfigException;
use App\Reporting\Domain\Port\HolidayCalendar;
use Umulmrum\Holiday\HolidayCalculator;
use Umulmrum\Holiday\HolidayCalculatorInterface;
use Umulmrum\Holiday\Provider\HolidayProviderInterface;

use function Safe\preg_match;

final readonly class HolidayCalendarFactory
{
    public const string COUNTRY_AUSTRALIA = 'Australia';
    public const string COUNTRY_AUSTRIA = 'Austria';
    public const string COUNTRY_BELARUS = 'Belarus';
    public const string COUNTRY_BELGIUM = 'Belgium';
    public const string COUNTRY_BRAZIL = 'Brazil';
    public const string COUNTRY_BULGARIA = 'Bulgaria';
    public const string COUNTRY_CANADA = 'Canada';
    public const string COUNTRY_CZECH_REPUBLIC = 'CzechRepublic';
    public const string COUNTRY_DENMARK = 'Denmark';
    public const string COUNTRY_ESTONIA = 'Estonia';
    public const string COUNTRY_FINLAND = 'Finland';
    public const string COUNTRY_FRANCE = 'France';
    public const string COUNTRY_GERMANY = 'Germany';
    public const string COUNTRY_GREENLAND = 'Greenland';
    public const string COUNTRY_ICELAND = 'Iceland';
    public const string COUNTRY_IRELAND = 'Ireland';
    public const string COUNTRY_ITALY = 'Italy';
    public const string COUNTRY_LATVIA = 'Latvia';
    public const string COUNTRY_LIECHTENSTEIN = 'Liechtenstein';
    public const string COUNTRY_LITHUANIA = 'Lithuania';
    public const string COUNTRY_LUXEMBOURG = 'Luxembourg';
    public const string COUNTRY_MEXICO = 'Mexico';
    public const string COUNTRY_NETHERLANDS = 'Netherlands';
    public const string COUNTRY_NORWAY = 'Norway';
    public const string COUNTRY_POLAND = 'Poland';
    public const string COUNTRY_PORTUGAL = 'Portugal';
    public const string COUNTRY_RUSSIA = 'Russia';
    public const string COUNTRY_SPAIN = 'Spain';
    public const string COUNTRY_SWEDEN = 'Sweden';
    public const string COUNTRY_SWITZERLAND = 'Switzerland';
    public const string COUNTRY_TURKEY = 'Turkey';
    public const string COUNTRY_UKRAINE = 'Ukraine';
    public const string COUNTRY_UNITED_KINGDOM = 'UnitedKingdom';
    public const string COUNTRY_USA = 'Usa';

    /** The country-wide provider has the same name as its country. */
    public const string VERSION_NATIONAL = 'national';
    public const string VERSION_AUSTRALIA_CAPITAL_TERRITORY = 'AustralianCapitalTerritory';
    public const string VERSION_AUSTRALIA_NEW_SOUTH_WALES = 'NewSouthWales';
    public const string VERSION_AUSTRALIA_NORTHERN_TERRITORY = 'NorthernTerritory';
    public const string VERSION_AUSTRALIA_QUEENSLAND = 'Queensland';
    public const string VERSION_AUSTRALIA_SOUTH_AUSTRALIA = 'SouthAustralia';
    public const string VERSION_AUSTRALIA_TASMANIA = 'Tasmania';
    public const string VERSION_AUSTRALIA_VICTORIA = 'Victoria';
    public const string VERSION_AUSTRALIA_WESTERN_AUSTRALIA = 'WesternAustralia';
    public const string VERSION_AUSTRIA_BURGENLAND = 'Burgenland';
    public const string VERSION_AUSTRIA_CARINTHIA = 'Carinthia';
    public const string VERSION_AUSTRIA_LOWER_AUSTRIA = 'LowerAustria';
    public const string VERSION_AUSTRIA_SALZBURG = 'Salzburg';
    public const string VERSION_AUSTRIA_STYRIA = 'Styria';
    public const string VERSION_AUSTRIA_TYROL = 'Tyrol';
    public const string VERSION_AUSTRIA_UPPER_AUSTRIA = 'UpperAustria';
    public const string VERSION_AUSTRIA_VIENNA = 'Vienna';
    public const string VERSION_AUSTRIA_VORARLBERG = 'Vorarlberg';
    public const string VERSION_CANADA_ALBERTA = 'Alberta';
    public const string VERSION_CANADA_BRITISH_COLUMBIA = 'BritishColumbia';
    public const string VERSION_CANADA_MANITOBA = 'Manitoba';
    public const string VERSION_CANADA_NEW_BRUNSWICK = 'NewBrunswick';
    public const string VERSION_CANADA_NEWFOUNDLAND_AND_LABRADOR = 'NewFoundlandAndLabrador';
    public const string VERSION_CANADA_NORTHWEST_TERRITORIES = 'NorthwestTerritories';
    public const string VERSION_CANADA_NOVA_SCOTIA = 'NovaScotia';
    public const string VERSION_CANADA_NUNAVUT = 'Nunavut';
    public const string VERSION_CANADA_ONTARIO = 'Ontario';
    public const string VERSION_CANADA_PRINCE_EDWARD_ISLAND = 'PrinceEdwardIsland';
    public const string VERSION_CANADA_QUEBEC = 'Quebec';
    public const string VERSION_CANADA_SASKATCHEWAN = 'Saskatchewan';
    public const string VERSION_CANADA_YUKON = 'Yukon';
    public const string VERSION_FRANCE_BAS_RHIN = 'BasRhin';
    public const string VERSION_FRANCE_FRENCH_GUIANA = 'FrenchGuiana';
    public const string VERSION_FRANCE_GUADELOUPE = 'Guadeloupe';
    public const string VERSION_FRANCE_HAUT_RHIN = 'HautRhin';
    public const string VERSION_FRANCE_MARTINIQUE = 'Martinique';
    public const string VERSION_FRANCE_MOSELLE = 'Moselle';
    public const string VERSION_FRANCE_REUNION = 'Reunion';
    public const string VERSION_GERMANY_BADEN_WUERTTEMBERG = 'BadenWuerttemberg';
    public const string VERSION_GERMANY_BAVARIA = 'Bavaria';
    public const string VERSION_GERMANY_BERLIN = 'Berlin';
    public const string VERSION_GERMANY_BRANDENBURG = 'Brandenburg';
    public const string VERSION_GERMANY_BREMEN = 'Bremen';
    public const string VERSION_GERMANY_HAMBURG = 'Hamburg';
    public const string VERSION_GERMANY_HESSE = 'Hesse';
    public const string VERSION_GERMANY_LOWER_SAXONY = 'LowerSaxony';
    public const string VERSION_GERMANY_MECKLENBURG_VORPOMMERN = 'MecklenburgVorpommern';
    public const string VERSION_GERMANY_NORTH_RHINE_WESTPHALIA = 'NorthRhineWestphalia';
    public const string VERSION_GERMANY_RHINELAND_PALATINATE = 'RhinelandPalatinate';
    public const string VERSION_GERMANY_SAARLAND = 'Saarland';
    public const string VERSION_GERMANY_SAXONY = 'Saxony';
    public const string VERSION_GERMANY_SAXONY_ANHALT = 'SaxonyAnhalt';
    public const string VERSION_GERMANY_SCHLESWIG_HOLSTEIN = 'SchleswigHolstein';
    public const string VERSION_GERMANY_THURINGIA = 'Thuringia';
    public const string VERSION_ITALY_SOUTH_TYROL = 'SouthTyrol';
    public const string VERSION_PORTUGAL_AZORES = 'Azores';
    public const string VERSION_PORTUGAL_MADEIRA = 'Madeira';
    public const string VERSION_SWITZERLAND_AARGAU = 'Aargau';
    public const string VERSION_SWITZERLAND_APPENZELL_AUSSERRHODEN = 'AppenzellAusserrhoden';
    public const string VERSION_SWITZERLAND_APPENZELL_INNERRHODEN = 'AppenzellInnerrhoden';
    public const string VERSION_SWITZERLAND_BASEL_LANDSCHAFT = 'BaselLandschaft';
    public const string VERSION_SWITZERLAND_BASEL_STADT = 'BaselStadt';
    public const string VERSION_SWITZERLAND_BERN = 'Bern';
    public const string VERSION_SWITZERLAND_FRIBOURG = 'Fribourg';
    public const string VERSION_SWITZERLAND_GENEVA = 'Geneva';
    public const string VERSION_SWITZERLAND_GLARUS = 'Glarus';
    public const string VERSION_SWITZERLAND_GRISONS = 'Grisons';
    public const string VERSION_SWITZERLAND_JURA = 'Jura';
    public const string VERSION_SWITZERLAND_LUCERNE = 'Lucerne';
    public const string VERSION_SWITZERLAND_NEUCHATEL = 'Neuchatel';
    public const string VERSION_SWITZERLAND_NIDWALDEN = 'Nidwalden';
    public const string VERSION_SWITZERLAND_OBWALDEN = 'Obwalden';
    public const string VERSION_SWITZERLAND_SCHAFFHAUSEN = 'Schaffhausen';
    public const string VERSION_SWITZERLAND_SCHWYZ = 'Schwyz';
    public const string VERSION_SWITZERLAND_SOLOTHURN = 'Solothurn';
    public const string VERSION_SWITZERLAND_ST_GALLEN = 'StGallen';
    public const string VERSION_SWITZERLAND_THURGAU = 'Thurgau';
    public const string VERSION_SWITZERLAND_TICINO = 'Ticino';
    public const string VERSION_SWITZERLAND_URI = 'Uri';
    public const string VERSION_SWITZERLAND_VALAIS = 'Valais';
    public const string VERSION_SWITZERLAND_VAUD = 'Vaud';
    public const string VERSION_SWITZERLAND_ZUERICH = 'Zuerich';
    public const string VERSION_SWITZERLAND_ZUG = 'Zug';
    public const string VERSION_UNITED_KINGDOM_NORTHERN_IRELAND = 'NorthernIreland';
    public const string VERSION_UNITED_KINGDOM_SCOTLAND = 'Scotland';

    public function __construct(private HolidayCalculatorInterface $calculator = new HolidayCalculator()) {}

    public function create(string $country, string $version = self::VERSION_NATIONAL): HolidayCalendar
    {
        $country = trim($country);
        $version = trim($version);
        $provider = $version === self::VERSION_NATIONAL ? $country : $version;

        if (!preg_match('/^[A-Z][A-Za-z]*$/D', $country) || !preg_match('/^[A-Z][A-Za-z]*$/D', $provider)) {
            throw ApplicationConfigException::invalidValue(
                'HOLIDAY_COUNTRY/HOLIDAY_CALENDAR_VERSION',
                'nazwy providera z Umulmrum\\Holiday\\Provider',
            );
        }

        $providerClass = "Umulmrum\\Holiday\\Provider\\{$country}\\{$provider}";
        if (!class_exists($providerClass) || !is_subclass_of($providerClass, HolidayProviderInterface::class)) {
            throw ApplicationConfigException::invalidValue(
                'HOLIDAY_COUNTRY/HOLIDAY_CALENDAR_VERSION',
                "istniejący provider świąt (otrzymano {$country}/{$version})",
            );
        }

        return new UmulmrumHolidayCalendar($this->calculator, $providerClass);
    }
}
