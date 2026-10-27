<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SuperAdminUsuariosAccessTest extends TestCase
{
    public function test_is_superadmin_identifies_superadmin_correctly(): void
    {
        $superadmin = new User();
        $superadmin->role_id = 3;
        $superadmin->setRelation('role', new Role(['name' => 'superadministrador']));
        $this->assertTrue($superadmin->isSuperAdmin());

        $supervisor = new User();
        $supervisor->role_id = 1;
        $supervisor->setRelation('role', new Role(['name' => 'supervisor']));
        $this->assertFalse($supervisor->isSuperAdmin());

        $vendedor = new User();
        $vendedor->role_id = 2;
        $vendedor->setRelation('role', new Role(['name' => 'vendedor']));
        $this->assertFalse($vendedor->isSuperAdmin());
    }

    public function test_middleware_allows_active_superadmin(): void
    {
        $superadmin = new User();
        $superadmin->id = 1;
        $superadmin->role_id = 3;
        $superadmin->is_active = true;
        $superadmin->setRelation('role', new Role(['name' => 'superadministrador']));

        $request = Request::create('/supervisor/admin/usuarios', 'GET');
        $request->setUserResolver(fn () => $superadmin);

        $middleware = new EnsureUserIsSuperAdmin();
        $response = $middleware->handle($request, function ($req) {
            return new \Symfony\Component\HttpFoundation\Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_denies_supervisor(): void
    {
        $supervisor = new User();
        $supervisor->id = 2;
        $supervisor->role_id = 1;
        $supervisor->is_active = true;
        $supervisor->setRelation('role', new Role(['name' => 'supervisor']));

        $request = Request::create('/supervisor/admin/usuarios', 'GET');
        $request->setUserResolver(fn () => $supervisor);

        $middleware = new EnsureUserIsSuperAdmin();

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $middleware->handle($request, function () {
            return new \Symfony\Component\HttpFoundation\Response('OK', 200);
        });
    }

    public function test_middleware_denies_inactive_superadmin(): void
    {
        $superadmin = new User();
        $superadmin->id = 3;
        $superadmin->role_id = 3;
        $superadmin->is_active = false;
        $superadmin->setRelation('role', new Role(['name' => 'superadministrador']));

        $request = Request::create('/supervisor/admin/usuarios', 'GET');
        $request->setUserResolver(fn () => $superadmin);

        $middleware = new EnsureUserIsSuperAdmin();

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $middleware->handle($request, function () {
            return new \Symfony\Component\HttpFoundation\Response('OK', 200);
        });
    }

    public function test_middleware_json_request_returns_403_json(): void
    {
        $supervisor = new User();
        $supervisor->id = 5;
        $supervisor->role_id = 1;
        $supervisor->is_active = true;
        $supervisor->setRelation('role', new Role(['name' => 'supervisor']));

        $request = Request::create('/supervisor/admin/usuarios', 'POST');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $supervisor);

        $middleware = new EnsureUserIsSuperAdmin();
        $response = $middleware->handle($request, function () {
            return new \Symfony\Component\HttpFoundation\Response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
    }
}

