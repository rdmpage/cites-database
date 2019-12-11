# Database of citation relationships

Database of citation relationships, sourced from CrossRef, web scraping, etc.

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


## Matching citations to DOIs

Citations may not be linked to DOIs, so we may need to fix this.

match.php matches citations to DOIs in ```microcitations.publications``` table.
