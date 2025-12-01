<?php
// Set content type for all responses
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request silently
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

include_once 'config/Database.php';
include_once 'models/NdviData.php';
include_once 'controllers/NdviController.php';
include_once 'controllers/PredictionController.php';
include_once 'controllers/ChartsController.php';

$request_method = $_SERVER["REQUEST_METHOD"];

// Route based on query parameters
if (isset($_GET['chart']) || isset($_GET['areas'])) {
    $controller = new ChartsController();
    
    if ($request_method === 'GET') {
        $controller->handleGet();
    } else {
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed"));
    }
} else if (isset($_GET['predict']) || isset($_GET['health']) || isset($_GET['batch'])) {
    $controller = new PredictionController();
    
    if ($request_method === 'GET') {
        $controller->handleGet();
    } else if ($request_method === 'POST') {
        $controller->handlePost();
    } else {
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed"));
    }
} else {
    // Default to EnvironmentalDataController for standard data access
    $controller = new EnvironmentalDataController();

    switch($request_method) {
        case 'GET':
            $controller->handleGet();
            break;
        
        case 'POST':
            $controller->handlePost();
            break;
        
        case 'PUT':
            $controller->handlePut();
            break;
        
        case 'DELETE':
            $controller->handleDelete();
            break;
        
        default:
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
            break;
    }
}
?>