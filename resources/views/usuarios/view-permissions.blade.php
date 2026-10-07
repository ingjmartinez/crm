<div class="col-12 mb-3">
    <label class="form-label fw-semibold">Acceso a páginas</label>
    <p class="form-text mt-0">Heredar usa el acceso del rol. Permitir o Bloquear aplica solo a este usuario. Superadmin siempre conserva acceso total.</p>
    @error('view_permissions') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
    <div class="border rounded p-2" style="max-height: 420px; overflow-y: auto;">
        @php $permissionIndex = 0; @endphp
        @foreach ($viewPermissionsByModule as $module => $pages)
            <details class="border rounded mb-2" @if($loop->first) open @endif>
                <summary class="p-2 fw-semibold">{{ ucwords(str_replace('_', ' ', $module)) }} <span class="badge bg-secondary ms-1">{{ $pages->count() }}</span></summary>
                <div class="px-2 pb-2">
                    @foreach ($pages as $page)
                        @php
                            $access = old('view_permissions.'.$permissionIndex.'.access', $userViewPermissions[$page['permission']] ?? 'inherit');
                        @endphp
                        <div class="row g-2 align-items-center py-2 border-top">
                            <div class="col-md-8">
                                <label class="form-label mb-0" for="view-permission-{{ $permissionIndex }}">{{ $page['name'] }}</label>
                                <div class="small text-muted">{{ $page['permission'] }}</div>
                                <input type="hidden" name="view_permissions[{{ $permissionIndex }}][permission]" value="{{ $page['permission'] }}">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" id="view-permission-{{ $permissionIndex }}" name="view_permissions[{{ $permissionIndex }}][access]">
                                    <option value="inherit" @selected($access === 'inherit')>Heredar del rol</option>
                                    <option value="allow" @selected($access === 'allow')>Permitir</option>
                                    <option value="deny" @selected($access === 'deny')>Bloquear</option>
                                </select>
                                @error('view_permissions.'.$permissionIndex.'.access') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        @php $permissionIndex++; @endphp
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</div>
