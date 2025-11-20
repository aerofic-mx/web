<?php
/*
 * =================================
 * PROCESADOR DE BÚSQUEDA DE VUELOS
 * =================================
 */

// 1. Configuración inicial
// Inicia la sesión para poder usar variables entre páginas (como $_SESSION)
session_start();
// Incluye el archivo que conecta a la base de datos ($pdo)
require_once 'src/config.php'; 

// 2. Seguridad: Verificación de Login
// Si no existe la variable 'user_id' en la sesión, significa que no está logueado.
if (!isset($_SESSION['user_id'])) {
    // Lo redirigimos a la página de inicio de sesión
    header("Location: login.php");
    // 'exit' detiene la ejecución del código aquí mismo para mayor seguridad
    exit;
}

// 3. Verificar método de envío
// Solo procesamos si el usuario llegó aquí enviando el formulario (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 4. Recibir datos del formulario (lo que el usuario escribió/seleccionó)
    $tipo_vuelo = $_POST['tipo_vuelo'];   // "sencillo" o "redondo"
    $origen_id = $_POST['origen_id'];     // ID del aeropuerto de salida
    $destino_id = $_POST['destino_id'];   // ID del aeropuerto de llegada
    $fecha_salida = $_POST['fecha_salida']; // Fecha de ida (YYYY-MM-DD)
    
    // Si enviaron fecha de regreso, la guardamos; si no, la dejamos como null
    $fecha_regreso = (!empty($_POST['fecha_regreso'])) ? $_POST['fecha_regreso'] : null;
    
    $clase = $_POST['clase']; // Clase seleccionada (Turista, Business, etc.)

    // Convertimos los textos de cantidad de pasajeros a números enteros (int)
    $adultos = (int)$_POST['adultos'];
    $ninos = (int)$_POST['ninos'];
    $bebes = (int)$_POST['bebes'];

    // Calculamos el total sumando a todos (útil para validaciones futuras)
    $total_pasajeros = $adultos + $ninos + $bebes;

    // --- Validaciones ---
    
    // A) Revisar que los campos obligatorios no estén vacíos
    if (empty($origen_id) || empty($destino_id) || empty($fecha_salida)) {
        header("Location: index.php?error=camposvacios");
        exit;
    }
    
    // B) Si es viaje redondo, es obligatorio tener fecha de regreso
    if ($tipo_vuelo == 'redondo' && empty($fecha_regreso)) {
        header("Location: index.php?error=fecharegreso");
        exit;
    }
    
    // C) Debe haber al menos un adulto responsable
    if ($adultos < 1) {
        header("Location: index.php?error=minimo_un_adulto");
        exit;
    }

    // 5. Preparar la consulta SQL
    // Seleccionamos la info del vuelo y unimos tablas (JOIN) para obtener
    // los nombres de los aeropuertos y el modelo del avión en vez de solo sus IDs.
    $sql = "SELECT 
                v.id AS vuelo_id, 
                v.numero_vuelo, 
                v.hora_salida, 
                v.hora_llegada, 
                v.precio_base,
                a_origen.nombre AS origen_nombre,
                a_destino.nombre AS destino_nombre,
                av.modelo AS avion_modelo
            FROM vuelos AS v
            JOIN aeropuertos AS a_origen ON v.aeropuerto_origen_id = a_origen.id
            JOIN aeropuertos AS a_destino ON v.aeropuerto_destino_id = a_destino.id
            JOIN aviones AS av ON v.id_avion = av.id
            WHERE 
                v.aeropuerto_origen_id = :origen_id 
                AND v.aeropuerto_destino_id = :destino_id 
                AND DATE(v.hora_salida) = :fecha_salida"; // Compara solo la fecha (ignora la hora)
    
    try {
        // Preparamos la consulta para evitar inyecciones SQL
        $stmt = $pdo->prepare($sql);
        
        // Ejecutamos la consulta pasando los valores reales
        $stmt->execute([
            'origen_id' => $origen_id,
            'destino_id' => $destino_id,
            'fecha_salida' => $fecha_salida
        ]);

        // Obtenemos todos los resultados encontrados en un array
        $resultados_ida = $stmt->fetchAll();

        // 6. Guardar todo en la SESIÓN
        // Esto es vital: Guardamos los criterios de búsqueda y los resultados
        // en $_SESSION para poder usarlos en la siguiente página (resultadosVuelos.php)
        $_SESSION['busqueda'] = [
            'tipo_vuelo' => $tipo_vuelo,
            'origen_id' => $origen_id,
            'destino_id' => $destino_id,
            'fecha_salida' => $fecha_salida,
            'fecha_regreso' => $fecha_regreso,
            'clase' => $clase,           // Guardamos la clase
            'adultos' => $adultos,       // Guardamos el desglose
            'ninos' => $ninos,
            'bebes' => $bebes,
            'total_pasajeros' => $total_pasajeros // Guardamos el total
        ];
        
        // Guardamos los vuelos encontrados
        $_SESSION['resultados_ida'] = $resultados_ida;
        
        // 7. Redirección
        // Enviamos al usuario a la página donde verá la lista de vuelos
        header("Location: resultadosVuelos.php");
        exit;

    } catch (PDOException $e) {
        // Si falla la base de datos, matamos el proceso y mostramos el error
        die("Error en la consulta de búsqueda: " . $e->getMessage());
    }

} else {
    // Si alguien intenta entrar a este archivo escribiendo la URL directo (GET),
    // lo regresamos al inicio porque no envió el formulario.
    header("Location: index.php");
    exit;
}
?>