<?php

declare(strict_types=1);

namespace Semitexa\Search;

use Semitexa\Core\Attribute\Capability;

/**
 * What this package offers, for the capability catalog.
 *
 * Without this the package is invisible to anyone whose project has not
 * installed it - which is precisely the audience worth telling, since they are
 * the ones about to build it by hand. The convention is one `Capabilities` class
 * per package: a definite place to look, and a definite place for a guard to
 * check.
 *
 * Nothing reads this at runtime.
 */
#[Capability(
    id: 'search.queries',
    summary: 'Declared search indexes with tenant-scoped queries, filters and sorting over an ORM backend.',
    useWhen: 'Users type free text and expect ranked matches across fields, with filters they can combine.',
    avoidWhen: 'An exact lookup by one column. A repository query is faster to write and faster to run.',
    replaces: [
        'LIKE %term% concatenated into a query, with tenant scoping remembered per call site',
        'a bespoke filter parser reinvented per screen',
    ],
)]
final class Capabilities
{
}
