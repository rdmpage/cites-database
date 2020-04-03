# Database of citation relationships

Database of citation relationships, sourced from CrossRef, web scraping, etc.


## Scripts

```php crossref-to-db.php``` takes DOI and gets citation data from CrossRef

```php pensoft-to-db.php``` takes local Pensoft JATS XML download, extracts citations and tries to enhance

```php match-to-db.php``` does a SQL query to get a list of articles and tries to match them to the local database (and optionally to CrossRef)


## PDF citation extraction

Extract references from PDF

```
php pdf-extract.php
```

## Wikidata

Match cited references to Wikidata items

```
php wikidata-match.php 
```

Generate quick statements for Wikidata

```
php wikidata-cites-quickstatments.php
```

## Citation identifiers

http://opencitations.net/oci

Wikidata example: http://opencitations.net/oci/01027931310-01022252312

## Sources

### CrossRef, Daisy, etc.

```cites.php``` calls external services to get data and dump to SQL.

### BioOne

Need to fetch HTMl for reference page, then parse.

### JStage

Has citation data in Google Scholar tags.

### Akademiai

Extract from web page.

### Taylor and Francis

Need to fetch HTMl for reference page, then parse.




## Matching citations to DOIs and other GUIDs

Citations may not be linked to DOIs, so we may need to fix this.

match.php matches citations to DOIs in ```microcitations.publications``` table (i. E., does SQL query

```match-to-db.php``` uses micro citation web service to match to any GUIDs in ```microcitations.publications``` table


## Example refs

[Darwininitium – a new fully pseudosigmurethrous orthurethran genus from Nepal (Gastropoda, Pulmonata, Cerastidae)](https://doi.org/10.3897/zookeys.175.2755) has very few linked references, but many can be linked.


