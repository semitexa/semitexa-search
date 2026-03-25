# semitexa/search

Structured search with tenant-aware scoping, ORM backend, and optional LLM query planning.

## Purpose

Provides a search abstraction with pluggable backends. The default ORM backend translates structured search requests into database queries with relevance ranking. Optional LLM integration enables natural-language query planning.

## Role in Semitexa

Depends on `semitexa/core`, `semitexa/orm`, and `semitexa/tenancy`. Suggests `semitexa/llm` for LLM-assisted query planning. Search results are automatically scoped to the active tenant.

## Key Features

- `SearchRequest` / `SearchResult` / `SearchHit` value objects
- `OrmSearchBackend` with full-text search support
- `OrmSearchTranslator` converts search syntax to ORM queries
- `OrmRankingStrategy` for relevance scoring
- `SearchIndexDefinition` for index metadata
- Tenant-scoped search isolation
- Optional LLM-assisted query planning
