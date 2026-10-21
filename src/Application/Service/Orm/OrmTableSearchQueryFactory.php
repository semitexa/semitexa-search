<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Orm;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\SoftDelete;
use Semitexa\Orm\Attribute\TenantScoped;
use Semitexa\Orm\OrmManager;
use Semitexa\Search\Domain\Contract\OrmSearchQueryFactoryInterface;
use Semitexa\Search\Domain\Contract\OrmSearchQueryInterface;
use Semitexa\Search\Domain\Enum\SearchScope;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Exception\SearchBackendException;

/**
 * The query factory the ORM backend ships with: an index whose `entityClass`
 * is a resource model with #[FromTable] is searched in that table. Nothing
 * implemented the contract, so every search failed until a project wrote its
 * own. A project's own implementation of the contract still takes over.
 */
#[SatisfiesServiceContract(of: OrmSearchQueryFactoryInterface::class)]
final class OrmTableSearchQueryFactory implements OrmSearchQueryFactoryInterface
{
    #[InjectAsReadonly]
    protected OrmManager $orm;

    public function createQuery(SearchIndexDefinition $definition): OrmSearchQueryInterface
    {
        $class = $definition->entityClass;
        $reflection = $class !== null && class_exists($class) ? new \ReflectionClass($class) : null;
        $fromTable = $reflection?->getAttributes(FromTable::class)[0] ?? null;
        if ($reflection === null || $fromTable === null) {
            throw new SearchBackendException(
                "Index '{$definition->name}' has no table to search: its entityClass must be a resource model with #[FromTable], or bind your own OrmSearchQueryFactoryInterface.",
            );
        }

        // The ORM's own scoping, which a repository applies and raw SQL does
        // not: a Global index over tenant data searched every tenant.
        $tenantScoped = $reflection->getAttributes(TenantScoped::class)[0] ?? null;
        $columnFor = [];
        if ($tenantScoped !== null) {
            if ($definition->scopeMode === SearchScope::Global) {
                throw new SearchBackendException(
                    "Index '{$definition->name}' is Global, but {$class} is #[TenantScoped]: a search would read every tenant's rows. Make the index Tenant-scoped.",
                );
            }
            $tenantProperty = $tenantScoped->newInstance()->column;
            if ($tenantProperty !== null) {
                $columnFor['tenant_id'] = self::columnOf($reflection, $tenantProperty);
            }
        }
        $softDelete = $reflection->getAttributes(SoftDelete::class)[0] ?? null;

        return new TableSearchQuery(
            $this->orm->getQueryAdapter(),
            $fromTable->newInstance()->name,
            // The backslash as an expression, not a literal: '\\' is two
            // characters under MySQL's NO_BACKSLASH_ESCAPES and failed every search.
            $this->orm->getDriver() === 'sqlite' ? ' ESCAPE char(92)' : ' ESCAPE CHAR(92 USING utf8mb4)',
            $columnFor,
            $softDelete !== null ? self::columnOf($reflection, $softDelete->newInstance()->column) : null,
        );
    }

    /** The column a model property is stored in: its #[Column(name:)], or the property name. */
    private static function columnOf(\ReflectionClass $model, string $property): string
    {
        if (!$model->hasProperty($property)) {
            return $property;
        }
        $column = $model->getProperty($property)->getAttributes(Column::class)[0] ?? null;

        return $column?->newInstance()->name ?? $property;
    }
}
