<?php
header('Content-Type: application/json');

// Tu API Key
$apiKey = "AIzaSyBpNeFTK8Eu1WA8a2QZuegv6MYjah5ZaIM";

// Mensaje del usuario
$mensaje = $_POST['mensaje'] ?? null;

if (!$mensaje) {
    echo json_encode(["error" => "No se recibió ningún mensaje."]);
    exit;
}

// Endpoint correcto del modelo
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

// Armar la solicitud
$data = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => "Eres un asistente amigable y conciso. " . $mensaje]
            ]
        ]
    ]
];

// Inicializar cURL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_SSL_VERIFYPEER => false, // Desactivar SSL si da error
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// Validar respuesta
if ($error) {
    echo json_encode(["error" => "Error en la conexión: $error"]);
    exit;
}

$result = json_decode($response, true);

if (isset($result["candidates"][0]["content"]["parts"][0]["text"])) {
    $textoIA = $result["candidates"][0]["content"]["parts"][0]["text"];
    echo json_encode(["respuesta" => $textoIA]);
} else {
    echo json_encode([
        "error" => "No se recibió una respuesta válida de la IA.",
        "debug" => $result
    ]);
}
?>




<?php
/*


AIzaSyBpNeFTK8Eu1WA8a2QZuegv6MYjah5ZaIM

curl -X POST \
-H "Content-Type: application/json" \
-d '{"contents":[{"parts":[{"text":"Hola, ¿cómo estás?"}]}]}' \
"https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=AIzaSyBpNeFTK8Eu1WA8a2QZuegv6MYjah5ZaIM"


curl -X POST -H "Content-Type: application/json" -d "{\"contents\":[{\"parts\":[{\"text\":\"Hola, ¿cómo estás?\"}]}]}" "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=AIzaSyBpNeFTK8Eu1WA8a2QZuegv6MYjah5ZaIM"











// ¡NO NECESITAS COMPOSER NI LOS 'use' DE GEMINI AHORA!

// El código de la base de datos (db/config.php) PUEDE quedarse si está limpio.
require_once __DIR__ . "/db/config.php"; 

session_start();

// PREPARACIÓN DE SALIDA
ob_start(); 
header('Content-Type: application/json');

error_log("DEBUG: Paso 1: Iniciando script con cURL."); 

// 🔑 IMPORTANTE: Obtener la clave de Gemini
$GEMINI_API_KEY = 'AIzaSyBpNeFTK8Eu1WA8a2QZuegv6MYjah5ZaIM'; // ⬅️ PEGA TU CLAVE AQUI
$GEMINI_MODEL = 'gemini-2.5-flash';



// ==========================================================
// FUNCIÓN cURL para llamar a la API de Gemini
// ==========================================================
function callGeminiApi($prompt, $apiKey, $model) {
    
    // Instrucción del sistema
    $system_instruction = "Eres un asistente de soporte técnico amable, claro y profesional. Tu respuesta debe ser concisa.";

    // Estructura de la solicitud (incluye la instrucción del sistema y el mensaje)
    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ],
        'config' => [
            'systemInstruction' => $system_instruction
        ]
    ];

    $ch = curl_init();
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // ⚠️ Deshabilita la verificación SSL (si el problema persiste en XAMPP)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        throw new Exception("Error cURL ({$http_code}): " . $error);
    }
    
    $data = json_decode($response, true);
    
    if ($http_code !== 200) {
        $errorMessage = $data['error']['message'] ?? 'Error desconocido de la API.';
        throw new Exception("Error HTTP {$http_code}: " . $errorMessage);
    }

    return $data;
}
// ==========================================================


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensaje'])) {
    
    error_log("DEBUG: Paso 2: Solicitud POST recibida."); 

    $mensajeUsuario = trim($_POST['mensaje']);
    
    if ($mensajeUsuario === "") {
        ob_clean();
        echo json_encode(['error' => 'No hay mensaje.']);
        exit;
    }

    try {
        error_log("DEBUG: Paso 3: Llamando a la API de Gemini con cURL."); 

        // 🚀 LLAMADA AL SERVICIO CON cURL 
        $apiResponse = callGeminiApi($mensajeUsuario, $GEMINI_API_KEY, $GEMINI_MODEL);

        error_log("DEBUG: Paso 5: Respuesta de la API OK."); 

        // Extraer la respuesta (manejo seguro de la estructura)
        $respuestaChat = $apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? "Lo siento, no pude procesar esa respuesta.";

        // --- Lógica de Base de Datos (Opcional) ---
        // Aquí puedes volver a habilitar tu lógica de DB, ya que el conflicto de Composer ya no existe.
        // ...
        // --- Fin Lógica de Base de Datos ---

        ob_clean();
        echo json_encode(['respuesta' => $respuestaChat]);
        exit;

    } catch (\Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("ERROR CRÍTICO (cURL): " . $errorMessage);
        
        ob_clean();
        echo json_encode(['error' => 'Error de conexión con la IA (cURL): ' . $errorMessage]); 
    }
} else {
    ob_clean();
    echo json_encode(['error' => 'Método no permitido.']);
}  */
    ?>