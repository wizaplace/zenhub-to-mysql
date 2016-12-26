# ZenHub to MySQL

Synchronizes [ZenHub](https://www.zenhub.com/) data (issues, labels, ...) to a MySQL database.

This script relies on the [github-to-mysql](https://github.com/wizaplace/github-to-mysql) script to be run beforehand. Its tables (namely `github_issues`) will be used to fetch additional information from ZenHub.

Features:

- [x] synchronize issue estimates
- [ ] synchronize epics
- [ ] synchronize boards

## Getting started

- install and run [github-to-mysql](https://github.com/wizaplace/github-to-mysql) to fetch GitHub data into MySQL
- clone the repository or [download a stable release](https://github.com/wizaplace/zenhub-to-mysql/releases) and unzip it
- copy `.env.dist` to create a `.env` file
- create the DB tables by running `./zenhub-to-mysql db-init --force`
    You can check which DB queries will be run by removing the `--force` option (the queries will NOT be run if the option is missing).
    
You can also simply run `./zenhub-to-mysql` without arguments and follow the instructions.

The `.env` file contains the configuration to connect to the MySQL database as well as the GitHub and ZenHub token. Alternatively to using this file you can set up all the environment variables it contains.

## Usage

```
./zenhub-to-mysql sync user/repository
```
