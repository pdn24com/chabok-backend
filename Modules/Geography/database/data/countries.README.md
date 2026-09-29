# Country reference data

`countries.json` contains the 249 ISO 3166-1 countries and territories, sorted by
their two-letter code. Each entry includes `country_code` (alpha-2), `alpha3_code`,
`numeric_code` (a three-character string preserving leading zeros), `name_fa`,
and `name_en`. Display names are localized names rather than official long names.

The data comes from these pinned Unicode CLDR 48.1 sources:

- [English names](https://github.com/unicode-org/cldr-json/blob/48.1.0/cldr-json/cldr-localenames-full/main/en/territories.json)
- [Persian names](https://github.com/unicode-org/cldr-json/blob/48.1.0/cldr-json/cldr-localenames-full/main/fa/territories.json)
- [Country code mappings](https://github.com/unicode-org/cldr-json/blob/48.1.0/cldr-json/cldr-core/supplemental/codeMappings.json)
- [Region validity](https://github.com/unicode-org/cldr/blob/release-48-1/common/validity/region.xml)

To reproduce the selection, expand the regular region ranges in `region.xml`,
join those codes to `codeMappings`, retain entries with both an alpha-3 code and
a numeric code below 900, then join the English and Persian territory names.
This excludes macroregions, historical codes, reserved codes and user-assigned
codes. The resulting 249 code triples were also checked against the system
`iso-codes` ISO 3166-1 dataset.

CLDR data is distributed under the Unicode License V3, included in
`countries.LICENSE`. Updating the source requires reviewing the country set and
updating the expected seed/test counts if ISO assignments change.

`CountrySeeder` reads only this checked-in JSON, validates it before writing,
and upserts by `country_code`. It preserves database IDs, creation timestamps,
and existing `is_active` choices when run again. It is included in the default
`DatabaseSeeder`; neither application startup nor HTTP requests download or seed
country data.
