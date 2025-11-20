<?php
// Esto permite acceder a variables como $_SESSION['user_id'] para saber si el usuario está logueado.
session_start();

// Esto nos da acceso a la variable $pdo para hacer consultas.
require_once 'src/config.php';

// 3. Protección de acceso
// Si el usuario YA inició sesión, no tiene sentido que vea esta página.
// Lo redirigimos directamente al panel principal (index.php).
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit; // Detiene la ejecución del script aquí.
}

// Variables para guardar mensajes de error si algo falla.
$login_error = null;
$register_error = null;
$initial_panel_class = ''; // Variable para decidir qué panel mostrar (Login o Registro) al cargar.

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Verificamos si el formulario enviado tiene la acción 'register'
    if (isset($_POST['action']) && $_POST['action'] == 'register') {
        
        // Mantenemos el panel de registro abierto si hay un error (para que no se cierre)
        $initial_panel_class = 'right-panel-active'; 
        
        // Obtenemos y limpiamos los datos que escribió el usuario
        $username = trim($_POST['username']); // trim() elimina espacios en blanco al inicio y final
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // VALIDACIONES BÁSICAS
        if (empty($username) || empty($email) || empty($password)) {
            $register_error = "Por favor, completa todos los campos.";
        } elseif (strlen($password) < 8) { // La contraseña debe ser segura (mínimo 8 caracteres)
            $register_error = "La contraseña debe tener al menos 8 caracteres.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Verifica que sea un email real (@ y .)
            $register_error = "El formato del email no es válido.";
        } else {
                        
            // 1. Encriptamos la contraseña (nunca se guarda texto plano)
            // PASSWORD_DEFAULT usa el algoritmo bcrypt (muy seguro).
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 2. Preparamos la consulta SQL para insertar
            $sql = "INSERT INTO users (username, email, password_hash) 
                    VALUES (:username, :email, :password_hash)";
            
            try {
                // Preparamos la sentencia (esto evita hackeos por inyección SQL)
                $stmt = $pdo->prepare($sql);
                
                // Ejecutamos la sentencia con los datos reales
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $hashed_password
                ]);

                // ¡ÉXITO! El usuario se creó.
                // Redirigimos a esta misma página con un mensaje de éxito.
                header("Location: auth.php?status=success");
                exit;

            } catch (PDOException $e) {
                // Si falla, atrapamos el error (ej. si el email ya existe)
                if ($e->getCode() == 23000) { // Código 23000 significa "Dato duplicado" en MySQL
                    $register_error = "El nombre de usuario o el email ya están registrados.";
                } else {
                    $register_error = "Error al registrar: " . $e->getMessage();
                }
            }
        }
    }

    // Verificamos si el formulario enviado tiene la acción 'login'
    if (isset($_POST['action']) && $_POST['action'] == 'login') {
        
        // Obtenemos los datos del formulario de login
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            $login_error = "Por favor, completa todos los campos.";
        } else {
            
            // 1. Buscamos al usuario en la BD usando su email
            $sql = "SELECT id, username, password_hash FROM users WHERE email = :email";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['email' => $email]);
                
                // Obtenemos el resultado (si existe)
                $user = $stmt->fetch();

                // 2. Verificamos dos cosas:
                //    a) Que el usuario exista ($user no sea falso)
                //    b) Que la contraseña escrita coincida con la encriptada en la BD (password_verify)
                if ($user && password_verify($password, $user['password_hash'])) {
                    
                    // ¡CREDENCIALES CORRECTAS!
                    
                    // Regeneramos el ID de sesión por seguridad (evita robo de sesiones)
                    session_regenerate_id(true); 
                    
                    // Guardamos los datos del usuario en la sesión
                    // Esto es lo que lo mantiene "conectado" mientras navega
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    // Lo enviamos a la página principal
                    header("Location: index.php");
                    exit;

                } else {
                    // Si el usuario no existe o la contraseña está mal
                    $login_error = "Email o contraseña incorrectos.";
                }

            } catch (PDOException $e) {
                $login_error = "Error de base de datos: " . $e->getMessage();
            }
        }
    }
}

// Lógica visual: Si la URL tiene "?panel=register", forzamos que se muestre el panel de registro
if (isset($_GET['panel']) && $_GET['panel'] == 'register') {
    $initial_panel_class = 'right-panel-active';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | AeroFic</title>
    
    <!-- Enlazamos la hoja de estilos específica para login/registro -->
    <link rel="stylesheet" href="public/css/auth.css">
    
    <!-- Iconos para redes sociales (opcional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Icono de la pestaña -->
    <link rel="icon" type="image/png" href="public/images/logo.png">
</head>
<body>

    <!-- Contenedor principal del efecto Slider -->
    <!-- La clase PHP $initial_panel_class decide qué lado se muestra primero -->
    <div class="container <?php echo $initial_panel_class; ?>" id="container">
        
        <!-- =================== -->
        <!-- FORMULARIO DE REGISTRO -->
        <!-- =================== -->
        <div class="form-container sign-up-container">
            <!-- Envía los datos a este mismo archivo (auth.php) -->
            <form action="auth.php" method="POST">
                <h1>Crear Cuenta</h1>
                
                <!-- Muestra error de registro si existe -->
                <?php if ($register_error): ?>
                    <p class="form-error"><?php echo $register_error; ?></p>
                <?php endif; ?>

                <span>Usa tu email para registrarte</span>
                
                <!-- Campo oculto para decirle a PHP que esto es un REGISTRO -->
                <input type="hidden" name="action" value="register">
                
                <input type="text" name="username" placeholder="Nombre de Usuario" required />
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Contraseña (mín. 8 caracteres)" required />
                <button type="submit">Registrarse</button>
            </form>
        </div>
        
        <div class="form-container sign-in-container">
            <form action="auth.php" method="POST">
                <h1>Iniciar Sesión</h1>
                
                <!-- Muestra error de login si existe -->
                <?php if ($login_error): ?>
                    <p class="form-error"><?php echo $login_error; ?></p>
                <?php endif; ?>

                <!-- Mensaje de éxito si viene de registrarse -->
                <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <p style="color: green; font-weight: bold; font-size: 13px;">
                        ¡Registro completado! Por favor, inicia sesión.
                    </p>
                <?php endif; ?>

                <span>Usa tu cuenta</span>
                
                <!-- Campo oculto para decirle a PHP que esto es un LOGIN -->
                <input type="hidden" name="action" value="login">
                
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Contraseña" required />
                
                <button type="submit">Iniciar Sesión</button>
            </form>
        </div>

        <div class="overlay-container">
            <div class="overlay">
                
                <!-- Panel izquierdo (Visible cuando estás en Registro) -->
                <div class="overlay-panel overlay-left">
                    <h1>¡Bienvenido de vuelta!</h1>
                    <p>Para seguir conectado con nosotros, inicia sesión con tu información personal</p>
                    <!-- Botón para deslizar hacia Login -->
                    <button class="ghost" id="signIn">Iniciar Sesión</button>
                </div>
                
                <!-- Panel derecho (Visible cuando estás en Login) -->
                <div class="overlay-panel overlay-right">
                    <h1>¡Hola, Amigo!</h1>
                    <p>Ingresa tus datos personales y comienza tu viaje con nosotros</p>
                    <!-- Botón para deslizar hacia Registro -->
                    <button class="ghost" id="signUp">Registrarse</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enlazamos el JS que maneja la animación del slider -->
    <script src="public/js/auth.js"></script>
</body>
</html>