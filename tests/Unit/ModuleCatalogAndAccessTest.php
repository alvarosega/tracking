<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Role;
use App\Models\SupervisorModulePermission;
use App\Models\User;
use App\Services\Permisos\ModuleCatalogService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class ModuleCatalogAndAccessTest extends TestCase
{
    public function test_catalog_contains_all_modules_and_submodules(): void
    {
        $catalog = ModuleCatalogService::getCatalog();
        $this->assertNotEmpty($catalog);

        $moduleIds = array_column($catalog, 'id');

        // Verificar módulos principales
        $expectedModules = [
            'inicio',
            'ruteo',
            'avance',
            'preventas',
            'tracking',
            'visitas',
            'altas-ediciones',
            'saneamiento',
            'jornadas',
            'horarios',
            'modulos',
            'ingesta',
        ];

        foreach ($expectedModules as $expected) {
            $this->assertContains($expected, $moduleIds, "El módulo '{$expected}' no se encuentra en el catálogo.");
        }

        // Verificar submódulos de módulos clave
        $catalogIndexed = [];
        foreach ($catalog as $item) {
            $catalogIndexed[$item['id']] = array_column($item['submodules'], 'id');
        }

        $this->assertContains('listado', $catalogIndexed['altas-ediciones']);
        $this->assertContains('cercanos', $catalogIndexed['altas-ediciones']);

        $this->assertContains('control', $catalogIndexed['jornadas']);
        $this->assertContains('cierre-masivo', $catalogIndexed['jornadas']);

        $this->assertContains('matriz', $catalogIndexed['horarios']);
        $this->assertContains('activos', $catalogIndexed['horarios']);

        $this->assertContains('matriz', $catalogIndexed['modulos']);
        $this->assertContains('global', $catalogIndexed['modulos']);
        $this->assertContains('inventarios', $catalogIndexed['modulos']);

        $this->assertContains('dashboard', $catalogIndexed['ingesta']);
        $this->assertContains('ventas', $catalogIndexed['ingesta']);
        $this->assertContains('preventas', $catalogIndexed['ingesta']);
        $this->assertContains('ruteo', $catalogIndexed['ingesta']);
        $this->assertContains('visitas', $catalogIndexed['ingesta']);
        $this->assertContains('inventario', $catalogIndexed['ingesta']);
        $this->assertContains('cancelados', $catalogIndexed['ingesta']);
        $this->assertContains('snapshots', $catalogIndexed['ingesta']);
    }

    public function test_has_module_access_default_deny_when_submodule_not_assigned(): void
    {
        $supervisor = new User();
        $supervisor->id = 10;
        $supervisor->setRelation('role', new Role(['name' => 'supervisor']));

        // Permisos configurados:
        // 'ruteo' principal habilitado
        // 'ruteo.visor' habilitado
        // 'ruteo.cercanos' NO configurado
        $p1 = new SupervisorModulePermission([
            'user_id' => 10,
            'module_key' => 'ruteo',
            'submodule_key' => '',
            'is_allowed' => true,
            'can_write' => true,
        ]);
        $p2 = new SupervisorModulePermission([
            'user_id' => 10,
            'module_key' => 'ruteo',
            'submodule_key' => 'visor',
            'is_allowed' => true,
            'can_write' => true,
        ]);

        $supervisor->setRelation('modulePermissions', new Collection([$p1, $p2]));

        // Submódulo otorgado
        $this->assertTrue($supervisor->hasModuleAccess('ruteo', 'visor'));

        // Submódulo NO otorgado debe ser estrictamente DENEGADO (false)
        $this->assertFalse($supervisor->hasModuleAccess('ruteo', 'cercanos'));

        // Módulo no otorgado debe ser denegado
        $this->assertFalse($supervisor->hasModuleAccess('ingesta', 'ventas'));
    }

    public function test_has_module_access_denies_when_submodule_is_explicitly_false(): void
    {
        $supervisor = new User();
        $supervisor->id = 11;
        $supervisor->setRelation('role', new Role(['name' => 'supervisor']));

        $p1 = new SupervisorModulePermission([
            'user_id' => 11,
            'module_key' => 'ingesta',
            'submodule_key' => '',
            'is_allowed' => true,
            'can_write' => true,
        ]);
        $p2 = new SupervisorModulePermission([
            'user_id' => 11,
            'module_key' => 'ingesta',
            'submodule_key' => 'ventas',
            'is_allowed' => true,
            'can_write' => true,
        ]);
        $p3 = new SupervisorModulePermission([
            'user_id' => 11,
            'module_key' => 'ingesta',
            'submodule_key' => 'preventas',
            'is_allowed' => false,
            'can_write' => true,
        ]);

        $supervisor->setRelation('modulePermissions', new Collection([$p1, $p2, $p3]));

        $this->assertTrue($supervisor->hasModuleAccess('ingesta', 'ventas'));
        $this->assertFalse($supervisor->hasModuleAccess('ingesta', 'preventas'));
        $this->assertFalse($supervisor->hasModuleAccess('ingesta', 'visitas'));
    }

    public function test_superadmin_always_has_access(): void
    {
        $superadmin = new User();
        $superadmin->id = 1;
        $superadmin->setRelation('role', new Role(['name' => 'superadministrador']));
        $superadmin->setRelation('modulePermissions', new Collection([]));

        $this->assertTrue($superadmin->hasModuleAccess('ruteo', 'cercanos'));
        $this->assertTrue($superadmin->hasModuleAccess('ingesta', 'ventas'));
        $this->assertTrue($superadmin->hasModuleAccess('modulos', 'global'));
    }

    public function test_administrador_role_can_have_custom_permissions(): void
    {
        $admin = new User();
        $admin->id = 15;
        $admin->setRelation('role', new Role(['name' => 'administrador']));

        $p1 = new SupervisorModulePermission([
            'user_id' => 15,
            'module_key' => 'ingesta',
            'submodule_key' => '',
            'is_allowed' => true,
            'can_write' => true,
        ]);
        $p2 = new SupervisorModulePermission([
            'user_id' => 15,
            'module_key' => 'ingesta',
            'submodule_key' => 'preventas',
            'is_allowed' => true,
            'can_write' => true,
        ]);

        $admin->setRelation('modulePermissions', new Collection([$p1, $p2]));

        $this->assertTrue($admin->canHaveCustomModulePermissions());
        $this->assertTrue($admin->hasModuleAccess('ingesta', 'preventas'));
        $this->assertFalse($admin->hasModuleAccess('ingesta', 'ventas'));
    }
}
