# bibfinder

Manage and search an Elastic database of academic articles, deployed on [Heroku](https://bibfinder.herokuapp.com/). Nothing fancy, just want to be able to (a) find an article, and (b) provide API to match citation strings to articles.

**Basic idea**: Get articles as CSL JSON from ether local database or external sources, create simple [Elasticsearch](https://www.elastic.co/elasticsearch/) schema and upload documents to Elasticsearch in the cloud (e.g., Bitnami). 

**Key features**:
- Simple search, API [api.php?q=new%20species](https://bibfinder.herokuapp.com/api.php?q=new%20species) returns result as [schema.org](http://schema.org/) `DataFeed`.
- Format article for citing using [Citation.js](https://citation.js.org)
- Reconciliation API with [web interface](https://bibfinder.herokuapp.com/match.html), API call [https://bibfinder.herokuapp.com/api_reconciliation.php](https://bibfinder.herokuapp.com/api_reconciliation.php)
- Cluster records for “same” article based on same GUID (e.g., DOI).






