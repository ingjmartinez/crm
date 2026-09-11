<?php

namespace App\Services;

class AccesoVistaService
{
    private ?array $catalog = null;

    /** @return array<string, array<string, mixed>> */
    public function modules(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        $modules = config('module_hubs', []);
        $modules['recursos_humanos'] = ['titulo' => 'Recursos Humanos', 'items' => config('recursos_humanos', [])];
        $modules['reportes'] = ['titulo' => 'Reportes', 'items' => config('reportes', [])];
        $modules = array_merge($modules, config('view_access.extra_modules', []));

        foreach (['lotobet', 'lotonet'] as $system) {
            foreach (['ventas-por-usuario', 'faltantes', 'ventas-por-producto', 'recargas', 'premios', 'pagos-misma-empresa', 'pagos-aotra-empresa', 'pagos-porotra-empresa', 'asistencias', 'ventas-flash'] as $page) {
                $modules['apis_ventas']['items'][] = ['nombre' => ucfirst(str_replace('-', ' ', $page)).' '.ucfirst($system), 'url' => '/'.$page.'-'.$system];
            }
        }
        $modules['apis_ventas']['items'][] = ['nombre' => 'Paqueticos Lotonet', 'url' => '/paquetico-lotonet'];

        foreach ($modules as $key => &$module) {
            $module['url'] ??= '/'.str_replace('_', '-', $key);
            $module['permission'] = $key.'.view';
            $module['items'] = array_values(array_filter($module['items'] ?? [], fn (array $item): bool => (bool) ($item['activo'] ?? true)));
            foreach ($module['items'] as &$item) {
                $item['access_permission'] = $this->permission($item['url']);
                $item['descripcion'] ??= 'Accede a '.$item['nombre'].'.';
                $item['icono'] ??= 'ri-apps-2-line';
                $item['categoria'] ??= 'General';
            }
            unset($item);
        }
        unset($module);

        return $this->catalog = $modules;
    }

    public function permission(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return 'vistas.'.str_replace(['/', '-'], ['.', '_'], $path ?: 'inicio');
    }

    public function canView(?\App\Models\User $user, string $url): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole('superadmin')) {
            return true;
        }
        $path = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');
        foreach ($this->modules() as $module) {
            foreach ($module['items'] as $item) {
                if ($item['url'] !== $path) {
                    continue;
                }

                return $user->can($module['permission'])
                    && $user->can($item['access_permission'])
                    && (empty($item['permission']) || $user->can($item['permission']))
                    && (empty($item['role']) || $user->hasRole($item['role']));
            }
        }

        return false;
    }

    public function canModule(?\App\Models\User $user, string $key): bool
    {
        $module = $this->modules()[$key] ?? null;
        if (! $module || ! $user || ! $user->can($module['permission'])) {
            return false;
        }
        foreach ($module['items'] as $item) {
            if ($this->canView($user, $item['url'])) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function viewsForPath(string $path): array
    {
        $path = '/'.trim($path, '/');
        $matches = [];
        foreach ($this->modules() as $module) {
            foreach ($module['items'] as $item) {
                $base = $item['url'];
                if ($path === $base || ($base !== '/' && str_starts_with($path, $base.'/'))) {
                    $matches[$base] = [$base];
                }
                if (str_ends_with($base, '-view')) {
                    $alias = substr($base, 0, -5);
                    if ($path === $alias || str_starts_with($path, $alias.'/')) {
                        $matches[$alias] = [$base];
                    }
                }
            }
        }
        foreach (config('view_access.aliases', []) as $prefix => $views) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $matches[$prefix] = $views;
            }
        }
        uksort($matches, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $matches === [] ? [] : reset($matches);
    }

    public function canPath(?\App\Models\User $user, string $path): bool
    {
        foreach ($this->viewsForPath($path) as $view) {
            if ($this->canView($user, $view)) {
                return true;
            }
        }

        return false;
    }

    public function syncPermissions(): void
    {
        foreach ($this->modules() as $module) {
            \Spatie\Permission\Models\Permission::findOrCreate($module['permission'], 'web');
            foreach ($module['items'] as $item) {
                \Spatie\Permission\Models\Permission::findOrCreate($item['access_permission'], 'web');
            }
        }
    }
}
