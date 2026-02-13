<!doctype html>
<html lang="es" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg"
    data-sidebar-image="none">

<head>
    <meta charset="utf-8" />
    <title>Iniciar Sesión | Grupo Joselito</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CRM Grupo Joselito - Inicio de Sesión" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">

    <!-- Bootstrap Css -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- App Css -->
    <link href="{{ asset('css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Custom Css -->
    <link href="{{ asset('css/custom.min.css') }}" rel="stylesheet" type="text/css" />

    <style>
        .auth-page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #405189 0%, #0ab39c 100%);
        }

        .auth-card {
            max-width: 450px;
            width: 100%;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .auth-logo h2 {
            color: #405189;
            font-weight: 700;
        }

        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="auth-page-wrapper">
        <div class="auth-card">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-body p-4 p-sm-5">
                    <!-- Logo -->
                    <div class="auth-logo">
                        <a href="/">
                            <h2><i class="ri-shield-user-line me-2"></i>Grupo Joselito</h2>
                        </a>
                        <p class="text-muted mt-2">Inicia sesión para continuar</p>
                    </div>

                    <!-- Mensaje de error -->
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="ri-error-warning-line me-2 fs-5"></i>
                                <div>
                                    @foreach ($errors->all() as $error)
                                        <span>{{ $error }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <!-- Formulario -->
                    <form action="{{ route('login') }}" method="POST">
                        @csrf

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-mail-line"></i></span>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" placeholder="correo@ejemplo.com"
                                    value="{{ old('email') }}" required autofocus>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    id="password" name="password" placeholder="Ingresa tu contraseña" required>
                                <button class="btn btn-light password-toggle" type="button"
                                    onclick="togglePassword()">
                                    <i class="ri-eye-off-line" id="password-icon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Recordarme -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember"
                                {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">Recordarme</label>
                        </div>

                        <!-- Botón de Login -->
                        <div class="mt-4">
                            <button class="btn btn-primary w-100 py-2" type="submit">
                                <i class="ri-login-circle-line me-1"></i> Iniciar Sesión
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4">
                <p class="text-white-50 mb-0">
                    &copy; {{ date('Y') }} Grupo Joselito. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </div>

    <!-- Javascript -->
    <script src="{{ asset('libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('ri-eye-off-line');
                icon.classList.add('ri-eye-line');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('ri-eye-line');
                icon.classList.add('ri-eye-off-line');
            }
        }
    </script>
</body>

</html>
