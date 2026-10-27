<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Search\Application\Service\SearchManager;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Exception\SearchValidationException;
use Semitexa\Tenancy\Context\TenantContext;
use Semitexa\Tenancy\Context\TenantContextStore;

/**
 * A caller-supplied tenantId beat the current tenant, and no tenant context
 * silently meant tenant 'default'.
 */
final class SearchManagerTenantScopeTest extends TestCase
{
    private string|false $tenancyEnabled;

    protected function setUp(): void
    {
        $this->tenancyEnabled = getenv('TENANCY_ENABLED');
        (new \ReflectionProperty(SearchManager::class, 'warnedNoContext'))->setValue(null, false);
    }

    protected function tearDown(): void
    {
        TenantContextStore::shared()->clear();
        putenv($this->tenancyEnabled === false ? 'TENANCY_ENABLED' : 'TENANCY_ENABLED=' . $this->tenancyEnabled);
    }

    #[Test]
    public function the_current_tenant_fills_the_request_and_its_own_id_passes(): void
    {
        TenantContextStore::shared()->set(TenantContext::fromResolution('acme', 'test'));

        self::assertSame('acme', $this->scoped(new SearchRequest(index: 'products'))->tenantId);
        self::assertSame('acme', $this->scoped(new SearchRequest(index: 'products', tenantId: 'acme'))->tenantId);
    }

    #[Test]
    public function another_tenants_id_is_refused_inside_a_tenant_context(): void
    {
        TenantContextStore::shared()->set(TenantContext::fromResolution('acme', 'test'));

        $this->expectException(SearchValidationException::class);
        $this->expectExceptionMessageMatches("/'globex' is not the current tenant 'acme'/");
        $this->scoped(new SearchRequest(index: 'products', tenantId: 'globex'));
    }

    #[Test]
    public function the_default_tenant_may_name_another_as_a_command_may(): void
    {
        // The root site (no tenant resolved) is the default tenant's context.
        TenantContextStore::shared()->set(TenantContext::default());
        self::assertSame('globex', $this->scoped(new SearchRequest(index: 'products', tenantId: 'globex'))->tenantId);

        TenantContextStore::shared()->clear();
        self::assertSame('globex', $this->scoped(new SearchRequest(index: 'products', tenantId: 'globex'))->tenantId);
    }

    #[Test]
    public function no_context_on_a_multi_tenant_install_is_tenant_default_with_a_warning(): void
    {
        // Tenancy means 'default' by no tenant (a job queued from the root site carries none).
        TenantContextStore::shared()->clear();
        putenv('TENANCY_ENABLED=true');

        self::assertSame('default', $this->scoped(new SearchRequest(index: 'products'))->tenantId);
        self::assertTrue((new \ReflectionProperty(SearchManager::class, 'warnedNoContext'))->getValue());
    }

    #[Test]
    public function no_context_without_tenancy_is_tenant_default(): void
    {
        TenantContextStore::shared()->clear();
        putenv('TENANCY_ENABLED=false');

        self::assertSame('default', $this->scoped(new SearchRequest(index: 'products'))->tenantId);
    }

    private function scoped(SearchRequest $request): SearchRequest
    {
        return (new \ReflectionMethod(SearchManager::class, 'enforceTenantScope'))
            ->invoke((new \ReflectionClass(SearchManager::class))->newInstanceWithoutConstructor(), $request, true);
    }
}
