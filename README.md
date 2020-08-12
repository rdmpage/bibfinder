# bibfinder

Manage and search an Elastic database of academic articles, deployed on [Heroku](https://bibfinder.herokuapp.com/). Nothing fancy, just want to be able to (a) find an article, and (b) provide API to match citation strings to articles.

**Basic idea**: Get articles as CSL JSON from ether local database or external sources, create simple [Elasticsearch](https://www.elastic.co/elasticsearch/) schema and upload documents to Elasticsearch in the cloud (e.g., Bitnami). 

**Key features**:
- Simple search, API [api.php?q=new%20species](https://bibfinder.herokuapp.com/api.php?q=new%20species) returns result as [schema.org](http://schema.org/) `DataFeed`.
- Format article for citing using [Citation.js](https://citation.js.org)
- Reconciliation API with [web interface](https://bibfinder.herokuapp.com/match.html), API call [https://bibfinder.herokuapp.com/api_reconciliation.php](https://bibfinder.herokuapp.com/api_reconciliation.php)
- Cluster records for “same” article based on same GUID (e.g., DOI). http://bibfinder.herokuapp.com/?q=Two%20new%20neotropical%20genera%20of%20Embiidae


## Clusters

### Neoplerochila

https://doi.org/10.21248/CONTRIB.ENTOMOL.57.2.419-428 [The species of the Afrotropical genus Neoplerochila Duarte Rodrigues, 1982 (Insecta, Heteroptera, Tingidae, Tinginae).]  and Die Arten der afrotropischen Gattung Neoplerochila Duarte Rodrigues, 1982 (Insecta, Heteroptera, Tingidae, Tinginae). From DataCite and BioNames. Same article, titles in different languages, clustered on DOI

### Two new neotropical genera of Embiidae (Embioptera, Insecta)

JSTOR and BioNames examples cluster on JSTOR, but BioStor not because CSL lacks JSTOR identifier 

### The genus Paragryllodes Karny, 1909

In BioNames and BioStor, not linked yet as BioNames has no BioStor record

### Mission Scientifique de l'Omo

Not clustered as not clustering on BioStor.









