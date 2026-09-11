@php
    $accessModules = app(\App\Services\AccesoVistaService::class)->modules();
    $selectedPermissions = old('_access_form') ? old('permissions', []) : ($rolePermissions ?? []);
    $catalogPermissions = collect($accessModules)->flatMap(fn ($module) => array_merge([$module['permission']], array_column($module['items'], 'access_permission')))->all();
@endphp
<input type="hidden" name="_access_form" value="1">
<div class="mb-3" id="role-access-picker">
    <h5>Módulos y vistas disponibles</h5>
    <p class="text-muted">Marca el módulo y las pantallas que puede abrir. Las consultas y funciones de cada pantalla requieren ese acceso; los permisos de acciones existentes se conservan. Los accesos de varios roles se suman.</p>
    @if(($role->name ?? '') === 'superadmin')
        <div class="alert alert-info">Superadmin siempre tiene acceso completo, independientemente de las casillas.</div>
    @endif
    <label for="access-search" class="form-label">Buscar módulo o vista</label>
    <input type="search" id="access-search" class="form-control mb-3" placeholder="Ejemplo: incentivos, pagos, empleados">
    @foreach($accessModules as $key => $module)
        <fieldset class="border rounded p-3 mb-3 access-module">
            <legend class="fs-6 float-none w-auto px-2">{{ $module['titulo'] }}</legend>
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input module-permission" name="permissions[]" value="{{ $module['permission'] }}" id="module-{{ $key }}" @checked(in_array($module['permission'], $selectedPermissions))>
                    <label for="module-{{ $key }}" class="form-check-label fw-semibold">Acceso al módulo</label>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-select-access="1">Marcar todas</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-access="0">Quitar todas</button>
                </div>
            </div>
            @foreach($module['items'] as $item)
                <div class="form-check ms-3 access-view">
                    <input type="checkbox" class="form-check-input view-permission" name="permissions[]" value="{{ $item['access_permission'] }}" id="view-{{ $key }}-{{ $loop->index }}" @checked(in_array($item['access_permission'], $selectedPermissions))>
                    <label class="form-check-label" for="view-{{ $key }}-{{ $loop->index }}">{{ $item['nombre'] }}</label>
                </div>
            @endforeach
        </fieldset>
    @endforeach
    <details class="border rounded p-3">
        <summary>Permisos de acciones y permisos adicionales</summary>
        <p class="text-muted mt-2">Por ejemplo: crear usuarios, editar roles o cerrar solicitudes. Estos permisos no sustituyen el acceso a la pantalla.</p>
        @foreach($permissions as $permission)
            @unless(in_array($permission->name, $catalogPermissions))
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="perm-{{ $permission->id }}" @checked(in_array($permission->name, $selectedPermissions))>
                    <label class="form-check-label" for="perm-{{ $permission->id }}">{{ $permission->name }}</label>
                </div>
            @endunless
        @endforeach
    </details>
    @error('permissions')<div class="text-danger">{{ $message }}</div>@enderror
    @error('permissions.*')<div class="text-danger">{{ $message }}</div>@enderror
</div>
<script>
document.getElementById('role-access-picker').addEventListener('change', function (event) {
    const fieldset = event.target.closest('.access-module');
    if (!fieldset) return;
    if (event.target.matches('.view-permission') && event.target.checked) {
        fieldset.querySelector('.module-permission').checked = true;
    }
    if (event.target.matches('.module-permission') && !event.target.checked) {
        fieldset.querySelectorAll('.view-permission').forEach(input => input.checked = false);
    }
});
document.getElementById('role-access-picker').addEventListener('click', function (event) {
    const button = event.target.closest('[data-select-access]');
    if (!button) return;
    button.closest('.access-module').querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = button.dataset.selectAccess === '1');
});
document.getElementById('access-search').addEventListener('input', function () {
    const search = this.value.toLocaleLowerCase();
    document.querySelectorAll('.access-module').forEach(fieldset => {
        const moduleMatches = fieldset.querySelector('legend').textContent.toLocaleLowerCase().includes(search);
        let visible = false;
        fieldset.querySelectorAll('.access-view').forEach(row => {
            row.hidden = !moduleMatches && !row.textContent.toLocaleLowerCase().includes(search);
            visible = visible || !row.hidden;
        });
        fieldset.hidden = !visible;
    });
});
</script>
