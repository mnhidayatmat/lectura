<?php

namespace Tests\Feature\Api\V1;

class TenantContextApiTest extends ApiTestCase
{
    public function test_member_receives_the_tenant_context(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'context'))
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', $tenant->slug)
            ->assertJsonPath('data.roles', ['student'])
            ->assertJsonPath('data.active_role', 'student');
    }

    public function test_requires_authentication(): void
    {
        $tenant = $this->createTenant();

        $this->getJson($this->tenantApi($tenant, 'context'))->assertUnauthorized();
    }

    public function test_non_member_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $outsider = $this->createMember($this->createTenant(), 'student');

        $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, 'context'))->assertForbidden();
    }

    public function test_inactive_institution_is_not_found(): void
    {
        $tenant = $this->createTenant(['is_active' => false]);
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'context'))->assertNotFound();
    }

    public function test_role_header_selects_the_active_role(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createMember($tenant, 'lecturer');
        $this->addRole($tenant, $user, 'student');

        $this->actingAsApi($user, 'student')->getJson($this->tenantApi($tenant, 'context'))
            ->assertOk()
            ->assertJsonPath('data.active_role', 'student');
    }

    public function test_role_header_cannot_claim_a_role_the_user_lacks(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student, 'lecturer')->getJson($this->tenantApi($tenant, 'context'))->assertForbidden();
    }
}
