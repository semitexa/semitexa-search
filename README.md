# Semitexa Search

Structured search with tenant-aware scoping, ORM backend, and optional LLM query planning.

## Install

Included in every project created by the installer (https://semitexa.com/install.sh).

## Purpose

Provides a search abstraction with pluggable backends. The default ORM backend translates structured search requests into database queries with relevance ranking. Optional LLM integration enables natural-language query planning.

## Role in Semitexa

Depends on `semitexa/core`, `semitexa/orm`, `semitexa/tenancy` and `semitexa/prompt`. Suggests `semitexa/llm` for LLM-assisted query planning. Search results are automatically scoped to the active tenant.

## Key Features

- `SearchRequest` / `SearchResult` / `SearchHit` value objects
- `OrmSearchBackend`: SQL `LIKE` matching (exact, prefix or contains) over the indexed fields
- `OrmSearchTranslator` converts search syntax to ORM queries
- `OrmRankingStrategy` for relevance scoring
- `SearchIndexDefinition` for index metadata
- Tenant-scoped search isolation
- Optional LLM-assisted query planning
