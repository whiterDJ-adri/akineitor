<?php
// Mostrar errores en desarrollo
ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/helpers.php';
require __DIR__ . '/backendClient.php';

session_start();

// Router por querystring
$action = trim($_GET['action'] ?? 'home');

// Si está vacío después del trim, usar 'home'
if (empty($action)) {
    $action = 'home';
}

// Normalizar la acción para evitar problemas
$action = strtolower($action);

switch ($action) {
    case 'home':
        // Decide qué vista mostrar según la sesión
        $vm = [
            'error' => $_SESSION['flash_error'] ?? null,
            'pregunta' => $_SESSION['pregunta'] ?? null,
            'progreso' => $_SESSION['progreso'] ?? null,
            'resultado' => $_SESSION['resultado'] ?? null,
            'partidaId' => $_SESSION['partidaId'] ?? null,
            'inicio' => $_SESSION['inicio'] ?? null,
            'confianza' => $_SESSION['confianza'] ?? null
        ];
        $_SESSION['flash_error'] = null;
        render('inicio', $vm);
        break;

    case 'comenzar':
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            die('CSRF inválido');
        }
        try {
            $api = new BackendClient();
            $resp = $api->crearPartida(); // { partidaId, pregunta, progreso }

            $_SESSION['partidaId'] = $resp['partidaId'] ?? null;
            $_SESSION['pregunta'] = $resp['pregunta'] ?? null;
            $_SESSION['progreso'] = $resp['progreso'] ?? null;
            $_SESSION['resultado'] = null;

            redirect('index.php?action=home');
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'No se pudo iniciar la partida.' . $e->getMessage();
            redirect('index.php?action=home');
        }
        break;

    case 'responder':
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            die('CSRF inválido');
        }
        $partidaId = $_SESSION['partidaId'] ?? null;
        $pregunta = $_SESSION['pregunta'] ?? null;
        $respuesta = $_POST['respuesta'] ?? null;

        if (!$partidaId || !$pregunta || !$respuesta) {
            $_SESSION['flash_error'] = 'Solicitud incompleta.';
            redirect('index.php?action=home');
        }

        try {
            $api = new BackendClient();
            $body = ['preguntaId' => $pregunta['id'], 'respuesta' => $respuesta];
            $resp = $api->responder($partidaId, $body);

            if (isset($resp['pregunta'])) {
                $_SESSION['pregunta'] = $resp['pregunta'];
                $_SESSION['progreso'] = $resp['progreso'] ?? null;
                $_SESSION['confianza'] = $resp['confianza'] ?? null;
                $_SESSION['resultado'] = null;
            } elseif (isset($resp['resultado'])) {
                $_SESSION['resultado'] = $resp['resultado'];
                $_SESSION['pregunta'] = null;
                $_SESSION['progreso'] = null;
                $_SESSION['confianza'] = null;
            }
            redirect('index.php?action=home');
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error al enviar la respuesta. ' . $e->getMessage();
            redirect('index.php?action=home');
        }
        break;

    case 'reiniciar':
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            die('CSRF inválido');
        }

        // Limpiar completamente la sesión del juego
        $_SESSION['partidaId'] = null;
        $_SESSION['pregunta'] = null;
        $_SESSION['progreso'] = null;
        $_SESSION['resultado'] = null;
        $_SESSION['confianza'] = null;
        $_SESSION['flash_error'] = null;

        // Redirigir a la página de inicio limpia
        redirect('index.php?action=home');
        break;

    case 'corregir':
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            die('CSRF inválido');
        }
        $partidaId = $_SESSION['partidaId'] ?? null;
        $personajeId = isset($_POST['personajeId']) ? (int)$_POST['personajeId'] : null;
        if (!$partidaId || !$personajeId) {
            $_SESSION['flash_error'] = 'Datos de corrección incompletos.';
            redirect('index.php?action=home');
        }
        try {
            $api = new BackendClient();
            $api->corregir((string)$partidaId, $personajeId);
            $_SESSION['flash_error'] = null;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'No se pudo aplicar la corrección.';
        }
        redirect('index.php?action=home');
        break;

    case 'sugerir':
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            die('CSRF inválido');
        }
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = $_POST['descripcion'] ?? null;
        $imagen = $_POST['imagen_url'] ?? null;
        if ($nombre === '') {
            $_SESSION['flash_error'] = 'Nombre requerido para sugerir personaje.';
            redirect('index.php?action=home');
        }
        try {
            $api = new BackendClient();
            $api->sugerirPersonaje($nombre, $descripcion, $imagen);
            $_SESSION['flash_error'] = null;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'No se pudo sugerir el personaje.';
        }
        redirect('index.php?action=home');
        break;

    default:
        // Si la acción no existe, redirigir a home
        header('Location: index.php?action=home');
        exit;
}
