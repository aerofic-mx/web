<?php
/*
 * =================================
 * PROCESADOR DE RESERVA (VIAJE REDONDO) (Actualizado)
 * =================================
 */

// 1. Iniciar sesión y cargar la BD
session_start();
require_once 'src/config.php'; // $pdo

// 2. Seguridad
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['vuelo_regreso_id'])) {
    header("Location: login.php");
    exit;
}

// 3. Obtener datos
$id_vuelo_regreso = $_POST['vuelo_regreso_id'];
$id_usuario = $_SESSION['user_id'];

if (!isset($_SESSION['busqueda']) || !isset($_SESSION['seleccion_ida'])) {
    header("Location: index.php?error=sesion_expirada");
    exit;
}

// 4. Leer los nuevos datos de la sesión
$busqueda = $_SESSION['busqueda'];
$seleccion_ida = $_SESSION['seleccion_ida'];
$id_vuelo_ida = $seleccion_ida['id_vuelo'];

$clase = $busqueda['clase'];
$adultos = $busqueda['adultos'];
$ninos = $busqueda['ninos'];
$bebes = $busqueda['bebes'];
$total_pasajeros = $busqueda['total_pasajeros'];

try {
    // 5. Obtener precio de regreso
    $sql_precio_regreso = "SELECT precio_base FROM vuelos WHERE id = :id_vuelo";
    $stmt_regreso = $pdo->prepare($sql_precio_regreso);
    $stmt_regreso->execute(['id_vuelo' => $id_vuelo_regreso]);
    $vuelo_regreso = $stmt_regreso->fetch();

    if (!$vuelo_regreso) {
        throw new Exception("El vuelo de regreso seleccionado no es válido.");
    }

    // 6. Guardar selección
    $_SESSION['seleccion_regreso'] = [
        'id_vuelo' => $id_vuelo_regreso,
        'precio_base' => $vuelo_regreso['precio_base']
    ];

    // 7. ¡NUEVO! Calcular precio final basado en la clase
    $multiplicador_clase = 1.0; // Turista
    if ($clase == 'Business') {
        $multiplicador_clase = 1.8;
    } elseif ($clase == 'Primera') {
        $multiplicador_clase = 2.5;
    }
    
    // Aplicar multiplicador a cada tramo
    $precio_base_ida_ajustado = $seleccion_ida['precio_base'] * $multiplicador_clase;
    $precio_base_regreso_ajustado = $vuelo_regreso['precio_base'] * $multiplicador_clase;

    // Sumar tramos y multiplicar por pasajeros (adultos + niños)
    $precio_final_total = ($precio_base_ida_ajustado + $precio_base_regreso_ajustado) * ($adultos + $ninos);


    // 8. Crear la reserva 'pendiente' (¡ACTUALIZADO!)
    $sql_reserva = "INSERT INTO reservas 
                        (id_usuario, id_vuelo_ida, id_vuelo_regreso, total_pasajeros, precio_final, clase, adultos, ninos, bebes)
                    VALUES 
                        (:id_usuario, :id_vuelo_ida, :id_vuelo_regreso, :total_pasajeros, :precio_final, :clase, :adultos, :ninos, :bebes)";
    
    $stmt_reserva = $pdo->prepare($sql_reserva);
    $stmt_reserva->execute([
        'id_usuario'       => $id_usuario,
        'id_vuelo_ida'     => $id_vuelo_ida,
        'id_vuelo_regreso' => $id_vuelo_regreso,
        'total_pasajeros'  => $total_pasajeros,
        'precio_final'     => $precio_final_total,
        'clase'            => $clase, // <-- Nuevo
        'adultos'          => $adultos, // <-- Nuevo
        'ninos'            => $ninos,   // <-- Nuevo
        'bebes'            => $bebes    // <-- Nuevo
    ]);

    // 9. Guardar ID de reserva
    $_SESSION['id_reserva_activa'] = $pdo->lastInsertId();

    // 10. Redirigir
    header("Location: detallesReserva.php");
    exit;

} catch (Exception $e) {
    die("Error al procesar la reserva redonda: " . $e->getMessage());
}
?>